<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Décision D2 : l'application passe de UTC à l'heure du Bénin (Africa/Porto-Novo,
 * UTC+1 toute l'année). Laravel enregistre les date-heures sans fuseau, à l'heure de
 * l'application : les valeurs écrites jusqu'ici sont en UTC et seraient relues une
 * heure trop tôt. On les décale donc du décalage du fuseau configuré, pour que chaque
 * horodatage désigne le même instant qu'avant (scellés HMAC inchangés : ils portent
 * l'instant en UTC, toISOString).
 *
 * Portée : toutes les colonnes DATETIME / TIMESTAMP de la base. Les dates seules
 * (DATE : départ, retour…) et les entiers Unix (cache, sessions, jobs) ne bougent pas,
 * pas plus que les dates recopiées dans du JSON (journal d'audit, lignes d'état).
 * Fuseau configuré en UTC (APP_TIMEZONE=UTC) : décalage nul, rien n'est modifié.
 *
 * À exécuter juste après le dépôt du code (mode maintenance) : une écriture faite
 * entre les deux serait déjà à l'heure du Bénin et décalée une seconde fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->decaler($this->decalageSecondes());
    }

    public function down(): void
    {
        $this->decaler(-$this->decalageSecondes());
    }

    private function decalageSecondes(): int
    {
        return (new DateTimeZone((string) config('app.timezone')))
            ->getOffset(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    }

    private function decaler(int $secondes): void
    {
        if ($secondes === 0) {
            return;
        }

        $sqlite = DB::connection()->getDriverName() === 'sqlite';

        foreach (Schema::getTables() as $table) {
            $nom = $table['name'];
            if ($nom === 'migrations' || str_starts_with($nom, 'sqlite_')) {
                continue;
            }

            foreach (Schema::getColumns($nom) as $colonne) {
                if (! in_array(strtolower($colonne['type_name']), ['datetime', 'timestamp'], true)) {
                    continue;
                }

                $c = DB::getQueryGrammar()->wrap($colonne['name']);
                $expression = $sqlite
                    ? "datetime({$c}, '".sprintf('%+d', $secondes)." seconds')"
                    : "DATE_ADD({$c}, INTERVAL {$secondes} SECOND)";

                DB::table($nom)->whereNotNull($colonne['name'])
                    ->update([$colonne['name'] => DB::raw($expression)]);
            }
        }
    }
};
