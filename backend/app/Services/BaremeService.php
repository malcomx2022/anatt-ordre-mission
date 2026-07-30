<?php

namespace App\Services;

use App\Models\Bareme;
use App\Models\OrdreMission;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BaremeService
{
    /** Liste du barème, filtrable par catégorie / zone / type. */
    public function lister(array $filters = []): Collection
    {
        $query = Bareme::query();

        if ($categorie = ($filters['categorie'] ?? null)) {
            $query->where('categorie', $categorie);
        }

        if ($zone = ($filters['zone_code'] ?? null)) {
            $query->where('zone_code', $zone);
        }

        if ($type = ($filters['type_prise_en_charge'] ?? null)) {
            $query->where('type_prise_en_charge', $type);
        }

        return $query
            ->orderBy('zone_code')
            ->orderBy('categorie')
            ->orderBy('type_prise_en_charge')
            ->get();
    }

    public function creer(array $data): Bareme
    {
        return Bareme::create($data);
    }

    public function modifier(Bareme $bareme, array $data): Bareme
    {
        $bareme->update($data);

        return $bareme->fresh();
    }

    public function supprimer(Bareme $bareme): void
    {
        $utilise = OrdreMission::where('categorie', $bareme->categorie)
            ->where('zone_code', $bareme->zone_code)
            ->where('type_prise_en_charge', $bareme->type_prise_en_charge)
            ->whereNotIn('statut', ['SOLDE', 'REJETE', 'ANNULE'])
            ->exists();

        if ($utilise) {
            throw ValidationException::withMessages([
                'bareme' => ['Ce barème est utilisé par des missions en cours : il ne peut pas être supprimé.'],
            ]);
        }

        $bareme->delete();
    }
}
