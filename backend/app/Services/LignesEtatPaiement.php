<?php

namespace App\Services;

use App\Models\OrdreMission;

/**
 * Lignes du tableau de l'état de paiement, telles qu'elles sont imprimées.
 *
 * Cette construction sert deux fois, et c'est le point : le récapitulatif
 * l'exécute pour FIGER le tableau dans l'état (colonne `lignes`), et le
 * générateur de PDF ne fait ensuite que relire cet instantané. Le document
 * signé ne peut donc plus diverger de ce qui a été présenté au signataire.
 *
 * Elle reste appelable à la volée pour les états antérieurs à l'instantané,
 * qui n'en portent pas.
 */
class LignesEtatPaiement
{
    /** Libellés de la colonne MOTIF de l'état officiel. */
    private const TYPES = [
        'UN_REPAS' => 'Mission à 1 repas',
        'DEUX_REPAS' => 'Mission à 2 repas',
        'JOURNEE_COMPLETE' => 'Mission complète',
        'LOGE_NOURRI' => 'Logé + nourri',
        'LOGE_SEUL' => 'Logé seul',
        'NOURRI_SEUL' => 'Nourri seul',
    ];

    /**
     * Le tableau des bénéficiaires de la mission, prêt à l'impression.
     *
     * @return list<array<string,mixed>>
     */
    public static function pourMission(int $missionId): array
    {
        $ordres = OrdreMission::with('agent')
            ->where('mission_id', $missionId)
            ->whereNotIn('statut', ['REJETE', 'ANNULE'])
            ->orderBy('id')
            ->get();

        return $ordres->map(fn (OrdreMission $om) => self::ligne($om))->values()->all();
    }

    /** @return array<string,mixed> */
    private static function ligne(OrdreMission $om): array
    {
        $agent = $om->agent;

        return [
            'numero' => $om->reference_semo,
            // Un agent supprimé de la base ne doit pas faire échouer l'édition
            // de l'état : la ligne reste, l'identité manquante est signalée.
            'beneficiaire' => $agent ? trim("{$agent->nom} {$agent->prenoms}") : '—',
            'structure' => 'ANaTT',
            'grade_indice' => self::grade($om->categorie, $agent?->indice),
            'detail' => self::detail($om),
            'total' => self::fmt((int) $om->montant_total),
            'avance' => self::fmt((int) $om->avance),
            'reliquat' => self::fmt((int) $om->reliquat),
            'contact' => self::telephone($agent?->telephone),
            // Valeurs brutes : elles permettent de recouper l'instantané avec
            // les totaux scellés sans avoir à reparser les chaînes formatées.
            'montant_total_brut' => (int) $om->montant_total,
            'avance_brut' => (int) $om->avance,
            'reliquat_brut' => (int) $om->reliquat,
        ];
    }

    /**
     * Sous-lignes par type de prise en charge (colonnes MOTIF / TAUX-JOUR /
     * NBRE DE JOURS / TOTAL PARTIEL).
     *
     * @return list<array<string,mixed>>
     */
    private static function detail(OrdreMission $om): array
    {
        $detail = collect($om->lignes_per_diem ?? [])->map(fn (array $l) => [
            'motif' => self::TYPES[$l['type']] ?? $l['type'],
            'taux' => self::fmt((int) $l['taux']),
            'nb_jours' => (int) $l['nb_jours'],
            'partiel' => self::fmt((int) $l['montant']),
        ])->values()->all();

        if ($detail !== []) {
            return $detail;
        }

        // Per diem non détaillé : une seule sous-ligne, taux reconstitué.
        return [[
            'motif' => self::TYPES[$om->type_prise_en_charge] ?? $om->type_prise_en_charge,
            'taux' => self::fmt($om->nb_jours > 0 ? intdiv((int) $om->montant_total, $om->nb_jours) : 0),
            'nb_jours' => (int) $om->nb_jours,
            'partiel' => self::fmt((int) $om->montant_total),
        ]];
    }

    /** Colonne GRADE/INDICE de l'état : « ≥ 800 » / « 400-799 » / « < 400 ». */
    private static function grade(string $categorie, ?int $indice): string
    {
        return match ($categorie) {
            'ORDRE_1' => '≥ 800',
            'ORDRE_2' => '400-799',
            'ORDRE_3' => '< 400',
            default => $indice !== null ? (string) $indice : '—',
        };
    }

    /** Téléphone en groupes de 2, comme sur l'état officiel (01 96 30 24 90). */
    private static function telephone(?string $numero): string
    {
        if (empty($numero)) {
            return '—';
        }

        return trim(implode(' ', str_split((string) preg_replace('/\D/', '', $numero), 2)));
    }

    /** Un montant nul s'imprime « - », comme sur l'état de référence. */
    public static function fmt(int $n): string
    {
        return $n === 0 ? '-' : number_format($n, 0, ',', ' ');
    }
}
