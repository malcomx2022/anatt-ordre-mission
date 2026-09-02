<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Support\PermissionRepository;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $personnalises = $this->repo->personnalises();
        // Comptage des utilisateurs par rôle en UNE requête (évite un N+1 par profil).
        $comptes = User::query()->groupBy('role')->selectRaw('role, count(*) as total')->pluck('total', 'role');

        return $this->success([
            'profils' => collect($this->repo->roles())->map(fn (array $r) => [
                'cle' => $r['code'],
                'libelle' => $r['libelle'],
                'systeme' => $r['systeme'],
                'verrouille' => $r['code'] === Permissions::SUPER_ADMIN,
                'personnalise' => in_array($r['code'], $personnalises, true),
                'nb_utilisateurs' => (int) ($comptes[$r['code']] ?? 0),
            ])->all(),
            'permissions' => Permissions::TOUTES,
            'matrice' => $this->repo->matrice(),
            'non_assignables' => PermissionRepository::NON_ASSIGNABLES,
        ]);
    }

    /** Crée un rôle personnalisé (permissions initiales optionnelles). */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = strtoupper($data['code']);

        if ($this->repo->roleExiste($code)) {
            throw ValidationException::withMessages([
                'code' => ['Ce code de rôle existe déjà.'],
            ]);
        }

        $permissions = array_values(array_unique($data['permissions'] ?? []));
        $this->assurerAttribuables($permissions);
        $this->assurerSeparationDesTaches($permissions);

        $role = $this->repo->creerRole($code, $data['libelle'], $permissions);

        AuditService::log($request->user(), 'ROLE_CREATE', 'role', $role->id, [
            'code' => $code, 'libelle' => $role->libelle, 'permissions' => $permissions,
        ]);

        return $this->created(
            ['code' => $role->code, 'libelle' => $role->libelle, 'permissions' => $this->repo->pour($code)],
            'Rôle personnalisé créé.'
        );
    }

    /** Renomme un rôle personnalisé (le code reste immuable). */
    public function rename(Request $request, string $role): JsonResponse
    {
        $role = strtoupper($role);
        $this->assurerRolePersonnalise($role);

        $data = $request->validate(['libelle' => ['required', 'string', 'max:100']]);
        $modele = $this->repo->renommerRole($role, $data['libelle']);

        AuditService::log($request->user(), 'ROLE_RENAME', 'role', $modele->id, [
            'code' => $role, 'libelle' => $modele->libelle,
        ]);

        return $this->success(['code' => $modele->code, 'libelle' => $modele->libelle], 'Rôle renommé.');
    }

    /** Supprime un rôle personnalisé (aucun utilisateur ne doit y être rattaché). */
    public function destroy(Request $request, string $role): JsonResponse
    {
        $role = strtoupper($role);
        $this->assurerRolePersonnalise($role);

        $nb = User::where('role', $role)->count();
        if ($nb > 0) {
            throw ValidationException::withMessages([
                'role' => ["Impossible : {$nb} utilisateur(s) portent encore ce rôle. Réaffectez-les d’abord."],
            ]);
        }

        $this->repo->supprimerRole($role);

        AuditService::log($request->user(), 'ROLE_DELETE', 'role', null, ['code' => $role]);

        return $this->success(null, 'Rôle personnalisé supprimé.');
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
        if (! $this->repo->roleExiste($role)) {
            abort(404, 'Rôle inconnu.');
        }

        if ($role === Permissions::SUPER_ADMIN) {
            throw ValidationException::withMessages([
                'role' => ['Le super administrateur a accès à tout : sa matrice n’est pas modifiable.'],
            ]);
        }
    }

    /** Un rôle personnalisé (renommage/suppression) : ni inconnu, ni système. */
    private function assurerRolePersonnalise(string $role): void
    {
        if ($this->repo->estSysteme($role)) {
            throw ValidationException::withMessages([
                'role' => ['Les profils système ne peuvent être ni renommés ni supprimés.'],
            ]);
        }

        if (! $this->repo->roleExiste($role)) {
            abort(404, 'Rôle inconnu.');
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
