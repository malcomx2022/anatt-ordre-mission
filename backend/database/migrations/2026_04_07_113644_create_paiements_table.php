<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_mission_id')->constrained('ordre_missions');
            $table->foreignId('agent_id')->constrained();
            $table->enum('operateur', ['MOOV', 'MTN']);
            $table->string('numero_destinataire');
            $table->decimal('montant', 12, 2);
            $table->enum('statut', ['EN_ATTENTE', 'INITIE', 'SUCCES', 'ECHEC'])->default('EN_ATTENTE');
            $table->string('reference_transaction')->nullable();
            $table->string('fichier_virement')->nullable();
            $table->timestamp('date_virement')->nullable();
            $table->timestamps();

            $table->index(['statut', 'operateur']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
