<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace le vocabulaire ORDRE_1/2/3 par la catégorie d'indice nominative :
 *   ORDRE_1 → INDICE_SUP_800  (indice ≥ 800)
 *   ORDRE_2 → INDICE_400_799  (indice 400-799)
 *   ORDRE_3 → INDICE_INF_400  (indice < 400)
 *
 * L'état de paiement officiel imprime déjà l'indice (« ≥ 800 ») ; cette
 * migration aligne le reste de l'application sur ce vocabulaire.
 *
 * ⚠️ Déroulé en TROIS temps, indispensable sur MySQL : un ALTER TABLE qui
 * réduirait directement l'enum aux nouvelles valeurs tronquerait toutes les
 * lignes existantes. On élargit donc l'enum aux six valeurs, on traduit les
 * données, puis on referme sur les trois nouvelles. Sur SQLite (tests), le
 * `change()` recrée la table : le déroulé reste correct.
 *
 * Les trois colonnes migrent dans la MÊME transaction : barème et OM doivent
 * basculer ensemble, faute de quoi Bareme::taux() ne retrouverait plus ses
 * taux et le calcul du per diem échouerait.
 *
 * Non touché volontairement : `audit_logs`, dont les payloads historiques
 * conservent le vocabulaire en vigueur au moment de l'action — un journal
 * d'audit ne se réécrit pas.
 */
return new class extends Migration
{
    /** Colonnes portant une catégorie d'indice. */
    private const COLONNES = [
        'agents' => 'categorie',
        'baremes' => 'categorie',
        'ordre_missions' => 'categorie',
    ];

    private const ANCIENS = ['ORDRE_1', 'ORDRE_2', 'ORDRE_3'];

    private const NOUVEAUX = ['INDICE_SUP_800', 'INDICE_400_799', 'INDICE_INF_400'];

    public function up(): void
    {
        $this->basculer(array_combine(self::ANCIENS, self::NOUVEAUX));
    }

    public function down(): void
    {
        $this->basculer(array_combine(self::NOUVEAUX, self::ANCIENS));
    }

    /**
     * @param  array<string,string>  $traduction  ancien code → nouveau code
     */
    private function basculer(array $traduction): void
    {
        $toutes = [...array_keys($traduction), ...array_values($traduction)];

        // 1. Élargir : les deux vocabulaires cohabitent le temps de l'UPDATE.
        $this->redefinirEnum($toutes);

        // 2. Traduire les données existantes.
        DB::transaction(function () use ($traduction) {
            foreach (self::COLONNES as $table => $colonne) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach ($traduction as $avant => $apres) {
                    DB::table($table)->where($colonne, $avant)->update([$colonne => $apres]);
                }
            }
        });

        // 3. Refermer sur le seul vocabulaire cible.
        $this->redefinirEnum(array_values($traduction));
    }

    /**
     * @param  list<string>  $valeurs
     */
    private function redefinirEnum(array $valeurs): void
    {
        foreach (self::COLONNES as $table => $colonne) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($colonne, $valeurs) {
                $blueprint->enum($colonne, $valeurs)->change();
            });
        }
    }
};
