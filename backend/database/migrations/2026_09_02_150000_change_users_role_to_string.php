<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * RBAC niveau 3 : la colonne users.role était un ENUM figé sur les 8 profils
 * système (contrainte CHECK). Les rôles personnalisés exigent une chaîne libre.
 * L'intégrité des codes est désormais assurée applicativement (validation contre
 * PermissionRepository::codes()), pas par une contrainte de base gravée dans le marbre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('LECTEUR')->change();
        });
    }

    public function down(): void
    {
        // Repli best-effort vers l'énumération des profils système.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'SUPER_ADMIN', 'ADMIN', 'GESTIONNAIRE_OM', 'AGENT_FINANCIER',
                'DAF', 'DG', 'CONTROLEUR', 'LECTEUR',
            ])->default('LECTEUR')->change();
        });
    }
};
