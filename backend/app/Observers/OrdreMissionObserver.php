<?php

namespace App\Observers;

use App\Models\OrdreMission;
use App\Services\EtatPaiementService;
use Illuminate\Support\Facades\DB;

/**
 * Garde l'état de paiement d'une mission en phase avec ses OM.
 *
 * Dès qu'un OM change (création, montant modifié, rejet, annulation,
 * suppression), l'état BROUILLON / REJETE de la mission est recalé : le
 * brouillon reste toujours fidèle sans que l'utilisateur ait à cliquer sur
 * « Rafraîchir ». Un état signé (CONTROLE / VISA_DAF / VALIDE) est protégé —
 * cf. EtatPaiementService::synchroniser.
 *
 * On resynchronise APRÈS la fin de la transaction en cours (`afterCommit`) :
 * l'OM qui vient d'être sauvegardé fait partie du sum, sans lecture dirty.
 */
class OrdreMissionObserver
{
    public function __construct(private readonly EtatPaiementService $service) {}

    public function saved(OrdreMission $ordre): void
    {
        $this->resynchroniser($ordre);
    }

    public function deleted(OrdreMission $ordre): void
    {
        $this->resynchroniser($ordre);
    }

    private function resynchroniser(OrdreMission $ordre): void
    {
        $missionId = (int) $ordre->mission_id;

        // Hors transaction, afterCommit() exécute la closure immédiatement ;
        // dans une transaction, il attend le commit final : dans les deux cas,
        // le sum reflète l'état persisté, pas un instantané intermédiaire.
        DB::afterCommit(fn () => $this->service->synchroniser($missionId));
    }
}
