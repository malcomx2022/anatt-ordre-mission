<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $zones = [
            [
                'code' => 'INTERIEUR',
                'libelle' => 'Intérieur (Bénin)',
                'portee' => 'INTERIEUR',
            ],
            [
                'code' => 'EXT_ZONE_I',
                'libelle' => 'Europe, Amérique, Éthiopie, Japon, Koweït, Abuja, Guinée Équatoriale, Libreville, Angola',
                'portee' => 'EXTERIEUR',
            ],
            [
                'code' => 'EXT_ZONE_II',
                'libelle' => 'Zone Franc, RDC, Ghana, Nigeria (sauf Abuja, Guinée Éq., Libreville)',
                'portee' => 'EXTERIEUR',
            ],
            [
                'code' => 'EXT_ZONE_III',
                'libelle' => 'Afrique hors zone franc, Asie, Océanie (sauf Éthiopie, Japon, Koweït, RDC, Ghana, Nigeria, Angola, Qatar)',
                'portee' => 'EXTERIEUR',
            ],
        ];

        foreach ($zones as $zone) {
            DB::table('zones_geographiques')->updateOrInsert(
                ['code' => $zone['code']],
                array_merge($zone, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
