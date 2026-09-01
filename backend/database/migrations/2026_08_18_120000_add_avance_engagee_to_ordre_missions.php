<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avance ENGAGÉE : montant d'avance figé au moment où l'état des avances passe
 * VALIDÉ. Tant qu'il est null, le comportement reste celui d'avant (répartition
 * partAvance 70/30 recalculée). Une fois posé, l'avance ne se recalcule plus :
 * un amendement ultérieur de l'OM n'ajuste que le reliquat
 * (reliquat = montant_total − avance_engagee, éventuellement négatif = trop-perçu).
 *
 * Cf. docs/amendement-om.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->unsignedInteger('avance_engagee')->nullable()->after('reliquat');
        });
    }

    public function down(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->dropColumn('avance_engagee');
        });
    }
};
