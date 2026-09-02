<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel générique d'un état de paiement (en-têtes + lignes déjà préparés).
 */
class EtatExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int,string>  $entetes
     * @param  array<int,array<int,mixed>>  $lignes
     */
    public function __construct(
        private readonly array $entetes,
        private readonly array $lignes,
        private readonly string $titre = 'Etat',
    ) {}

    public function headings(): array
    {
        return $this->entetes;
    }

    public function array(): array
    {
        return $this->lignes;
    }

    public function title(): string
    {
        return $this->titre;
    }
}
