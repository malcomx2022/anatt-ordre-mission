<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socle du circuit de double validation DAF -> DG (aucun changement de comportement).
 *
 * Ajoute le statut intermédiaire VISA_DAF et les colonnes de traçabilité des deux
 * signatures. Tant que le circuit double n'est pas activé
 * (App\Support\Workflow::doubleValidationActive()), aucun OM n'atteint VISA_DAF et
 * ces colonnes restent nulles.
 *
 * VALIDE conserve strictement son sens actuel : double signature obtenue = payable.
 * Versement::scopeEligiblesLot() n'est pas touché, le module paiement est donc inchangé.
 */
return new class extends Migration
{
    /** Liste complète des statuts APRÈS ajout (l'ENUM se redéclare en entier). */
    private const STATUTS_CIBLE = [
        'BROUILLON', 'IMPORTE', 'VISA_DAF', 'EN_ATTENTE', 'VALIDE', 'REJETE', 'SOLDE', 'ANNULE',
    ];

    private const STATUTS_ORIGINE = [
        'BROUILLON', 'IMPORTE', 'EN_ATTENTE', 'VALIDE', 'REJETE', 'SOLDE', 'ANNULE',
    ];

    public function up(): void
    {
        // Sur SQLite, enum() se matérialise en `varchar check (... in (...))` :
        // la contrainte est réellement appliquée, le change() est donc indispensable
        // sur les deux moteurs (MySQL en production, SQLite en test).
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->enum('statut', self::STATUTS_CIBLE)->default('IMPORTE')->change();
        });

        Schema::table('ordre_missions', function (Blueprint $table) {
            // ── Visa DAF (1re signature) ──
            $table->foreignId('visa_daf_par')->nullable()->after('commentaire_rejet')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('visa_daf_le')->nullable()->after('visa_daf_par');
            // Instantanés : un document signé ne doit pas être réécrit par un
            // renommage, une mutation ou une suppression ultérieure du signataire.
            $table->string('visa_daf_nom')->nullable()->after('visa_daf_le');
            $table->string('visa_daf_fonction')->nullable()->after('visa_daf_nom');
            $table->text('visa_daf_commentaire')->nullable()->after('visa_daf_fonction');
            $table->string('signature_daf_hash', 64)->nullable()->after('visa_daf_commentaire');

            // ── Approbation DG (2e et dernière signature) ──
            $table->foreignId('valide_dg_par')->nullable()->after('signature_daf_hash')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('valide_dg_le')->nullable()->after('valide_dg_par');
            $table->string('valide_dg_nom')->nullable()->after('valide_dg_le');
            $table->string('valide_dg_fonction')->nullable()->after('valide_dg_nom');
            $table->string('signature_dg_hash', 64)->nullable()->after('valide_dg_fonction');
        });
    }

    public function down(): void
    {
        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visa_daf_par');
            $table->dropConstrainedForeignId('valide_dg_par');
            $table->dropColumn([
                'visa_daf_le', 'visa_daf_nom', 'visa_daf_fonction', 'visa_daf_commentaire',
                'signature_daf_hash',
                'valide_dg_le', 'valide_dg_nom', 'valide_dg_fonction', 'signature_dg_hash',
            ]);
        });

        // Aucun statut hors de l'ENUM d'origine ne doit subsister avant de le restreindre :
        // les OM en cours de visa retournent à IMPORTE (le visa est perdu, ce qui est le
        // comportement attendu d'un retour au circuit simple).
        DB::table('ordre_missions')->where('statut', 'VISA_DAF')->update(['statut' => 'IMPORTE']);

        Schema::table('ordre_missions', function (Blueprint $table) {
            $table->enum('statut', self::STATUTS_ORIGINE)->default('IMPORTE')->change();
        });
    }
};
