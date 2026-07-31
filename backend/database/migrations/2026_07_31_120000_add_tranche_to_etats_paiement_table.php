<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'état de paiement devient PAR TRANCHE : une mission porte désormais DEUX
 * états — l'état des AVANCES puis, une fois celui-ci validé et payé, l'état
 * des RELIQUATS. Chacun suit le circuit complet (contrôle -> DAF -> DG) et
 * ouvre la génération des fichiers de paiement de sa tranche.
 *
 * L'unicité passe donc de (mission_id) à (mission_id, tranche).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            // AVANCE par défaut : un état existant couvre le premier document
            // du circuit. (Base fraîche acceptée : pas de migration de données.)
            $table->enum('tranche', ['AVANCE', 'RELIQUAT'])->default('AVANCE')->after('mission_id');
        });

        Schema::table('etats_paiement', function (Blueprint $table) {
            // Créé AVANT la suppression de l'ancien index : la clé étrangère sur
            // mission_id garde un index utilisable (préfixe gauche du composite).
            $table->unique(['mission_id', 'tranche']);
            $table->dropUnique(['mission_id']);
        });
    }

    public function down(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            $table->unique(['mission_id']);
            $table->dropUnique(['mission_id', 'tranche']);
        });

        Schema::table('etats_paiement', function (Blueprint $table) {
            $table->dropColumn('tranche');
        });
    }
};
