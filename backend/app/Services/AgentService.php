<?php

namespace App\Services;

use App\Imports\AgentsImport;
use App\Models\Agent;
use App\Models\Versement;
use App\Support\Telephone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AgentService
{
    public function lister(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($filters)->orderBy('nom')->paginate($perPage);
    }

    /**
     * Recherche légère pour l'autocomplétion du wizard OM (nom / matricule / IFU).
     */
    public function rechercher(string $terme, int $limite = 15): Collection
    {
        $terme = trim($terme);

        return Agent::query()
            ->where('actif', true)
            ->where(function ($q) use ($terme) {
                $q->where('nom', 'like', "%{$terme}%")
                    ->orWhere('prenoms', 'like', "%{$terme}%")
                    ->orWhere('matricule', 'like', "%{$terme}%")
                    ->orWhere('ifu', 'like', "%{$terme}%");
            })
            ->orderBy('nom')
            ->limit($limite)
            ->get();
    }

    public function creer(array $data): Agent
    {
        return Agent::create($data);
    }

    public function afficher(Agent $agent): Agent
    {
        return $agent->load('ordresMission');
    }

    public function modifier(Agent $agent, array $data): Agent
    {
        $agent->update($data);

        // Propagation automatique des nouvelles coordonnées aux versements en attente
        Versement::whereHas('ordreMission', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('statut', 'EN_ATTENTE')
            ->update([
                'telephone' => $agent->telephone,
                'operateur' => $agent->operateur,
            ]);

        return $agent->fresh();
    }

    public function desactiver(Agent $agent): void
    {
        $agent->update(['actif' => false]);
    }

    /**
     * Import du personnel (CSV/XLSX) avec rapport d'erreurs ligne à ligne.
     * Atomique : à la moindre erreur, AUCUN agent n'est inséré (rollback).
     */
    public function importer(UploadedFile $fichier): array
    {
        $rows = Excel::toCollection(new AgentsImport, $fichier)->first() ?? collect();

        $erreurs = [];
        $valides = [];
        $matriculesVus = [];
        $telephonesVus = [];

        foreach ($rows as $index => $row) {
            $ligne = $index + 2; // +1 (base 0) +1 (ligne d'en-tête)
            $data = $this->normaliserLigne($row->toArray());

            // Ligne entièrement vide -> ignorée silencieusement.
            if ($data === null) {
                continue;
            }

            $erreursLigne = $this->validerLigne($data, $matriculesVus, $telephonesVus);

            if (! empty($erreursLigne)) {
                $erreurs[] = [
                    'ligne' => $ligne,
                    'erreurs' => $erreursLigne,
                    'donnees' => $data,
                ];

                continue;
            }

            $matriculesVus[$data['matricule']] = $ligne;
            $telephonesVus[$data['telephone']] = $ligne;
            $valides[] = $data;
        }

        // Échec : rollback total, on ne persiste rien.
        if (! empty($erreurs)) {
            return [
                'importes' => 0,
                'ignores' => count($erreurs),
                'rollback' => true,
                'erreurs' => $erreurs,
            ];
        }

        DB::transaction(function () use ($valides) {
            foreach ($valides as $data) {
                Agent::create($data);
            }
        });

        return [
            'importes' => count($valides),
            'ignores' => 0,
            'rollback' => false,
            'erreurs' => [],
        ];
    }

    private function baseQuery(array $filters)
    {
        $query = Agent::query();

        if ($search = ($filters['search'] ?? null)) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenoms', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('ifu', 'like', "%{$search}%");
            });
        }

        if ($categorie = ($filters['categorie'] ?? null)) {
            $query->where('categorie', $categorie);
        }

        if ($operateur = ($filters['operateur'] ?? null)) {
            $query->where('operateur', $operateur);
        }

        // On ne filtre que si une valeur NON VIDE est fournie : un « actif= »
        // (chaîne vide, cas « Tous ») ne doit pas être interprété comme false —
        // sinon la liste ne remonterait que les inactifs.
        if (($filters['actif'] ?? '') !== '') {
            $query->where('actif', filter_var($filters['actif'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query;
    }

    /** Normalise une ligne du fichier ; retourne null si la ligne est vide. */
    private function normaliserLigne(array $row): ?array
    {
        $get = fn (string $key) => isset($row[$key]) && $row[$key] !== '' ? trim((string) $row[$key]) : null;

        $data = [
            'nom' => $get('nom'),
            'prenoms' => $get('prenoms'),
            'matricule' => $get('matricule'),
            'ifu' => $get('ifu'),
            'grade' => $get('grade'),
            'indice' => $get('indice') !== null ? (int) $get('indice') : null,
            'salaire_brut' => $get('salaire_brut') !== null ? (int) $get('salaire_brut') : null,
            'categorie' => $get('categorie'),
            'telephone' => $get('telephone'),
            'operateur' => $get('operateur') ? strtoupper($get('operateur')) : null,
        ];

        if (! array_filter($data, fn ($v) => $v !== null && $v !== '')) {
            return null;
        }

        // Détermination automatique de la catégorie si absente.
        if (! $data['categorie'] && ($data['indice'] !== null || $data['salaire_brut'] !== null)) {
            $data['categorie'] = Agent::determinerCategorie($data['indice'], $data['salaire_brut']);
        }

        $data['actif'] = true;

        return $data;
    }

    /** Valide une ligne ; renvoie la liste des messages d'erreur (vide si OK). */
    private function validerLigne(array $data, array $matriculesVus, array $telephonesVus): array
    {
        $validator = Validator::make($data, [
            'nom' => 'required|string|max:255',
            'prenoms' => 'required|string|max:255',
            'matricule' => 'required|string|max:50|unique:agents,matricule',
            'ifu' => 'nullable|string|max:50',
            'grade' => 'nullable|string|max:255',
            'indice' => 'nullable|integer|min:0',
            'salaire_brut' => 'nullable|integer|min:0',
            'categorie' => 'required|in:ORDRE_1,ORDRE_2,ORDRE_3',
            'telephone' => ['required', 'string', 'regex:'.config('benin.telephone.regex'), 'unique:agents,telephone'],
            'operateur' => 'required|in:MTN,MOOV',
        ]);

        $erreurs = array_values($validator->errors()->all());

        if ($data['indice'] === null && $data['salaire_brut'] === null) {
            $erreurs[] = "L'indice ou le salaire brut est requis pour déterminer la catégorie.";
        }

        if ($data['telephone'] && $data['operateur'] && ! Telephone::estCoherent($data['telephone'], $data['operateur'])) {
            $deduit = Telephone::operateurPour($data['telephone']);
            $erreurs[] = $deduit
                ? "Le numéro correspond à l'opérateur {$deduit}, pas à {$data['operateur']}."
                : 'Le préfixe du numéro ne correspond à aucun opérateur connu.';
        }

        // Doublons à l'intérieur du fichier lui-même.
        if ($data['matricule'] && isset($matriculesVus[$data['matricule']])) {
            $erreurs[] = "Matricule en doublon avec la ligne {$matriculesVus[$data['matricule']]} du fichier.";
        }
        if ($data['telephone'] && isset($telephonesVus[$data['telephone']])) {
            $erreurs[] = "Téléphone en doublon avec la ligne {$telephonesVus[$data['telephone']]} du fichier.";
        }

        return $erreurs;
    }
}
