<?php

namespace App\Services\Paiement;

use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\User;
use App\Models\Versement;
use App\Services\AuditService;
use App\Services\Paiement\Concerns\NormaliseBeneficiaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Base des générateurs de fichiers de paiement (mode GÉNÉRATION).
 *
 * Pilote, dans une transaction, tout le flux de génération d'un lot :
 *   1. sélection du périmètre (mission × tranche × opérateur, statut != CONFIRME) ;
 *   2. construction du fichier au format opérateur (délégué à la sous-classe) ;
 *   3. création/maj du lot en statut GENERE ;
 *   4. passage des versements inclus au statut GENERE ;
 *   5. journalisation d'audit.
 *
 * Idempotence : un lot CONFIRME ne peut pas être régénéré (anti-double-paiement).
 */
abstract class FileGenerator
{
    use NormaliseBeneficiaire;

    /** Opérateur traité par le générateur ('MTN' | 'MOOV'). */
    abstract protected function operateur(): string;

    /**
     * Construit le fichier sur le disque et renvoie ses métadonnées.
     *
     * @param  Collection<int,Versement>  $versements
     * @return array{chemin:string, nom_fichier:string, nb_lignes:int, montant_total:int}
     */
    abstract protected function construireFichier(Mission $mission, string $tranche, Collection $versements): array;

    /**
     * Génère (ou régénère) le lot de la mission/tranche pour cet opérateur.
     *
     * @return array{lot:LotPaiement, chemin:string}
     */
    public function generer(int $missionId, string $tranche, User $user): array
    {
        return DB::transaction(function () use ($missionId, $tranche, $user) {
            $mission = Mission::findOrFail($missionId);

            $lot = LotPaiement::where('mission_id', $missionId)
                ->where('tranche', $tranche)
                ->where('operateur', $this->operateur())
                ->lockForUpdate()
                ->first();

            if ($lot && $lot->statut === 'CONFIRME') {
                throw ValidationException::withMessages([
                    'lot' => ['Ce lot est déjà confirmé : régénération interdite (anti-double-paiement).'],
                ]);
            }

            $versements = Versement::eligiblesLot($missionId, $tranche, $this->operateur())->get();

            if ($versements->isEmpty()) {
                throw ValidationException::withMessages([
                    'lot' => ['Aucun versement éligible (non confirmé) pour ce lot.'],
                ]);
            }

            $meta = $this->construireFichier($mission, $tranche, $versements);

            $lot = LotPaiement::updateOrCreate(
                ['mission_id' => $missionId, 'tranche' => $tranche, 'operateur' => $this->operateur()],
                [
                    'statut' => 'GENERE',
                    'nom_fichier' => $meta['chemin'],
                    'nb_lignes' => $meta['nb_lignes'],
                    'montant_total' => $meta['montant_total'],
                    'genere_le' => now(),
                    'genere_par' => $user->id,
                    'confirme_le' => null,
                    'confirme_par' => null,
                ],
            );

            Versement::whereIn('id', $versements->pluck('id'))
                ->update(['lot_id' => $lot->id, 'statut' => 'GENERE']);

            AuditService::log($user, 'GENERATE_LOT', 'lot_paiement', $lot->id, [
                'mission' => $mission->destination,
                'tranche' => $tranche,
                'operateur' => $this->operateur(),
                'nb_lignes' => $meta['nb_lignes'],
                'montant_total' => $meta['montant_total'],
                'fichier' => $meta['chemin'],
            ]);

            return ['lot' => $lot, 'chemin' => $meta['chemin']];
        });
    }
}
