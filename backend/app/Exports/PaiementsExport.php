<?php

namespace App\Exports;

use App\Models\Paiement;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaiementsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly ?string $dateDebut = null,
        private readonly ?string $dateFin = null,
        private readonly ?string $operateur = null,
    ) {}

    public function query()
    {
        $query = Paiement::with(['ordreMission', 'agent']);

        if ($this->dateDebut) {
            $query->where('created_at', '>=', $this->dateDebut);
        }
        if ($this->dateFin) {
            $query->where('created_at', '<=', $this->dateFin . ' 23:59:59');
        }
        if ($this->operateur) {
            $query->where('operateur', $this->operateur);
        }

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Reference OM',
            'Agent',
            'Matricule',
            'Operateur',
            'Numero destinataire',
            'Montant (FCFA)',
            'Statut',
            'Reference transaction',
            'Date virement',
            'Date creation',
        ];
    }

    public function map($paiement): array
    {
        return [
            $paiement->ordreMission?->numero ?? '',
            $paiement->agent ? "{$paiement->agent->prenom} {$paiement->agent->nom}" : '',
            $paiement->agent?->matricule ?? '',
            $paiement->operateur,
            $paiement->numero_destinataire,
            $paiement->montant,
            $paiement->statut,
            $paiement->reference_transaction ?? '-',
            $paiement->date_virement?->format('d/m/Y H:i') ?? '-',
            $paiement->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Paiements';
    }
}
