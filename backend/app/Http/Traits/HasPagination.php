<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait HasPagination
{
    protected int $defaultPerPage = 15;

    protected int $minPerPage = 5;

    protected int $maxPerPage = 100;

    /** Taille de page bornée [min, max] ; `$defaut` remplace le défaut de l'écran. */
    protected function getPerPage(Request $request, ?int $defaut = null): int
    {
        $perPage = $request->integer('per_page', $defaut ?? $this->defaultPerPage);

        return max($this->minPerPage, min($this->maxPerPage, $perPage));
    }
}
