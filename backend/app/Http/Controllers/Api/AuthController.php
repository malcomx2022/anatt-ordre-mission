<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Hash bcrypt (coût 12) utilisé pour le hachage factice anti-timing au login.
     * Sa seule fonction est de faire travailler bcrypt quand l'email est inconnu ;
     * il ne correspond à aucun mot de passe réel.
     */
    private const HASH_FACTICE = '$2y$12$zCYxJ0ykvFyUeiSoV60tUuSo27cxsuLEfCcKzq0PzVnUNrQOa6LKe';

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Anti-énumération : on exécute TOUJOURS un hachage, même si l'utilisateur est
        // introuvable, pour égaliser le temps de réponse (sinon le coût bcrypt distingue
        // un email connu d'un email inconnu — timing oracle).
        $motDePasseValide = $user
            ? Hash::check($request->password, $user->password)
            : Hash::check($request->password, self::HASH_FACTICE);

        // Message unique : ne jamais révéler si l'email existe ni si le compte est désactivé.
        if (! $user || ! $motDePasseValide || ! $user->actif) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Trace la dernière connexion réussie (sans toucher updated_at).
        $user->forceFill(['derniere_connexion' => now()])->saveQuietly();

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
            'permissions' => $user->permissions(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Deconnexion reussie.');
    }

    /**
     * Changement de mot de passe par l'utilisateur lui-même.
     * Lève l'indicateur must_change_password et révoque les autres sessions
     * (le token courant est conservé pour ne pas déconnecter l'utilisateur).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        // Révoque toutes les autres sessions, conserve le token courant.
        // (En contexte de test, le token courant est transient et n'a pas d'id.)
        $courant = $request->user()->currentAccessToken();
        $autres = $user->tokens();
        if ($courant instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $autres->where('id', '!=', $courant->id);
        }
        $autres->delete();

        return $this->success(null, 'Mot de passe mis à jour.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /** Liste des permissions effectives de l'utilisateur courant (pilote l'UI). */
    public function permissions(Request $request): JsonResponse
    {
        return $this->success($request->user()->permissions());
    }

    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Anti-énumération : on déclenche l'envoi mais on renvoie TOUJOURS la même réponse,
        // que l'email existe ou non (ne pas révéler l'existence d'un compte).
        Password::sendResetLink($request->only('email'));

        return $this->success(null, "Si un compte existe pour cet email, un lien de reinitialisation a ete envoye.");
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Mot de passe reinitialise avec succes.');
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
