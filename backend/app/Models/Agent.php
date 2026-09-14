<?php

namespace App\Models;

use App\Support\CategorieIndice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    /** Diviseur officiel de la formule de secours indice = salaire_base × 12 / 3097. */
    public const DIVISEUR_INDICE = 3097;

    protected $fillable = [
        'nom',
        'prenoms',
        'matricule',
        'ifu',
        'grade',
        'indice',
        'salaire_brut',
        'categorie',
        'telephone',
        'operateur',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'indice' => 'integer',
            'salaire_brut' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function ordresMission()
    {
        return $this->hasMany(OrdreMission::class);
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->nom} {$this->prenoms}");
    }

    /**
     * Détermine la catégorie d'indice ANaTT à partir de l'indice OU du salaire brut.
     *
     * Concordance officielle :
     *   INDICE_SUP_800 : indice ≥ 800   | salaire brut ≥ 372 000
     *   INDICE_400_799 : indice 400-799 | salaire brut 186 000-372 000
     *   INDICE_INF_400 : indice < 400   | salaire brut < 186 000
     *
     * Priorité à l'indice ; à défaut, on applique les seuils directs du salaire brut
     * (concordants avec ceux de l'indice). Les seuils eux-mêmes vivent dans
     * App\Support\CategorieIndice, source de vérité unique côté serveur.
     */
    public static function determinerCategorie(?int $indice, ?int $salaireBrut): ?string
    {
        return CategorieIndice::determiner($indice, $salaireBrut);
    }

    /**
     * Formule de secours : indice = salaire_base × 12 / 3097.
     * Conservée pour reconstituer un indice quand seul le salaire de base est connu ;
     * non utilisée par determinerCategorie() (qui s'appuie sur les seuils concordants).
     */
    public static function indiceDepuisSalaire(int $salaireBase): int
    {
        return (int) round($salaireBase * 12 / self::DIVISEUR_INDICE);
    }
}
