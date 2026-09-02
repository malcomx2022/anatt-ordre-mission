<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;

trait HasPagination
{
    protected int $defaultPerPage = 15;

    protected int $minPerPage = 5;

    protected int $maxPerPage = 100;

    protected function getPerPage(Request $request): int
    {
        $perPage = $request->integer('per_page', $this->defaultPerPage);

        return max($this->minPerPage, min($this->maxPerPage, $perPage));
    }
}
