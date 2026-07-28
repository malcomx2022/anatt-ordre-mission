<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État de paiement d'une mission — l'objet que l'on valide réellement.
 *
 * Le circuit ANaTT ne signe pas les OM un par un : il signe UN état par mission,
 * qui récapitule tous les bénéficiaires avec leurs colonnes AVANCE et RELIQUAT
 * (cf. l'état de référence N°0069/ANaTT/DAF/C-SFC).
 *
 *   récapitulatif ──▶ BROUILLON ──contrôle──▶ CONTROLE ──DAF──▶ VISA_DAF
 *                 ──DG──▶ VALIDE   (les fichiers de paiement deviennent générables)
 *
 * La validation de l'état propage le statut à tous ses OM : le périmètre payable
 * (Versement::scopeEligiblesLot, qui exige un OM VALIDE) reste inchangé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etats_paiement', function (Blueprint $table) {
            $table->id();

            // Un seul état par mission : c'est le document de la mission.
            $table->foreignId('mission_id')->unique()->constrained('missions')->cascadeOnDelete();

            // Numérotation MANUELLE (ex. « 0069/ANaTT/DAF/C-SFC ») : elle suit le
            // registre papier de l'agence, l'application ne l'invente pas.
            // Unique lorsqu'elle est renseignée, nulle tant que l'état est un brouillon.
            $table->string('numero')->nullable()->unique();

            $table->enum('statut', ['BROUILLON', 'CONTROLE', 'VISA_DAF', 'VALIDE', 'REJETE'])
                ->default('BROUILLON');

            // Instantané financier figé au récapitulatif : le document signé doit
            // rester fidèle aux montants présentés au signataire.
            $table->unsignedInteger('nb_beneficiaires')->default(0);
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->unsignedBigInteger('montant_avance')->default(0);
            $table->unsignedBigInteger('montant_reliquat')->default(0);

            // ── Étape 1 : contrôle de conformité ──
            $table->foreignId('controle_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('controle_le')->nullable();
            $table->string('controle_nom')->nullable();
            $table->string('controle_fonction')->nullable();
            $table->string('signature_controle_hash', 64)->nullable();

            // ── Étape 2 : « Vu » du DAF ──
            $table->foreignId('visa_daf_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visa_daf_le')->nullable();
            $table->string('visa_daf_nom')->nullable();
            $table->string('visa_daf_fonction')->nullable();
            $table->string('signature_daf_hash', 64)->nullable();

            // ── Étape 3 : validation finale du Directeur Général ──
            $table->foreignId('valide_dg_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_dg_le')->nullable();
            $table->string('valide_dg_nom')->nullable();
            $table->string('valide_dg_fonction')->nullable();
            $table->string('signature_dg_hash', 64)->nullable();

            $table->text('commentaire_rejet')->nullable();
            $table->timestamps();

            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etats_paiement');
    }
};
