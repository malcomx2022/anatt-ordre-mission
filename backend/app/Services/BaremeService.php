<?php

namespace App\Services;

use App\Models\Bareme;
use App\Models\OrdreMission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($data) {
            $bareme = Bareme::create($data);
            $this->synchroniserJumeau($bareme);

            return $bareme;
        });
    }

    public function modifier(Bareme $bareme, array $data): Bareme
    {
        return DB::transaction(function () use ($bareme, $data) {
            $bareme->update($data);
            $bareme = $bareme->fresh();
            $this->synchroniserJumeau($bareme);

            return $bareme;
        });
    }

    public function supprimer(Bareme $bareme): void
    {
        $zones = array_values(array_filter([$bareme->zone_code, $this->zoneJumelle($bareme->zone_code)]));

        // Garde-fou étendu aux DEUX zones jumelles : on ne supprime pas un barème
        // (ni son jumeau) tant qu'une mission en cours s'y appuie.
        $utilise = OrdreMission::where('categorie', $bareme->categorie)
            ->whereIn('zone_code', $zones)
            ->where('type_prise_en_charge', $bareme->type_prise_en_charge)
            ->whereNotIn('statut', ['SOLDE', 'REJETE', 'ANNULE'])
            ->exists();

        if ($utilise) {
            throw ValidationException::withMessages([
                'bareme' => ['Ce barème est utilisé par des missions en cours : il ne peut pas être supprimé.'],
            ]);
        }

        // Suppression conjointe : EXT_ZONE_II et EXT_ZONE_III forment une seule
        // entrée logique (mêmes taux par règle métier).
        DB::transaction(function () use ($bareme, $zones) {
            Bareme::where('categorie', $bareme->categorie)
                ->whereIn('zone_code', $zones)
                ->where('type_prise_en_charge', $bareme->type_prise_en_charge)
                ->delete();
        });
    }

    /** Zone jumelle partageant les mêmes taux (règle métier) : EXT_ZONE_II ↔ EXT_ZONE_III. */
    private function zoneJumelle(string $zone): ?string
    {
        return match ($zone) {
            'EXT_ZONE_II' => 'EXT_ZONE_III',
            'EXT_ZONE_III' => 'EXT_ZONE_II',
            default => null,
        };
    }

    /**
     * Réplique le taux du barème sur sa zone jumelle (EXT_ZONE_II ↔ EXT_ZONE_III)
     * afin de préserver l'invariant « II = III ». Idempotent (updateOrCreate) ;
     * sans effet pour les zones sans jumelle (INTERIEUR, EXT_ZONE_I).
     */
    private function synchroniserJumeau(Bareme $bareme): void
    {
        $jumelle = $this->zoneJumelle($bareme->zone_code);

        if ($jumelle === null) {
            return;
        }

        Bareme::updateOrCreate(
            [
                'categorie' => $bareme->categorie,
                'zone_code' => $jumelle,
                'type_prise_en_charge' => $bareme->type_prise_en_charge,
            ],
            ['taux_journalier' => $bareme->taux_journalier],
        );
    }
}
