<?php

use App\Models\Agent;
use App\Support\CategorieIndice;

it('déduit la catégorie depuis l’indice', function (int $indice, string $cat) {
    expect(Agent::determinerCategorie($indice, null))->toBe($cat);
})->with([
    'borne haute INDICE_SUP_800' => [800, 'INDICE_SUP_800'],
    'au-dessus' => [950, 'INDICE_SUP_800'],
    'sous la borne -> INDICE_400_799' => [799, 'INDICE_400_799'],
    'borne basse INDICE_400_799' => [400, 'INDICE_400_799'],
    'sous 400 -> INDICE_INF_400' => [399, 'INDICE_INF_400'],
    'minimum' => [0, 'INDICE_INF_400'],
]);

it('déduit la catégorie depuis le salaire brut', function (int $salaire, string $cat) {
    expect(Agent::determinerCategorie(null, $salaire))->toBe($cat);
})->with([
    'borne INDICE_SUP_800' => [372000, 'INDICE_SUP_800'],
    'au-dessus' => [410000, 'INDICE_SUP_800'],
    'sous -> INDICE_400_799' => [371999, 'INDICE_400_799'],
    'borne INDICE_400_799' => [186000, 'INDICE_400_799'],
    'sous 186000 -> INDICE_INF_400' => [185999, 'INDICE_INF_400'],
    'faible' => [120000, 'INDICE_INF_400'],
]);

it('donne la priorité à l’indice sur le salaire brut', function () {
    // Indice INDICE_SUP_800 mais salaire INDICE_INF_400 -> l'indice prime.
    expect(Agent::determinerCategorie(850, 120000))->toBe('INDICE_SUP_800');
});

it('renvoie null sans indice ni salaire', function () {
    expect(Agent::determinerCategorie(null, null))->toBeNull();
});

it('traduit les anciens codes ORDRE_* vers le vocabulaire courant', function (string $ancien, string $attendu) {
    expect(CategorieIndice::normaliser($ancien))->toBe($attendu);
})->with([
    'ORDRE_1' => ['ORDRE_1', 'INDICE_SUP_800'],
    'ORDRE_2' => ['ORDRE_2', 'INDICE_400_799'],
    'ORDRE_3' => ['ORDRE_3', 'INDICE_INF_400'],
    'casse et espaces tolérés' => [' ordre_1 ', 'INDICE_SUP_800'],
]);

it('laisse passer un code déjà courant et rejette l’inconnu', function () {
    expect(CategorieIndice::normaliser('INDICE_400_799'))->toBe('INDICE_400_799')
        ->and(CategorieIndice::normaliser('ORDRE_4'))->toBeNull()
        ->and(CategorieIndice::normaliser(null))->toBeNull();
});

it('expose un libellé lisible et la plage imprimée sur l’état officiel', function () {
    expect(CategorieIndice::libelle('INDICE_SUP_800'))->toBe('Indice ≥ 800')
        ->and(CategorieIndice::libelle('INDICE_400_799'))->toBe('Indice 400-799')
        ->and(CategorieIndice::libelle('INDICE_INF_400'))->toBe('Indice < 400')
        // Forme courte figée : les états déjà signés la portent dans leur instantané.
        ->and(CategorieIndice::plage('INDICE_SUP_800'))->toBe('≥ 800')
        ->and(CategorieIndice::plage('INDICE_400_799'))->toBe('400-799')
        ->and(CategorieIndice::plage('INDICE_INF_400'))->toBe('< 400');
});

it('retombe sur l’indice réel puis sur un tiret pour une catégorie inconnue', function () {
    expect(CategorieIndice::plage('ORDRE_1', 850))->toBe('850')
        ->and(CategorieIndice::plage(null, null))->toBe('—')
        // Un code hérité non traduit reste affichable tel quel plutôt que vide.
        ->and(CategorieIndice::libelle('ORDRE_1'))->toBe('ORDRE_1');
});
