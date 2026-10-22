<?php

namespace App\Services;

use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\Paiement\MoovFileGenerator;
use App\Services\Paiement\MtnFileGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestion des lots de paiement en mode GÉNÉRATION (aucun réimport, aucune API opérateur).
 *
 * Un lot = 1 mission × 1 tranche [AVANCE|RELIQUAT] × 1 opérateur [MTN|MOOV].
 * Idempotence : un versement CONFIRME n'est jamais réinclus dans un nouveau fichier.
 */
class LotPaiementService
{
    public function __construct(
        private readonly MtnFileGenerator $mtnGenerator,
        private readonly MoovFileGenerator $moovGenerator,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Bénéficiaires éligibles d'un lot : versements de la tranche/opérateur au statut
     * != CONFIRME, dont l'OM n'est ni rejeté ni annulé.
     */
    public function previsualiser(int $missionId, string $tranche, string $operateur): array
    {
        $mission = Mission::findOrFail($missionId);
        $versements = Versement::eligiblesLot($missionId, $tranche, $operateur)->get();

        $lignes = $versements->map(fn (Versement $v) => [
            'versement_id' => $v->id,
            'reference_semo' => $v->ordreMission->reference_semo,
            'beneficiaire' => $v->ordreMission->agent->nom_complet,
            'telephone' => $v->telephone,
            'montant' => $v->montant,
        ])->values();

        return [
            'mission_id' => $mission->id,
            'mission' => $mission->objet.' — '.$mission->destination,
            'tranche' => $tranche,
            'operateur' => $operateur,
            'nb_lignes' => $lignes->count(),
            'total' => (int) $lignes->sum('montant'),
            'beneficiaires' => $lignes,
        ];
    }

    /**
     * Génère (ou régénère) le fichier d'un lot en déléguant au générateur autonome
     * de l'opérateur (qui pilote périmètre, lot GENERE, statuts versements, idempotence).
     *
     * @return array{lot:LotPaiement, chemin:string}
     */
    public function generer(int $missionId, string $tranche, string $operateur, User $user): array
    {
        $generateur = $operateur === 'MTN' ? $this->mtnGenerator : $this->moovGenerator;

        return $generateur->generer($missionId, $tranche, $user);
    }

    /**
     * Confirmation manuelle après dépôt réussi sur la plateforme opérateur.
     * Lot + versements -> CONFIRME, date_paiement renseignée.
     */
    public function confirmer(LotPaiement $lot, User $user): LotPaiement
    {
        $lot = DB::transaction(function () use ($lot, $user) {
            $lot = LotPaiement::lockForUpdate()->findOrFail($lot->id);

            // Seul un lot GÉNÉRÉ se confirme : un lot déjà confirmé (anti-double-
            // paiement) comme un lot en erreur (dépôt échoué → régénérer d'abord)
            // sont refusés. La confirmation atteste d'un dépôt réussi du fichier courant.
            if ($lot->statut !== 'GENERE') {
                throw ValidationException::withMessages([
                    'lot' => ["Ce lot est au statut « {$lot->statut} » : seul un lot généré peut être confirmé (régénérez-le si nécessaire)."],
                ]);
            }

            $now = now();

            // OM concernés par ce lot, avant la mise à jour des statuts.
            $omIds = Versement::where('lot_id', $lot->id)->pluck('ordre_mission_id')->unique();

            Versement::where('lot_id', $lot->id)
                ->where('statut', '!=', 'CONFIRME')
                ->update(['statut' => 'CONFIRME', 'date_paiement' => $now]);

            // Un OM dont TOUS les versements sont confirmés passe à SOLDE.
            foreach ($omIds as $omId) {
                $resteAConfirmer = Versement::where('ordre_mission_id', $omId)
                    ->where('statut', '!=', 'CONFIRME')
                    ->exists();

                if (! $resteAConfirmer) {
                    OrdreMission::where('id', $omId)
                        ->whereNotIn('statut', ['SOLDE', 'ANNULE'])
                        ->update(['statut' => 'SOLDE']);
                }
            }

            $lot->update([
                'statut' => 'CONFIRME',
                'confirme_le' => $now,
                'confirme_par' => $user->id,
            ]);

            AuditService::log($user, 'CONFIRM_LOT', 'lot_paiement', $lot->id, [
                'tranche' => $lot->tranche,
                'operateur' => $lot->operateur,
                'montant_total' => $lot->montant_total,
                'date_paiement' => $now->toISOString(),
            ]);

            return $lot->fresh();
        });

        // Notification hors transaction (sync queue) : le lot est définitivement confirmé.
        $this->notifications->lotConfirme($lot->load('mission'));

        return $lot;
    }

    /** Marque un lot en ERREUR (autorise une régénération ultérieure). */
    public function marquerErreur(LotPaiement $lot, User $user): LotPaiement
    {
        if ($lot->statut === 'CONFIRME') {
            throw ValidationException::withMessages([
                'lot' => ['Un lot confirmé ne peut pas être marqué en erreur.'],
            ]);
        }

        DB::transaction(function () use ($lot) {
            $lot->update(['statut' => 'ERREUR']);

            // Le dépôt a échoué : rien n'a été payé. Les versements du lot redeviennent
            // EN_ATTENTE (amendables, mis à jour par le référentiel agent) et reprennent
            // les coordonnées COURANTES de l'agent — un numéro corrigé après l'échec
            // part ainsi dans le fichier régénéré.
            Versement::with('ordreMission.agent')
                ->where('lot_id', $lot->id)
                ->where('statut', 'GENERE')
                ->get()
                ->each(function (Versement $v) {
                    $agent = $v->ordreMission?->agent;
                    $v->update([
                        'statut' => 'EN_ATTENTE',
                        'telephone' => $agent->telephone ?? $v->telephone,
                        'operateur' => $agent->operateur ?? $v->operateur,
                    ]);
                });
        });

        AuditService::log($user, 'ERROR_LOT', 'lot_paiement', $lot->id, [
            'tranche' => $lot->tranche,
            'operateur' => $lot->operateur,
        ]);

        return $lot->fresh();
    }

    /**
     * Régularisations : versements de reliquat basculés en A_RECOUVRER (trop-perçu
     * après amendement) ou déjà RECOUVRE. Le montant à récupérer est la valeur
     * absolue du reliquat négatif. Cf. docs/amendement-om.md.
     *
     * @param  array<string,mixed>  $filtres
     * @return array<int,array<string,mixed>>
     */
    public function regularisations(array $filtres = [], int $perPage = 20): array
    {
        // Périmètre commun (mission + recherche), avant le filtre par statut :
        // le « reste à recouvrer » doit rester global à ce périmètre, quel que
        // soit l'onglet (à recouvrer / recouvrés) affiché.
        $base = Versement::query()->whereIn('statut', ['A_RECOUVRER', 'RECOUVRE']);

        if (! empty($filtres['mission_id'])) {
            $base->whereHas('ordreMission', fn ($o) => $o->where('mission_id', (int) $filtres['mission_id']));
        }
        if (! empty($filtres['search'])) {
            $terme = '%'.$filtres['search'].'%';
            $base->whereHas('ordreMission', fn ($o) => $o
                ->where('reference_semo', 'like', $terme)
                ->orWhereHas('agent', fn ($a) => $a->where('nom', 'like', $terme)->orWhere('prenoms', 'like', $terme))
                ->orWhereHas('mission', fn ($m) => $m->where('objet', 'like', $terme)->orWhere('destination', 'like', $terme)));
        }

        // montant d'un A_RECOUVRER est négatif : la somme l'est aussi → valeur absolue.
        $totalARecouvrer = abs((int) (clone $base)->where('statut', 'A_RECOUVRER')->sum('montant'));

        $liste = clone $base;
        if (! empty($filtres['statut']) && in_array($filtres['statut'], ['A_RECOUVRER', 'RECOUVRE'], true)) {
            $liste->where('statut', $filtres['statut']);
        }

        $page = $liste->with(['ordreMission.agent', 'ordreMission.mission'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Versement $v) => [
                'versement_id' => $v->id,
                'ordre_mission_id' => $v->ordre_mission_id,
                'reference_semo' => $v->ordreMission->reference_semo,
                'beneficiaire' => $v->ordreMission->agent->nom_complet,
                'mission' => $v->ordreMission->mission->objet.' — '.$v->ordreMission->mission->destination,
                'operateur' => $v->operateur,
                'telephone' => $v->telephone,
                'montant_a_recouvrer' => abs((int) $v->montant),
                'statut' => $v->statut,
                'date_regularisation' => $v->date_paiement?->toISOString(),
            ]);

        // Paginateur « à plat » (data + current_page/last_page/total…) + le total global.
        return $page->toArray() + ['total_a_recouvrer' => $totalARecouvrer];
    }

    /**
     * Marque une régularisation comme recouvrée (retenue appliquée par la RH).
     * Constat manuel : l'application ne réalise pas la retenue sur salaire.
     */
    public function marquerRecouvre(Versement $versement, User $user): Versement
    {
        if ($versement->statut !== 'A_RECOUVRER') {
            throw ValidationException::withMessages([
                'versement' => ["Ce versement est au statut « {$versement->statut} » : seule une régularisation « à recouvrer » peut être marquée recouvrée."],
            ]);
        }

        $versement->update(['statut' => 'RECOUVRE', 'date_paiement' => now()]);

        AuditService::log($user, 'RECOUVRER_VERSEMENT', 'versement', $versement->id, [
            'montant_recouvre' => abs((int) $versement->montant),
            'ordre_mission_id' => $versement->ordre_mission_id,
        ]);

        return $versement->fresh();
    }
}
