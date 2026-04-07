<?php

namespace App\Jobs;

use App\Models\OrdreMission;
use App\Services\MoovPaymentService;
use App\Services\MtnPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePaiementFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $ordreIds,
        private readonly string $operateur,
    ) {}

    public function handle(MoovPaymentService $moovService, MtnPaymentService $mtnService): void
    {
        $ordres = OrdreMission::with('agent')
            ->whereIn('id', $this->ordreIds)
            ->where('statut', 'APPROUVE_DG')
            ->get();

        if ($ordres->isEmpty()) {
            Log::warning("GeneratePaiementFile: aucun ordre approuve trouve pour les IDs: " . implode(',', $this->ordreIds));
            return;
        }

        $resultat = match ($this->operateur) {
            'MOOV' => $moovService->genererFichier($ordres),
            'MTN' => $mtnService->genererFichier($ordres),
            default => throw new \InvalidArgumentException("Operateur inconnu: {$this->operateur}"),
        };

        Log::info("GeneratePaiementFile: fichier {$resultat['fichier']} genere avec {$resultat['nb_paiements']} paiements pour un total de {$resultat['montant_total']} FCFA");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GeneratePaiementFile: echec generation fichier {$this->operateur} - " . $exception->getMessage());
    }
}
