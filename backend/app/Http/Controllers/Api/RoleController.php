<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Services\AuditService;
use App\Support\PermissionRepository;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Matrice rôle → permissions ÉDITABLE (RBAC flexible).
 *
 * - Lecture (index) : utilisateurs.gerer — l'ADMIN consulte la matrice.
 * - Écriture (update / reset) : roles.gerer — SUPER_ADMIN uniquement.
 *
 * Le SUPER_ADMIN n'est pas éditable (accès implicite total). Les clés réservées
 * (superadmins.gerer, parametres.gerer, roles.gerer) ne sont jamais attribuables.
 */
class RoleController extends Controller
{
    public function __construct(private readonly PermissionRepository $repo) {}

    /** Matrice complète + catalogue des clés, pour l'écran d'administration. */
    public function index(): JsonResponse
    {
        return $this->success([
            'profils' => collect(Permissions::PROFILS)->map(fn (string $cle) => [
                'cle' => $cle,
                'libelle' => Permissions::LIBELLES[$cle] ?? $cle,
                'verrouille' => $cle === Permissions::SUPER_ADMIN,
                'personnalise' => in_array($cle, $this->repo->personnalises(), true),
            ])->all(),
            'permissions' => Permissions::TOUTES,
            'matrice' => $this->repo->matrice(),
            'non_assignables' => PermissionRepository::NON_ASSIGNABLES,
        ]);
    }

    /** Remplace intégralement les permissions d'un profil. */
    public function update(UpdateRolePermissionsRequest $request, string $role): JsonResponse
    {
        $role = strtoupper($role);
        $this->assurerProfilEditable($role);

        $permissions = array_values(array_unique($request->validated()['permissions']));
        $this->assurerAttribuables($permissions);
        $this->assurerSeparationDesTaches($permissions);

        $avant = $this->repo->pour($role);
        $apres = $this->repo->remplacer($role, $permissions);

        AuditService::log($request->user(), 'ROLE_PERM_UPDATE', 'role_permissions', null, [
            'role' => $role,
            'avant' => $avant,
            'apres' => $apres,
        ]);

        return $this->success(
            ['role' => $role, 'permissions' => $apres],
            'Permissions du profil mises à jour.'
        );
    }

    /** Rétablit le défaut de configuration pour un profil. */
    public function reset(string $role): JsonResponse
    {
        $role = strtoupper($role);
        $this->assurerProfilEditable($role);

        $avant = $this->repo->pour($role);
        $apres = $this->repo->reinitialiser($role);

        AuditService::log(request()->user(), 'ROLE_PERM_RESET', 'role_permissions', null, [
            'role' => $role,
            'avant' => $avant,
            'apres' => $apres,
        ]);

        return $this->success(
            ['role' => $role, 'permissions' => $apres],
            'Permissions du profil réinitialisées au défaut.'
        );
    }

    private function assurerProfilEditable(string $role): void
    {
        if (! in_array($role, Permissions::PROFILS, true)) {
            abort(404, 'Profil inconnu.');
        }

        if ($role === Permissions::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'role' => ['Le super administrateur a accès à tout : sa matrice n’est pas modifiable.'],
            ]);
        }
    }

    /**
     * @param  string[]  $permissions
     */
    private function assurerAttribuables(array $permissions): void
    {
        $reservees = array_values(array_intersect($permissions, PermissionRepository::NON_ASSIGNABLES));

        if ($reservees !== []) {
            throw ValidationException::withMessages([
                'permissions' => ['Clés réservées au super administrateur, non attribuables : '.implode(', ', $reservees).'.'],
            ]);
        }
    }

    /**
     * Séparation ordonnateur / payeur : un profil qui VALIDE l'état de paiement
     * (om.approuver) ne peut pas aussi EXÉCUTER les décaissements
     * (paiements.generer / paiements.confirmer). Règle de contrôle interne ANaTT.
     *
     * @param  string[]  $permissions
     */
    private function assurerSeparationDesTaches(array $permissions): void
    {
        $valide = in_array(Permissions::OM_APPROUVER, $permissions, true);
        $execute = array_intersect(
            [Permissions::PAIEMENTS_GENERER, Permissions::PAIEMENTS_CONFIRMER],
            $permissions
        ) !== [];

        if ($valide && $execute) {
            throw ValidationException::withMessages([
                'permissions' => ['Séparation ordonnateur/payeur : un profil qui valide l’état (om.approuver) ne peut pas aussi générer ou confirmer les paiements.'],
            ]);
        }
    }
}
