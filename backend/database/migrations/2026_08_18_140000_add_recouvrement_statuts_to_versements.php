<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statuts de RÉGULARISATION d'un versement (trop-perçu après amendement) :
 *  - A_RECOUVRER : reliquat négatif, montant à récupérer (retenue sur salaire) ;
 *  - RECOUVRE    : la régularisation a été appliquée (constat manuel).
 * Un versement A_RECOUVRER/RECOUVRE ne part jamais dans un fichier de paiement.
 * Cf. docs/amendement-om.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versements', function (Blueprint $table) {
            $table->enum('statut', ['EN_ATTENTE', 'GENERE', 'CONFIRME', 'ANNULE', 'A_RECOUVRER', 'RECOUVRE'])
                ->default('EN_ATTENTE')->change();
        });
    }

    public function down(): void
    {
        Schema::table('versements', function (Blueprint $table) {
            $table->enum('statut', ['EN_ATTENTE', 'GENERE', 'CONFIRME', 'ANNULE'])
                ->default('EN_ATTENTE')->change();
        });
    }
};
