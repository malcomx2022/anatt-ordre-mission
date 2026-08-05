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

        $lot->update(['statut' => 'ERREUR']);

        AuditService::log($user, 'ERROR_LOT', 'lot_paiement', $lot->id, [
            'tranche' => $lot->tranche,
            'operateur' => $lot->operateur,
        ]);

        return $lot->fresh();
    }
}
