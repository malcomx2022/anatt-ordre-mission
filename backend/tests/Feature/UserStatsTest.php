<?php

use App\Models\User;

/*
 * Cartes de synthèse de la gestion des utilisateurs : la répartition par rôle
 * doit être GLOBALE (ensemble filtré), pas limitée à la page courante.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('renvoie une répartition par rôle globale, indépendante de la pagination', function () {
    // En base : 1 SUPER_ADMIN, 3 ADMIN (dont l'acteur), 2 autres profils.
    User::factory()->superAdmin()->create();
    User::factory()->admin()->count(2)->create();
    User::factory()->lecteur()->create();
    User::factory()->controleur()->create();

    // per_page=5 : la page ne contient que 5 des 6 comptes, mais les stats
    // couvrent l'ensemble (démontre « global ≠ page courante »).
    $this->actingAs($this->admin)
        ->getJson('/api/admin/users?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('stats.total', 6)
        ->assertJsonPath('stats.super_admins', 1)
        ->assertJsonPath('stats.admins', 3)
        ->assertJsonPath('stats.autres', 2);
});

it('la répartition reflète le filtre appliqué', function () {
    User::factory()->superAdmin()->create();
    User::factory()->lecteur()->count(2)->create();

    // Filtre role=ADMIN : seul l'acteur admin correspond.
    $this->actingAs($this->admin)
        ->getJson('/api/admin/users?role=ADMIN')
        ->assertOk()
        ->assertJsonPath('stats.total', 1)
        ->assertJsonPath('stats.admins', 1)
        ->assertJsonPath('stats.super_admins', 0)
        ->assertJsonPath('stats.autres', 0);
});
