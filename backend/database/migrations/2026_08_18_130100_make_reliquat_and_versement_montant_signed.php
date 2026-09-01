<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passe en entiers SIGNÉS les colonnes qui peuvent porter un trop-perçu :
 * après amendement d'un OM dont l'avance est déjà engagée, le reliquat vaut
 * `montant_total − avance_engagee` et peut être NÉGATIF. `avance`, `avance_engagee`
 * et `montant_total` restent non signés (toujours ≥ 0). Cf. docs/amendement-om.md.
 *
 * Un versement de reliquat négatif est exclu des fichiers de paiement
 * (Versement::scopeEligiblesLot filtre montant > 0) : il relève d'une régularisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->bigInteger('reliquat')->default(0)->change();
        });
        Schema::table('versements', function (Blueprint $table) {
            $table->bigInteger('montant')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->unsignedBigInteger('reliquat')->default(0)->change();
        });
        Schema::table('versements', function (Blueprint $table) {
            $table->unsignedBigInteger('montant')->change();
        });
    }
};
