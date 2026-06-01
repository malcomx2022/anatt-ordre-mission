<?php

use App\Models\Agent;

it('déduit la catégorie depuis l’indice', function (int $indice, string $cat) {
    expect(Agent::determinerCategorie($indice, null))->toBe($cat);
})->with([
    'borne haute ORDRE_1' => [800, 'ORDRE_1'],
    'au-dessus' => [950, 'ORDRE_1'],
    'sous la borne -> ORDRE_2' => [799, 'ORDRE_2'],
    'borne basse ORDRE_2' => [400, 'ORDRE_2'],
    'sous 400 -> ORDRE_3' => [399, 'ORDRE_3'],
    'minimum' => [0, 'ORDRE_3'],
]);

it('déduit la catégorie depuis le salaire brut', function (int $salaire, string $cat) {
    expect(Agent::determinerCategorie(null, $salaire))->toBe($cat);
})->with([
    'borne ORDRE_1' => [372000, 'ORDRE_1'],
    'au-dessus' => [410000, 'ORDRE_1'],
    'sous -> ORDRE_2' => [371999, 'ORDRE_2'],
    'borne ORDRE_2' => [186000, 'ORDRE_2'],
    'sous 186000 -> ORDRE_3' => [185999, 'ORDRE_3'],
    'faible' => [120000, 'ORDRE_3'],
]);

it('donne la priorité à l’indice sur le salaire brut', function () {
    // Indice ORDRE_1 mais salaire ORDRE_3 -> l'indice prime.
    expect(Agent::determinerCategorie(850, 120000))->toBe('ORDRE_1');
});

it('renvoie null sans indice ni salaire', function () {
    expect(Agent::determinerCategorie(null, null))->toBeNull();
});
