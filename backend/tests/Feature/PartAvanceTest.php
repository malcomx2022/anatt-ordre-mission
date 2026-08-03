<?php

use App\Models\User;
use App\Support\Parametres;

/*
 * Lecture du pourcentage d'avance : route PUBLIQUE (tout authentifié), à ne pas
 * confondre avec /admin/settings réservée au SUPER_ADMIN (parametres.gerer).
 * C'est cette route que l'UI interroge pour tous les profils.
 */

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

it('exige une authentification', function () {
    $this->getJson('/api/admin/parametres/part-avance')->assertUnauthorized();
});

it('renvoie la part par défaut (70 %) à un profil non administrateur', function () {
    // Un lecteur n'a pas parametres.gerer : il serait bloqué sur /settings,
    // mais doit pouvoir lire la part via cette route dédiée.
    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/admin/parametres/part-avance')
        ->assertOk()
        ->assertJsonPath('data', 70);
});

it('renvoie la part configurée', function () {
    file_put_contents(storage_path('app/settings.json'), json_encode(['partAvance' => 60]));
    Parametres::invalidateCache();

    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/admin/parametres/part-avance')
        ->assertOk()
        ->assertJsonPath('data', 60);
});
