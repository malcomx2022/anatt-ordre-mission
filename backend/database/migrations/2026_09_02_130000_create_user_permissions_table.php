<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Overrides de permissions PAR UTILISATEUR (RBAC flexible — niveau 2).
 *
 * En plus de son profil (matrice rôle → permissions), un utilisateur peut se
 * voir ACCORDER une permission supplémentaire (granted = true) ou RETIRER une
 * permission que son profil donnerait (granted = false).
 *
 * Permissions effectives = base_du_profil ∪ accordées − retirées.
 * Le SUPER_ADMIN n'est jamais concerné (accès implicite total).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);
            $table->boolean('granted'); // true = accordée en plus ; false = retirée
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
