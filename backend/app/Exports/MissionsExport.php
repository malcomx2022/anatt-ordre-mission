<?php

namespace App\Exports;

use App\Models\OrdreMission;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MissionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly ?string $dateDebut = null,
        private readonly ?string $dateFin = null,
        private readonly ?string $statut = null,
    ) {}

    public function query()
    {
        $query = OrdreMission::with(['agent', 'taux']);

        if ($this->dateDebut) {
            $query->where('date_depart', '>=', $this->dateDebut);
        }
        if ($this->dateFin) {
            $query->where('date_retour', '<=', $this->dateFin);
        }
        if ($this->statut) {
            $query->where('statut', $this->statut);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Numero',
            'Agent',
            'Matricule',
            'Service',
            'Objet',
            'Destination',
            'Date depart',
            'Date retour',
            'Nb jours',
            'Taux journalier (FCFA)',
            'Montant per diem (FCFA)',
            'Statut',
            'Date creation',
        ];
    }

    public function map($ordre): array
    {
        return [
            $ordre->numero,
            $ordre->agent ? "{$ordre->agent->prenom} {$ordre->agent->nom}" : '',
            $ordre->agent?->matricule ?? '',
            $ordre->agent?->service ?? '',
            $ordre->objet,
            $ordre->destination,
            $ordre->date_depart?->format('d/m/Y'),
            $ordre->date_retour?->format('d/m/Y'),
            $ordre->nb_jours,
            $ordre->taux?->montant_journalier,
            $ordre->montant_per_diem,
            $ordre->statut,
            $ordre->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D9E4F'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Ordres de Mission';
    }
}
