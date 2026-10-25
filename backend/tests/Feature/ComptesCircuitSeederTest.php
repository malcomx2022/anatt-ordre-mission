<?php

use App\Models\User;
use Database\Seeders\ComptesCircuitSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * ComptesCircuitSeeder est destiné à tourner EN PRODUCTION : ses garanties
 * (pas de mot de passe en dur, idempotence, aucune réinitialisation) doivent
 * être verrouillées par des tests.
 */

beforeEach(function () {
    config(['profils.circuit' => [
        'controleur' => ['name' => 'Paul AGBO', 'email' => 'p.agbo@anatt.bj', 'fonction' => 'Contrôleur'],
        'daf' => ['name' => 'Euloge D. ATOHOUN', 'email' => 'e.atohoun@anatt.bj', 'fonction' => "Directeur de l'Administration et des Finances"],
        'dg' => ['name' => 'Richard DADA', 'email' => 'r.dada@anatt.bj', 'fonction' => 'Directeur Général'],
    ]]);
});

it('crée les trois comptes du circuit avec le bon profil', function () {
    $this->seed(ComptesCircuitSeeder::class);

    expect(User::where('email', 'p.agbo@anatt.bj')->value('role'))->toBe('CONTROLEUR')
        ->and(User::where('email', 'e.atohoun@anatt.bj')->value('role'))->toBe('DAF')
        ->and(User::where('email', 'r.dada@anatt.bj')->value('role'))->toBe('DG');
});

it('impose le changement du mot de passe temporaire à la première connexion', function () {
    $this->seed(ComptesCircuitSeeder::class);

    expect(User::whereIn('email', ['p.agbo@anatt.bj', 'e.atohoun@anatt.bj', 'r.dada@anatt.bj'])
        ->pluck('must_change_password')->every(fn ($v) => (bool) $v))->toBeTrue();
});

it('enregistre la fonction imprimée sous la signature', function () {
    $this->seed(ComptesCircuitSeeder::class);

    expect(User::where('email', 'e.atohoun@anatt.bj')->value('fonction'))
        ->toBe("Directeur de l'Administration et des Finances");
});

it('n’utilise aucun mot de passe prévisible', function () {
    $this->seed(ComptesCircuitSeeder::class);

    $hash = User::where('email', 'r.dada@anatt.bj')->value('password');

    foreach (['password', 'secret', '123456', 'anatt', 'r.dada@anatt.bj'] as $tentative) {
        expect(Hash::check($tentative, $hash))->toBeFalse();
    }
});

it('ne réinitialise jamais le mot de passe d’un compte existant', function () {
    $existant = User::factory()->create([
        'email' => 'e.atohoun@anatt.bj',
        'password' => Hash::make('MotDePasseChoisiParLUtilisateur'),
        'role' => 'LECTEUR',
        'must_change_password' => false,
    ]);

    $this->seed(ComptesCircuitSeeder::class);

    $existant->refresh();

    // Le rôle et la fonction sont alignés…
    expect($existant->role)->toBe('DAF')
        ->and($existant->fonction)->toBe("Directeur de l'Administration et des Finances")
        // …mais le mot de passe choisi par l'intéressé est intact.
        ->and(Hash::check('MotDePasseChoisiParLUtilisateur', $existant->password))->toBeTrue()
        ->and($existant->must_change_password)->toBeFalse();
});

it('est idempotent : deux exécutions ne créent pas de doublon', function () {
    $this->seed(ComptesCircuitSeeder::class);
    $this->seed(ComptesCircuitSeeder::class);

    expect(User::where('email', 'e.atohoun@anatt.bj')->count())->toBe(1);
});

it('ignore un poste non renseigné plutôt que d’inventer un compte', function () {
    config(['profils.circuit' => [
        'controleur' => ['name' => null, 'email' => null],
        'daf' => ['name' => 'Euloge D. ATOHOUN', 'email' => 'e.atohoun@anatt.bj'],
        'dg' => ['name' => 'Richard DADA', 'email' => null],
    ]]);

    $this->seed(ComptesCircuitSeeder::class);

    expect(User::where('role', 'DAF')->count())->toBe(1)
        ->and(User::where('role', 'DG')->count())->toBe(0)
        ->and(User::where('role', 'CONTROLEUR')->count())->toBe(0);
});

it('ne rétrograde jamais un super administrateur dont l’e-mail est celui d’un poste', function () {
    // Le seeder alignait le rôle de tout compte existant : un DG_EMAIL égal à
    // celui du super administrateur (souvent le seul) lui retirait tout accès.
    $superAdmin = User::factory()->superAdmin()->create(['email' => 'r.dada@anatt.bj']);

    $this->seed(ComptesCircuitSeeder::class);

    expect($superAdmin->fresh()->role)->toBe('SUPER_ADMIN')
        ->and(User::where('role', 'DG')->count())->toBe(0)
        ->and(User::where('role', 'DAF')->count())->toBe(1);
});
