<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la répartition détaillée du per diem (état ANaTT) et le montant de
 * transport aux OM existants.
 *
 * Migration ADDITIVE et idempotente : sur une base déjà en production, elle
 * ajoute les colonnes ; sur une installation fraîche (où la migration `create`
 * les pose déjà), les gardes hasColumn la rendent sans effet — aucun doublon,
 * aucune erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            if (! Schema::hasColumn('ordre_missions', 'lignes_per_diem')) {
                $table->json('lignes_per_diem')->nullable()->after('nb_jours');
            }
            if (! Schema::hasColumn('ordre_missions', 'montant_transport')) {
                $table->unsignedBigInteger('montant_transport')->default(0)->after('lignes_per_diem');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            foreach (['montant_transport', 'lignes_per_diem'] as $col) {
                if (Schema::hasColumn('ordre_missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
