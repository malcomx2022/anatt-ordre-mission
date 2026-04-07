<?php

namespace App\Services;

use App\Models\TauxPerDiem;
use Illuminate\Support\Collection;

class TauxService
{
    public function listerActifs(): Collection
    {
        return TauxPerDiem::where('actif', true)
            ->orderBy('categorie')
            ->orderBy('destination_type')
            ->get();
    }

    public function creer(array $data): TauxPerDiem
    {
        return TauxPerDiem::create([
            ...$data,
            'actif' => true,
        ]);
    }

    public function modifier(TauxPerDiem $taux, array $data): TauxPerDiem
    {
        $taux->update($data);

        return $taux->fresh();
    }
}
