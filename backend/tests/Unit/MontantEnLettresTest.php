<?php

use App\Support\MontantEnLettres;

/*
 * La mention d'arrêt de l'état de paiement est une valeur juridique : le montant
 * en lettres doit être irréprochable, y compris sur les pièges d'accord du
 * français (quatre-vingts, cent, mille invariable).
 */

it('convertit les nombres jusqu’à seize', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [0, 'zéro'], [1, 'un'], [5, 'cinq'], [10, 'dix'],
    [11, 'onze'], [15, 'quinze'], [16, 'seize'],
]);

it('convertit les dix-sept à dix-neuf', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [17, 'dix-sept'], [18, 'dix-huit'], [19, 'dix-neuf'],
]);

it('applique la liaison « et » sur les unités en un', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [20, 'vingt'], [21, 'vingt et un'], [22, 'vingt-deux'],
    [31, 'trente et un'], [41, 'quarante et un'],
    [51, 'cinquante et un'], [61, 'soixante et un'],
]);

it('construit les soixante-dix sur soixante', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [70, 'soixante-dix'], [71, 'soixante et onze'], [72, 'soixante-douze'],
    [77, 'soixante-dix-sept'], [79, 'soixante-dix-neuf'],
]);

it('accorde quatre-vingts et ne dit jamais « quatre-vingt et un »', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [80, 'quatre-vingts'],          // s : rien ne suit
    [81, 'quatre-vingt-un'],        // pas de « et », pas de s
    [85, 'quatre-vingt-cinq'],
    [90, 'quatre-vingt-dix'],
    [91, 'quatre-vingt-onze'],      // pas de « et »
    [99, 'quatre-vingt-dix-neuf'],
]);

it('accorde cent au pluriel seulement en fin de nombre', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [100, 'cent'],                  // jamais « un cent »
    [101, 'cent un'],
    [180, 'cent quatre-vingts'],
    [200, 'deux cents'],            // s : rien ne suit
    [201, 'deux cent un'],          // pas de s : suivi
    [999, 'neuf cent quatre-vingt-dix-neuf'],
]);

it('laisse mille invariable et ne dit jamais « un mille »', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [1000, 'mille'],
    [1001, 'mille un'],
    [2000, 'deux mille'],           // mille invariable
    [80000, 'quatre-vingt mille'],  // pas de s : mille suit
    [200000, 'deux cent mille'],    // pas de s : mille suit
]);

it('accorde million et milliard au pluriel', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [1000000, 'un million'],
    [2000000, 'deux millions'],
    [1000000000, 'un milliard'],
    [3000000000, 'trois milliards'],
]);

// ── Les montants de l'état de référence N°0069/ANaTT/DAF/C-SFC ──

it('convertit les montants réels de l’état de paiement', function (int $n, string $attendu) {
    expect(MontantEnLettres::convertir($n))->toBe($attendu);
})->with([
    [440000, 'quatre cent quarante mille'],            // total reliquat
    [880000, 'huit cent quatre-vingt mille'],          // total avance
    [1320000, 'un million trois cent vingt mille'],    // TOTAL général
    [210000, 'deux cent dix mille'],
    [135000, 'cent trente-cinq mille'],
]);

it('produit la mention d’arrêt complète', function () {
    expect(MontantEnLettres::mention(1320000))
        ->toBe('Un million trois cent vingt mille (1 320 000) FRANCS CFA');
});

it('gère un montant nul', function () {
    expect(MontantEnLettres::mention(0))->toBe('Zéro (0) FRANCS CFA');
});
