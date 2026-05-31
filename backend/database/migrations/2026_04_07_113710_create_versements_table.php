<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_mission_id')->constrained('ordre_missions')->cascadeOnDelete();
            // lot_id nullable : un versement EN_ATTENTE n'est rattaché à aucun lot tant qu'il n'est pas généré.
            $table->foreignId('lot_id')->nullable()->constrained('lots_paiement')->nullOnDelete();
            $table->enum('type', ['AVANCE', 'RELIQUAT']);
            // Montant en FCFA entier.
            $table->unsignedBigInteger('montant');
            // Téléphone et opérateur figés au moment du versement (issus du référentiel agents).
            $table->enum('operateur', ['MTN', 'MOOV']);
            $table->string('telephone', 10);
            $table->enum('statut', ['EN_ATTENTE', 'GENERE', 'CONFIRME', 'ANNULE'])->default('EN_ATTENTE');
            // Horodatage rempli à la confirmation manuelle du dépôt sur la plateforme opérateur.
            $table->timestamp('date_paiement')->nullable();
            $table->timestamps();

            $table->index('ordre_mission_id');
            $table->index('lot_id');
            $table->index('statut');
            $table->index('date_paiement');
            // Idempotence : un seul versement par (OM, type) — pas de double avance/reliquat.
            $table->unique(['ordre_mission_id', 'type'], 'versements_unique_cle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements');
    }
};
