<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lot complémentaire : un lot CONFIRME ne se régénère jamais (anti-double-
 * paiement), mais un versement devenu payable APRÈS cette confirmation (agent
 * passé de MOOV à MTN, OM validé tardivement, reliquat redevenu positif) doit
 * pouvoir partir dans un nouveau fichier. La clé du lot porte donc un numéro
 * de séquence : 1 = lot initial, 2.. = lots complémentaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots_paiement', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence')->default(1)->after('operateur');
        });

        Schema::table('lots_paiement', function (Blueprint $table) {
            // La nouvelle clé est créée AVANT de supprimer l'ancienne : sous MySQL,
            // la clé étrangère mission_id exige un index qui commence par elle.
            $table->unique(['mission_id', 'tranche', 'operateur', 'sequence'], 'lots_unique_cle_seq');
        });

        Schema::table('lots_paiement', function (Blueprint $table) {
            $table->dropUnique('lots_unique_cle');
        });
    }

    public function down(): void
    {
        Schema::table('lots_paiement', function (Blueprint $table) {
            $table->unique(['mission_id', 'tranche', 'operateur'], 'lots_unique_cle');
        });

        Schema::table('lots_paiement', function (Blueprint $table) {
            $table->dropUnique('lots_unique_cle_seq');
            $table->dropColumn('sequence');
        });
    }
};
