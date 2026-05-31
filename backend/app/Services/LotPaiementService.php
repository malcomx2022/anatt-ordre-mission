<?php

namespace App\Services;

use App\Models\LotPaiement;
use App\Models\Mission;
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
        $versements = $this->versementsEligibles($missionId, $tranche, $operateur);

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
     * Génère (ou régénère) le fichier d'un lot. Régénération autorisée tant que le lot
     * n'est pas CONFIRME ; seuls les versements non confirmés sont (ré)inclus.
     *
     * @return array{lot:LotPaiement, chemin:string}
     */
    public function generer(int $missionId, string $tranche, string $operateur, User $user): array
    {
        return DB::transaction(function () use ($missionId, $tranche, $operateur, $user) {
            $mission = Mission::findOrFail($missionId);

            $lot = LotPaiement::where('mission_id', $missionId)
                ->where('tranche', $tranche)
                ->where('operateur', $operateur)
                ->lockForUpdate()
                ->first();

            if ($lot && $lot->statut === 'CONFIRME') {
                throw ValidationException::withMessages([
                    'lot' => ['Ce lot est déjà confirmé : régénération interdite (anti-double-paiement).'],
                ]);
            }

            $versements = $this->versementsEligibles($missionId, $tranche, $operateur);

            if ($versements->isEmpty()) {
                throw ValidationException::withMessages([
                    'lot' => ['Aucun versement éligible (non confirmé) pour ce lot.'],
                ]);
            }

            // Génération du fichier au format exact opérateur (.txt MTN / .xlsx Moov).
            $generateur = $operateur === 'MTN' ? $this->mtnGenerator : $this->moovGenerator;
            $meta = $generateur->generer($mission, $tranche, $versements);

            $lot = LotPaiement::updateOrCreate(
                ['mission_id' => $missionId, 'tranche' => $tranche, 'operateur' => $operateur],
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

            // Rattachement des versements inclus + passage au statut GENERE.
            Versement::whereIn('id', $versements->pluck('id'))
                ->update(['lot_id' => $lot->id, 'statut' => 'GENERE']);

            AuditService::log($user, 'GENERATE_LOT', 'lot_paiement', $lot->id, [
                'mission' => $mission->destination,
                'tranche' => $tranche,
                'operateur' => $operateur,
                'nb_lignes' => $meta['nb_lignes'],
                'montant_total' => $meta['montant_total'],
                'fichier' => $meta['chemin'],
            ]);

            return ['lot' => $lot, 'chemin' => $meta['chemin']];
        });
    }

    /**
     * Confirmation manuelle après dépôt réussi sur la plateforme opérateur.
     * Lot + versements -> CONFIRME, date_paiement renseignée.
     */
    public function confirmer(LotPaiement $lot, User $user): LotPaiement
    {
        $lot = DB::transaction(function () use ($lot, $user) {
            $lot = LotPaiement::lockForUpdate()->findOrFail($lot->id);

            if ($lot->statut === 'CONFIRME') {
                throw ValidationException::withMessages([
                    'lot' => ['Ce lot est déjà confirmé.'],
                ]);
            }

            $now = now();

            Versement::where('lot_id', $lot->id)
                ->where('statut', '!=', 'CONFIRME')
                ->update(['statut' => 'CONFIRME', 'date_paiement' => $now]);

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

    /**
     * Versements éligibles : tranche + opérateur donnés, statut != CONFIRME,
     * OM ni rejeté ni annulé.
     */
    private function versementsEligibles(int $missionId, string $tranche, string $operateur)
    {
        return Versement::query()
            ->where('type', $tranche)
            ->where('operateur', $operateur)
            ->where('statut', '!=', 'CONFIRME')
            ->whereHas('ordreMission', fn ($q) => $q
                ->where('mission_id', $missionId)
                ->whereNotIn('statut', ['REJETE', 'ANNULE']))
            ->with('ordreMission.agent')
            ->orderBy('id')
            ->get();
    }
}
