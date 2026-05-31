<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremes', function (Blueprint $table) {
            $table->id();
            $table->enum('categorie', ['ORDRE_1', 'ORDRE_2', 'ORDRE_3']);
            $table->enum('zone_code', ['INTERIEUR', 'EXT_ZONE_I', 'EXT_ZONE_II', 'EXT_ZONE_III']);
            $table->enum('type_prise_en_charge', [
                'UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE',
                'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL',
            ]);
            // Taux journalier en FCFA (entier).
            $table->unsignedBigInteger('taux_journalier');
            $table->timestamps();

            // Contrainte APPLICATIVE (validée au seeding / FormRequest) :
            //  - UN_REPAS / DEUX_REPAS  => uniquement zone INTERIEUR
            //  - LOGE_* / NOURRI_SEUL   => uniquement zones EXTERIEUR (EXT_ZONE_*)
            //  - JOURNEE_COMPLETE       => toutes zones
            $table->unique(['categorie', 'zone_code', 'type_prise_en_charge'], 'baremes_unique_cle');
            $table->index('zone_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baremes');
    }
};
