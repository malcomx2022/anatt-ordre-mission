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
            $table->string('numero')->unique();
            $table->foreignId('agent_id')->constrained();
            $table->string('objet');
            $table->string('destination');
            $table->date('date_depart');
            $table->date('date_retour');
            $table->integer('nb_jours');
            $table->foreignId('taux_id')->constrained('taux_per_diems');
            $table->decimal('montant_per_diem', 12, 2)->default(0);
            $table->enum('statut', ['BROUILLON', 'SOUMIS', 'VALIDE_N1', 'APPROUVE_DG', 'PAYE', 'REJETE'])->default('BROUILLON');
            $table->text('commentaire_rejet')->nullable();
            $table->foreignId('valide_par_chef')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('valide_par_dg')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordre_missions');
    }
};
