<?php

namespace App\Services;

use App\Models\OrdreMission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGenerator
{
    public function genererOrdreMission(OrdreMission $ordre): string
    {
        $ordre->load(['agent', 'taux', 'valideurChef', 'valideurDG']);

        $data = [
            'ordre' => $ordre,
            'agent' => $ordre->agent,
            'taux' => $ordre->taux,
            'numero' => $ordre->numero,
            'objet' => $ordre->objet,
            'destination' => $ordre->destination,
            'date_depart' => $ordre->date_depart->format('d/m/Y'),
            'date_retour' => $ordre->date_retour->format('d/m/Y'),
            'nb_jours' => $ordre->nb_jours,
            'montant_per_diem' => number_format($ordre->montant_per_diem, 0, ',', ' '),
            'valideur_chef' => $ordre->valideurChef?->name,
            'valideur_dg' => $ordre->valideurDG?->name,
            'date_generation' => now()->format('d/m/Y H:i'),
        ];

        $pdf = Pdf::loadView('pdf.ordre-mission', $data);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption([
            'defaultFont' => 'sans-serif',
            'isRemoteEnabled' => true,
        ]);

        $filename = "ordres/{$ordre->numero}.pdf";
        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }

    public function telecharger(OrdreMission $ordre)
    {
        $filename = "ordres/{$ordre->numero}.pdf";

        if (! Storage::disk('local')->exists($filename)) {
            $filename = $this->genererOrdreMission($ordre);
        }

        return Storage::disk('local')->download($filename, "{$ordre->numero}.pdf");
    }
}
