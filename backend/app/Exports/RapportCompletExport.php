<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RapportCompletExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ?string $dateDebut = null,
        private readonly ?string $dateFin = null,
    ) {}

    public function sheets(): array
    {
        return [
            new MissionsExport($this->dateDebut, $this->dateFin),
            new PaiementsExport($this->dateDebut, $this->dateFin),
            new AgentsExport(),
        ];
    }
}
