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
        ]);
        $user->assignRole($data['role']);
        $user->actif = $data['actif'] ?? true;
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

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
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

        AuditService::log($request->user(), 'UPDATE_USER', 'user', $user->id, [
            'email' => $user->email, 'role' => $user->role, 'actif' => $user->actif,
        ]);

        return $this->success(new UserResource($user->fresh()), 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->verrouSuperAdmin($request, null, $user);

        abort_if($this->estDernierSuperAdmin($user), 422, "Impossible : c'est le dernier super administrateur actif.");
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');

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
