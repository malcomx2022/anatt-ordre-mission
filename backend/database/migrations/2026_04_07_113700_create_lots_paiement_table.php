<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots_paiement', function (Blueprint $table) {
            $table->id();
            // Un lot = 1 mission × 1 tranche [AVANCE|RELIQUAT] × 1 opérateur [MTN|MOOV].
            $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
            $table->enum('tranche', ['AVANCE', 'RELIQUAT']);
            $table->enum('operateur', ['MTN', 'MOOV']);
            $table->enum('statut', ['GENERE', 'CONFIRME', 'ERREUR'])->default('GENERE');
            $table->string('nom_fichier')->nullable();
            $table->unsignedInteger('nb_lignes')->default(0);
            $table->unsignedBigInteger('montant_total')->default(0);
            $table->timestamp('genere_le')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirme_le')->nullable();
            $table->foreignId('confirme_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un seul lot par (mission, tranche, opérateur) : régénération = mise à jour du lot.
            $table->unique(['mission_id', 'tranche', 'operateur'], 'lots_unique_cle');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots_paiement');
    }
};
