<?php

namespace App\Jobs;

use App\Models\LotPaiement;
use App\Models\User;
use App\Services\LotPaiementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Génération asynchrone d'un lot de paiement (gros lots) sur la file « paiements ».
 * Reprise sur erreur : 3 tentatives, backoff progressif. Suivi via le cache (trackingId).
 */
class GenererLotPaiementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $missionId,
        public string $tranche,
        public string $operateur,
        public int $userId,
        public string $trackingId,
    ) {
        $this->onQueue('paiements');
    }

    public function handle(LotPaiementService $service): void
    {
        Cache::put($this->cle(), ['statut' => 'EN_COURS'], now()->addHour());

        $user = User::findOrFail($this->userId);
        $resultat = $service->generer($this->missionId, $this->tranche, $this->operateur, $user);

        Cache::put($this->cle(), [
            'statut' => 'TERMINE',
            'lot_id' => $resultat['lot']->id,
            'fichier' => basename($resultat['chemin']),
        ], now()->addDay());
    }

    public function failed(Throwable $e): void
    {
        Cache::put($this->cle(), ['statut' => 'ERREUR', 'message' => $e->getMessage()], now()->addDay());

        // Le dernier lot de la clé, s'il n'est pas confirmé, passe en ERREUR par le même
        // chemin que l'action manuelle : ses versements redeviennent EN_ATTENTE avec les
        // coordonnées courantes de l'agent (sinon : lot ERREUR, versements GENERE, OM non
        // amendable). Un lot CONFIRME n'est jamais touché.
        $lot = LotPaiement::where('mission_id', $this->missionId)
            ->where('tranche', $this->tranche)
            ->where('operateur', $this->operateur)
            ->orderByDesc('sequence')
            ->first();
        $user = User::find($this->userId);

        if ($lot && $user && in_array($lot->statut, ['GENERE', 'ERREUR'], true)) {
            app(LotPaiementService::class)->marquerErreur($lot, $user);
        }
    }

    public function cle(): string
    {
        return "lot_job:{$this->trackingId}";
    }
}
