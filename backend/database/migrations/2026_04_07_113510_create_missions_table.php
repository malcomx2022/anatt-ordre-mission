<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            // Une mission (événement) regroupe N OM individuels (participants + chauffeurs).
            $table->string('objet'); // thème de la mission
            $table->string('destination');
            $table->enum('zone_code', ['INTERIEUR', 'EXT_ZONE_I', 'EXT_ZONE_II', 'EXT_ZONE_III']);
            $table->date('date_depart');
            $table->date('date_retour');
            $table->string('imputation_budgetaire')->nullable();
            $table->boolean('prise_en_charge_tiers')->default(false);
            $table->text('observations')->nullable();
            $table->timestamps();

            $table->index('zone_code');
            $table->index(['date_depart', 'date_retour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
