<?php

use App\Models\OrdreMission;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/*
 * Motifs de refus (422) en français.
 *
 * En production, APP_LOCALE=fr et APP_FALLBACK_LOCALE=fr : sans fichier de
 * traduction, une règle générique (required, unique, min…) renvoyait sa clé brute
 * — « validation.unique » — dans `errors`. L'écran affiche désormais ce motif
 * (getErrorMessage) : il doit être lisible. Traductions : resources/lang/fr.
 */

beforeEach(function () {
    config(['app.fallback_locale' => 'fr']); // comme .env.production.example
    app()->setLocale('fr');
});

it('ne renvoie jamais une clé brute pour les règles employées par l’API', function (array $donnees, array $regles) {
    $erreurs = Validator::make($donnees, $regles)->errors()->all();

    expect($erreurs)->not->toBeEmpty();
    foreach ($erreurs as $e) {
        expect($e)->not->toStartWith('validation.');
    }
})->with([
    'required' => [[], ['motif' => 'required']],
    'email' => [['email' => 'x'], ['email' => 'email']],
    'string' => [['motif' => 12], ['motif' => 'string']],
    'min / max (texte)' => [['motif' => 'court', 'nom' => str_repeat('a', 300)], ['motif' => 'min:10', 'nom' => 'max:255']],
    'min (nombre)' => [['nb_jours' => 0], ['nb_jours' => 'integer|min:1']],
    'integer' => [['nb_jours' => 'abc'], ['nb_jours' => 'integer']],
    'in' => [['tranche' => 'X'], ['tranche' => 'in:AVANCE,RELIQUAT']],
    'array' => [['lignes' => 'x'], ['lignes' => 'array']],
    'date' => [['date_depart' => 'hier'], ['date_depart' => 'date']],
    'after_or_equal' => [['a' => '2026-01-02', 'b' => '2026-01-01'], ['b' => 'date|after_or_equal:a']],
    'confirmed' => [['password' => 'a', 'password_confirmation' => 'b'], ['password' => 'confirmed']],
    'regex' => [['telephone' => 'x'], ['telephone' => 'regex:/^01\d{8}$/']],
    'required_with' => [['lignes' => [['nb_jours' => 1]]], ['lignes.*.type' => 'required_with:lignes']],
    'required_without' => [[], ['nb_jours' => 'required_without:lignes']],
    'boolean' => [['actif' => 'peut-être'], ['actif' => 'boolean']],
    'distinct' => [['ids' => [1, 1]], ['ids.*' => 'distinct']],
]);

it('nomme le champ en français dans le motif', function () {
    $erreur = Validator::make([], ['motif' => 'required'])->errors()->first('motif');

    expect($erreur)->toBe('Le champ motif est obligatoire.');
});

it('explique un e-mail déjà utilisé à la création d’un compte', function () {
    User::factory()->create(['email' => 'pris@anatt.bj']);

    $this->actingAs(User::factory()->admin()->create())
        ->postJson('/api/admin/users', ['name' => 'Doublon', 'email' => 'pris@anatt.bj', 'password' => 'Secret!2026', 'role' => 'LECTEUR'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée.');
});

it('explique un motif d’amendement trop court', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs(User::factory()->gestionnaireOm()->create())
        ->postJson("/api/ordres/{$om->id}/amender", ['nb_jours' => 2, 'motif' => 'court'])
        ->assertStatus(422)
        ->assertJsonPath('errors.motif.0', 'Le champ motif doit contenir au moins 10 caractères.');
});
