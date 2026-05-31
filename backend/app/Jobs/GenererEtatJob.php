<?php

namespace App\Jobs;

use App\Services\RapportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Production asynchrone d'un état/rapport (export xlsx/pdf) sur la file Redis « etats ».
 * 3 tentatives, backoff progressif. Suivi via le cache (trackingId).
 */
class GenererEtatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60];

    /**
     * @param  array<string,mixed>  $params  dimension, format, date_debut, date_fin
     */
    public function __construct(
        public array $params,
        public string $trackingId,
    ) {
        $this->onQueue('etats');
    }

    public function handle(RapportService $service): void
    {
        Cache::put($this->cle(), ['statut' => 'EN_COURS'], now()->addHour());

        $resultat = $service->exporter($this->params);

        Cache::put($this->cle(), [
            'statut' => 'TERMINE',
            'fichier' => $resultat['fichier'],
            'url' => $resultat['url'],
        ], now()->addDay());
    }

    public function failed(Throwable $e): void
    {
        Cache::put($this->cle(), ['statut' => 'ERREUR', 'message' => $e->getMessage()], now()->addDay());
    }

    public function cle(): string
    {
        return "etat_job:{$this->trackingId}";
    }
}
