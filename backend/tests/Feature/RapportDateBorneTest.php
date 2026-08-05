<?php

use App\Models\User;
use App\Models\Versement;
use Carbon\Carbon;

/*
 * Borne « date_fin » des rapports : date_paiement est un TIMESTAMP, donc un
 * filtre en date seule doit couvrir la JOURNÉE ENTIÈRE (jusqu'à 23:59:59) et
 * non s'arrêter à minuit — sinon les paiements du dernier jour disparaissent.
 */

beforeEach(function () {
    $this->lecteur = User::factory()->lecteur()->create();
});

it('inclut un versement confirmé en journée le dernier jour de la plage', function () {
    // Paiement confirmé le 31/01 à 14h00 : hors plage si date_fin vaut minuit.
    Versement::factory()->confirme()->create([
        'montant' => 94500,
        'date_paiement' => Carbon::parse('2026-01-31 14:00:00'),
    ]);

    $this->actingAs($this->lecteur)
        ->getJson('/api/rapports/agents?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        // Agrégats non castés en int côté service : comparaison tolérante au driver.
        ->assertJsonPath('data.0.nb_versements', fn ($v) => (int) $v === 1)
        ->assertJsonPath('data.0.montant_total', fn ($v) => (int) $v === 94500);
});

it('exclut un versement confirmé après la borne de fin', function () {
    // Le 01/02 à 00h01 : hors plage janvier, même après normalisation en fin de jour.
    Versement::factory()->confirme()->create([
        'montant' => 94500,
        'date_paiement' => Carbon::parse('2026-02-01 00:01:00'),
    ]);

    $this->actingAs($this->lecteur)
        ->getJson('/api/rapports/agents?date_debut=2026-01-01&date_fin=2026-01-31')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
