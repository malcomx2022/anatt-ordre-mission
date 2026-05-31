<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('matricule')->unique();
            $table->string('ifu')->nullable();
            $table->string('grade')->nullable();
            $table->integer('indice')->nullable();
            $table->integer('salaire_brut')->nullable();
            $table->enum('categorie', ['ORDRE_1', 'ORDRE_2', 'ORDRE_3']);
            // Téléphone national à 10 chiffres ; opérateur Mobile Money du bénéficiaire.
            $table->string('telephone', 10);
            $table->enum('operateur', ['MTN', 'MOOV']);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('categorie');
            $table->index('operateur');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
