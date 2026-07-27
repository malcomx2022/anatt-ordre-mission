<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Première étape du circuit : la vérification par le CONTROLEUR, en amont du
 * visa DAF puis de la validation finale du DG.
 *
 *   IMPORTE ──vérifier (CONTROLEUR)──▶ VERIFIE ──viser (DAF)──▶ VISA_DAF
 *           ──approuver (DG)──▶ VALIDE   (verrouillé, payable)
 *
 * Le circuit complet reste derrière le flag workflow.double_validation : tant
 * qu'il est inactif, aucun OM n'atteint VERIFIE et ces colonnes restent nulles.
 */
return new class extends Migration
{
    private const STATUTS_CIBLE = [
        'BROUILLON', 'IMPORTE', 'VERIFIE', 'VISA_DAF', 'EN_ATTENTE',
        'VALIDE', 'REJETE', 'SOLDE', 'ANNULE',
    ];

    private const STATUTS_ORIGINE = [
        'BROUILLON', 'IMPORTE', 'VISA_DAF', 'EN_ATTENTE',
        'VALIDE', 'REJETE', 'SOLDE', 'ANNULE',
    ];

    public function up(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->enum('statut', self::STATUTS_CIBLE)->default('IMPORTE')->change();
        });

        Schema::table('ordre_missions', function (Blueprint $table) {
            // Vérification de conformité par le CONTROLEUR (étape 1 sur 3).
            // Mêmes instantanés que les deux signatures suivantes : le document
            // ne doit pas être réécrit par un renommage ultérieur du vérificateur.
            $table->foreignId('verifie_par')->nullable()->after('commentaire_rejet')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_le')->nullable()->after('verifie_par');
            $table->string('verifie_nom')->nullable()->after('verifie_le');
            $table->string('verifie_fonction')->nullable()->after('verifie_nom');
            $table->text('verifie_commentaire')->nullable()->after('verifie_fonction');
            $table->string('signature_verif_hash', 64)->nullable()->after('verifie_commentaire');
        });
    }

    public function down(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verifie_par');
            $table->dropColumn([
                'verifie_le', 'verifie_nom', 'verifie_fonction',
                'verifie_commentaire', 'signature_verif_hash',
            ]);
        });

        // Les OM arrêtés à la vérification retournent à IMPORTE avant de
        // restreindre l'ENUM (le contrôle est perdu, comportement attendu d'un
        // retour en arrière sur le circuit).
        DB::table('ordre_missions')->where('statut', 'VERIFIE')->update(['statut' => 'IMPORTE']);

        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->enum('statut', self::STATUTS_ORIGINE)->default('IMPORTE')->change();
        });
    }
};
