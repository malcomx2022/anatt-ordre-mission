<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des amendements d'un ordre de mission (changement du nombre de jours
 * facturés après retranscription). Trace l'avant/après et le delta, pour audit et
 * pour l'affichage de l'historique. Cf. docs/amendement-om.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('om_amendements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_mission_id')->constrained('ordre_missions')->cascadeOnDelete();
            $table->unsignedInteger('nb_jours_avant');
            $table->unsignedInteger('nb_jours_apres');
            $table->json('lignes_avant')->nullable();
            $table->unsignedBigInteger('montant_total_avant');
            $table->unsignedBigInteger('montant_total_apres');
            $table->bigInteger('delta_montant'); // signé : négatif = réduction
            $table->text('motif');
            $table->foreignId('amende_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('amende_par_nom')->nullable();   // instantané du signataire
            $table->timestamp('amende_le');
            $table->timestamps();

            $table->index('ordre_mission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('om_amendements');
    }
};
