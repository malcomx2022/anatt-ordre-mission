<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordre_missions', function (Blueprint $table) {
            $table->id();
            // Référence SEMO = clé naturelle UNIQUE (anti-doublon à la retranscription).
            $table->string('reference_semo')->unique();
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents');
            // Le CAV est un bénéficiaire à part entière, payé selon SA catégorie.
            $table->enum('role', ['PARTICIPANT', 'CAV'])->default('PARTICIPANT');
            $table->enum('categorie', ['ORDRE_1', 'ORDRE_2', 'ORDRE_3']);
            $table->enum('zone_code', ['INTERIEUR', 'EXT_ZONE_I', 'EXT_ZONE_II', 'EXT_ZONE_III']);
            $table->enum('type_prise_en_charge', [
                'UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE',
                'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL',
            ]);
            $table->integer('nb_jours');
            // Montants en FCFA entiers : total = avance + reliquat.
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->unsignedBigInteger('avance')->default(0);
            $table->unsignedBigInteger('reliquat')->default(0);
            $table->enum('statut', [
                'BROUILLON', 'IMPORTE', 'EN_ATTENTE', 'VALIDE', 'REJETE', 'SOLDE', 'ANNULE',
            ])->default('IMPORTE');
            // Mention logistique uniquement (≠ bénéficiaire) : nom du conducteur à bord.
            $table->text('conducteur_a_bord')->nullable();
            $table->string('reference_autorisation')->nullable();
            $table->string('moyen_transport')->nullable();
            // Motif obligatoire en cas de rejet (statut REJETE).
            $table->text('commentaire_rejet')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('mission_id');
            $table->index('statut');
            $table->index('zone_code');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordre_missions');
    }
};
