<?php

namespace App\Exports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly ?string $service = null,
        private readonly ?bool $actif = null,
    ) {}

    public function query()
    {
        $query = Agent::query();

        if ($this->service) {
            $query->where('service', $this->service);
        }
        if ($this->actif !== null) {
            $query->where('actif', $this->actif);
        }

        return $query->orderBy('nom');
    }

    public function headings(): array
    {
        return [
            'Matricule',
            'Nom',
            'Prenom',
            'Email',
            'Telephone',
            'Service',
            'Categorie',
            'Numero Moov',
            'Numero MTN',
            'Statut',
        ];
    }

    public function map($agent): array
    {
        return [
            $agent->matricule,
            $agent->nom,
            $agent->prenom,
            $agent->email,
            $agent->telephone,
            $agent->service,
            $agent->categorie,
            $agent->numero_moov ?? '-',
            $agent->numero_mtn ?? '-',
            $agent->actif ? 'Actif' : 'Inactif',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D4A843'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Personnel';
    }
}
