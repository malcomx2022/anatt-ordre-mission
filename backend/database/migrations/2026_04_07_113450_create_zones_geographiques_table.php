<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones_geographiques', function (Blueprint $table) {
            $table->id();
            // EXT_ZONE_II et EXT_ZONE_III partagent les mêmes taux (cf. barème ANaTT).
            $table->enum('code', ['INTERIEUR', 'EXT_ZONE_I', 'EXT_ZONE_II', 'EXT_ZONE_III'])->unique();
            $table->string('libelle');
            $table->enum('portee', ['INTERIEUR', 'EXTERIEUR']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones_geographiques');
    }
};
