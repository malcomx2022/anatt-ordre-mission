<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Matrice rôle → permissions ÉDITABLE (RBAC flexible).
 *
 * La source de vérité vivante devient cette table ; config/profils.php reste le
 * défaut / repli (installation neuve, tests, profils jamais personnalisés).
 * Un profil sans ligne ici hérite de la matrice de config ; dès qu'on l'édite,
 * ses lignes font foi. Le SUPER_ADMIN n'y figure jamais (accès implicite total).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 50);
            $table->string('permission', 60);
            $table->timestamps();

            $table->unique(['role', 'permission']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
