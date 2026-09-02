<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bareme extends Model
{
    use HasFactory;

    protected $table = 'baremes';

    /** Types de prise en charge autorisés selon la portée de la zone. */
    public const TYPES_INTERIEUR = ['UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE'];

    public const TYPES_EXTERIEUR = ['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL'];

    protected $fillable = [
        'categorie',
        'zone_code',
        'type_prise_en_charge',
        'taux_journalier',
    ];

    protected function casts(): array
    {
        return [
            'taux_journalier' => 'integer',
        ];
    }

    /** Taux journalier officiel (FCFA) pour un triplet catégorie × zone × type, ou null si introuvable. */
    public static function taux(string $categorie, string $zoneCode, string $type): ?int
    {
        $taux = static::query()
            ->where('categorie', $categorie)
            ->where('zone_code', $zoneCode)
            ->where('type_prise_en_charge', $type)
            ->value('taux_journalier');

        return $taux !== null ? (int) $taux : null;
    }

    /** Types de prise en charge autorisés pour une zone donnée. */
    public static function typesAutorises(string $zoneCode): array
    {
        return $zoneCode === 'INTERIEUR' ? self::TYPES_INTERIEUR : self::TYPES_EXTERIEUR;
    }

    /** Le type de prise en charge est-il compatible avec la portée de la zone ? */
    public static function typeCompatible(string $zoneCode, string $type): bool
    {
        return in_array($type, self::typesAutorises($zoneCode), true);
    }
}
