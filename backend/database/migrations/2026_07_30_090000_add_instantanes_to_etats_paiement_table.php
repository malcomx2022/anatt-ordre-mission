<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fige dans l'état ce que le document imprime, et non seulement ses totaux.
 *
 * Deux instantanés manquaient à l'état de paiement :
 *
 *  - le PARAPHE de chaque signataire. Le nom et la fonction étaient figés au
 *    moment de la signature, mais l'image était relue dans les Paramètres à
 *    chaque édition du PDF : un changement de titulaire réimprimait des états
 *    déjà validés avec le paraphe du nouveau DAF sous le nom de l'ancien. On
 *    archive désormais une copie de l'image au moment où la personne signe.
 *
 *  - les LIGNES du tableau. Les totaux étaient figés au récapitulatif, mais le
 *    corps du tableau était recalculé depuis les OM courants : rien ne
 *    garantissait qu'il totalise encore le montant scellé. L'instantané rend
 *    l'invariant vrai par construction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            // Copie archivée du paraphe, prise au moment de la signature.
            // Vit sous storage/app/signatures/etats (disque privé).
            $table->string('signature_controle_image')->nullable()->after('signature_controle_hash');
            $table->string('signature_daf_image')->nullable()->after('signature_daf_hash');
            $table->string('signature_dg_image')->nullable()->after('signature_dg_hash');

            // Lignes du tableau telles que présentées au signataire.
            // Nul sur les états antérieurs : le PDF retombe alors sur le calcul.
            $table->json('lignes')->nullable()->after('montant_reliquat');
        });
    }

    public function down(): void
    {
        Schema::table('etats_paiement', function (Blueprint $table) {
            $table->dropColumn([
                'signature_controle_image',
                'signature_daf_image',
                'signature_dg_image',
                'lignes',
            ]);
        });
    }
};
