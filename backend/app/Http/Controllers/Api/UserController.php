<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->get('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }
        if ($request->filled('actif')) {
            $query->where('actif', $request->boolean('actif'));
        }

        $users = $query->orderBy('name')->paginate($this->getPerPage($request));

        return UserResource::collection($users)->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Créer/promouvoir un SUPER_ADMIN exige superadmins.gerer (SUPER_ADMIN seul).
        $this->verrouSuperAdmin($request, $data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'telephone' => $data['telephone'] ?? null,
            'cree_par' => $request->user()->id,
        ]);
        $user->assignRole($data['role']);
        $user->actif = $data['actif'] ?? true;
        // Le mot de passe initial est défini par l'administrateur (connu d'un tiers) :
        // l'utilisateur devra le changer à sa première connexion.
        $user->must_change_password = true;
        $user->save();

        AuditService::log($request->user(), 'CREATE_USER', 'user', $user->id, [
            'email' => $user->email, 'role' => $user->role,
        ]);

        return $this->created(new UserResource($user), 'Utilisateur créé avec succès.');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Toute opération touchant un SUPER_ADMIN (cible ou rôle visé) exige superadmins.gerer.
        $this->verrouSuperAdmin($request, $data['role'] ?? null, $user);

        // Invariant : le dernier SUPER_ADMIN actif ne peut être ni rétrogradé ni désactivé.
        if ($this->estDernierSuperAdmin($user)) {
            $retrograde = array_key_exists('role', $data) && $data['role'] !== User::ROLE_SUPER_ADMIN;
            $desactive = array_key_exists('actif', $data) && ! $data['actif'];
            abort_if($retrograde || $desactive, 422, "Impossible : c'est le dernier super administrateur actif.");
        }

        // On ne peut pas se désactiver soi-même.
        abort_if(
            $user->id === $request->user()->id && array_key_exists('actif', $data) && ! $data['actif'],
            422,
            'Vous ne pouvez pas désactiver votre propre compte.',
        );

        $ancienRole = $user->role;

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (array_key_exists('telephone', $data)) {
            $user->telephone = $data['telephone'];
        }
        if (isset($data['password'])) {
            $user->password = $data['password'];
        }
        if (array_key_exists('actif', $data)) {
            $user->actif = $data['actif'];
        }
        $user->save();

        if (isset($data['role'])) {
            $user->assignRole($data['role']);
        }

        // Révoque les tokens si le compte est désactivé ou son rôle modifié : sans cela,
        // une session déjà ouverte conserverait ses anciennes permissions (la vérification
        // `actif`/permissions n'a lieu qu'à la connexion).
        $roleChange = isset($data['role']) && $data['role'] !== $ancienRole;
        $desactivation = array_key_exists('actif', $data) && ! $data['actif'];
        if ($roleChange || $desactivation) {
            $user->tokens()->delete();
        }

        AuditService::log($request->user(), 'UPDATE_USER', 'user', $user->id, [
            'email' => $user->email, 'role' => $user->role, 'actif' => $user->actif,
        ]);

        // Trace explicitement tout changement de profil.
        if ($roleChange) {
            AuditService::log($request->user(), 'CHANGE_ROLE_USER', 'user', $user->id, [
                'ancien_role' => $ancienRole, 'nouveau_role' => $user->role,
            ]);
        }

        return $this->success(new UserResource($user->fresh()), 'Utilisateur mis à jour.');
    }

    /** Active ou désactive un compte (action dédiée, journalisée). */
    public function setActif(Request $request, User $user): JsonResponse
    {
        $request->validate(['actif' => 'required|boolean']);
        $actif = $request->boolean('actif');

        $this->verrouSuperAdmin($request, null, $user);

        // Le dernier super admin actif ne peut être désactivé.
        abort_if(! $actif && $this->estDernierSuperAdmin($user), 422, "Impossible : c'est le dernier super administrateur actif.");
        // On ne peut pas se désactiver soi-même.
        abort_if(! $actif && $user->id === $request->user()->id, 422, 'Vous ne pouvez pas désactiver votre propre compte.');

        $user->actif = $actif;
        $user->save();

        // Désactivation : on révoque immédiatement les sessions actives du compte.
        if (! $actif) {
            $user->tokens()->delete();
        }

        AuditService::log($request->user(), $actif ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', 'user', $user->id, [
            'email' => $user->email,
        ]);

        return $this->success(new UserResource($user->fresh()), $actif ? 'Compte activé.' : 'Compte désactivé.');
    }

    /** Réinitialise le mot de passe : génère un mot de passe temporaire et le renvoie. */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->verrouSuperAdmin($request, null, $user);

        $request->validate(['password' => ['sometimes', 'string', Password::defaults()]]);

        $nouveau = $request->input('password') ?: Str::password(16);
        $user->password = $nouveau;
        // Mot de passe temporaire connu de l'administrateur : changement forcé à la
        // prochaine connexion. On révoque aussi les sessions actives de la cible.
        $user->must_change_password = true;
        $user->save();
        $user->tokens()->delete();

        // Journalisation distincte quand la cible est un SUPER_ADMIN : un reset par un
        // pair est un vecteur de prise de contrôle latérale qui doit rester traçable.
        $action = $user->isSuperAdmin() ? 'RESET_PASSWORD_SUPERADMIN' : 'RESET_PASSWORD_USER';
        AuditService::log($request->user(), $action, 'user', $user->id, [
            'email' => $user->email,
        ]);

        return $this->success(['password' => $nouveau], 'Mot de passe réinitialisé.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->verrouSuperAdmin($request, null, $user);

        abort_if($this->estDernierSuperAdmin($user), 422, "Impossible : c'est le dernier super administrateur actif.");
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');

        // Révoque les sessions actives avant suppression du compte.
        $user->tokens()->delete();
        $user->delete();

        AuditService::log($request->user(), 'DELETE_USER', 'user', $user->id, ['email' => $user->email]);

        return $this->success(null, 'Utilisateur supprimé.');
    }

    /**
     * Exige la permission superadmins.gerer dès qu'un SUPER_ADMIN est impliqué
     * (cible existante ou rôle visé).
     */
    private function verrouSuperAdmin(Request $request, ?string $roleVise = null, ?User $cible = null): void
    {
        $toucheSuperAdmin = $roleVise === User::ROLE_SUPER_ADMIN || ($cible && $cible->isSuperAdmin());

        abort_if(
            $toucheSuperAdmin && ! $request->user()->can(Permissions::SUPERADMINS_GERER),
            403,
            'Seul un super administrateur peut gérer les super administrateurs.',
        );
    }

    /** Le compte est-il le DERNIER super administrateur actif ? */
    private function estDernierSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin()
            && $user->actif
            && User::where('role', User::ROLE_SUPER_ADMIN)
                ->where('actif', true)
                ->where('id', '!=', $user->id)
                ->doesntExist();
    }
}
