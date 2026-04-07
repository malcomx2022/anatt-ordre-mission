<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taux_per_diems', function (Blueprint $table) {
            $table->id();
            $table->string('categorie');
            $table->enum('destination_type', ['LOCALE', 'REGIONALE', 'INTERNATIONALE']);
            $table->decimal('montant_journalier', 12, 2);
            $table->integer('annee');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['categorie', 'destination_type', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taux_per_diems');
    }
};
