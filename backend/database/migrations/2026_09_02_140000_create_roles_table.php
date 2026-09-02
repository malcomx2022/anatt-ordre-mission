<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Rôles PERSONNALISÉS (RBAC flexible — niveau 3).
 *
 * Les 8 profils métier restent définis dans le code (App\Support\Permissions :
 * ils portent la sémantique du circuit de signature et les protections
 * SUPER_ADMIN). Cette table ne stocke QUE les rôles créés à la demande, dont les
 * permissions vivent dans role_permissions comme pour les profils système.
 *
 * `systeme` reste false pour tout rôle créé ici ; il documente l'invariant et
 * sert de garde-fou (interdit de recréer/écraser un code de profil système).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
            $table->boolean('systeme')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
