<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profils DAF et DG (les deux signataires du circuit de validation) et support
 * de la signature visuelle apposée sur les documents.
 *
 * Aucun compte existant n'est modifié : les deux profils sont simplement rendus
 * assignables.
 */
return new class extends Migration
{
    private const ROLES_CIBLE = [
        'SUPER_ADMIN', 'ADMIN', 'GESTIONNAIRE_OM', 'AGENT_FINANCIER',
        'DAF', 'DG', 'CONTROLEUR', 'LECTEUR',
    ];

    private const ROLES_ORIGINE = [
        'SUPER_ADMIN', 'ADMIN', 'GESTIONNAIRE_OM', 'AGENT_FINANCIER', 'CONTROLEUR', 'LECTEUR',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::ROLES_CIBLE)->default('LECTEUR')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            // Fonction officielle telle qu'elle doit apparaître sous la signature
            // (ex. « Directeur de l'Administration et des Finances »).
            $table->string('fonction')->nullable()->after('telephone');
            // Image de signature : disque PRIVÉ uniquement, jamais exposée en URL
            // publique devinable (donnée sensible).
            $table->string('signature_path')->nullable()->after('fonction');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fonction', 'signature_path']);
        });

        // Les comptes DAF/DG deviendraient invalides au regard de l'ENUM restreint :
        // on les rétrograde en LECTEUR (profil le moins privilégié) plutôt que de
        // laisser une valeur hors contrainte.
        DB::table('users')->whereIn('role', ['DAF', 'DG'])->update(['role' => 'LECTEUR']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', self::ROLES_ORIGINE)->default('LECTEUR')->change();
        });
    }
};
