<?php

use App\Models\Bareme;
use App\Models\OrdreMission;
use App\Models\User;

/*
 * Invariant métier « EXT_ZONE_II = EXT_ZONE_III » : les deux zones partagent les
 * mêmes taux. Toute création / modification / suppression d'un barème sur l'une
 * doit se répercuter sur sa zone jumelle.
 */

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->cle = ['categorie' => 'INDICE_SUP_800', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'];
});

it('crée le jumeau EXT_ZONE_III en créant un barème EXT_ZONE_II', function () {
    $this->actingAs($this->admin)->postJson('/api/baremes', [
        ...$this->cle, 'zone_code' => 'EXT_ZONE_II', 'taux_journalier' => 60000,
    ])->assertStatus(201);

    $this->assertDatabaseHas('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_II', 'taux_journalier' => 60000]);
    $this->assertDatabaseHas('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_III', 'taux_journalier' => 60000]);
});

it('propage la modification d’un taux EXT_ZONE_II vers EXT_ZONE_III', function () {
    $ii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_II', 'taux_journalier' => 50000]);
    $iii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_III', 'taux_journalier' => 50000]);

    $this->actingAs($this->admin)->putJson("/api/baremes/{$ii->id}", ['taux_journalier' => 70000])->assertOk();

    expect($iii->fresh()->taux_journalier)->toBe(70000);
});

it('propage aussi dans l’autre sens (EXT_ZONE_III → EXT_ZONE_II)', function () {
    $ii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_II', 'taux_journalier' => 50000]);
    $iii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_III', 'taux_journalier' => 50000]);

    $this->actingAs($this->admin)->putJson("/api/baremes/{$iii->id}", ['taux_journalier' => 80000])->assertOk();

    expect($ii->fresh()->taux_journalier)->toBe(80000);
});

it('supprime conjointement le jumeau', function () {
    $ii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_II']);
    Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_III']);

    $this->actingAs($this->admin)->deleteJson("/api/baremes/{$ii->id}")->assertOk();

    $this->assertDatabaseMissing('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_II']);
    $this->assertDatabaseMissing('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_III']);
});

it('bloque la suppression si le jumeau est utilisé par une mission en cours', function () {
    $ii = Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_II']);
    Bareme::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_III']);
    // Une mission en cours s'appuie sur le jumeau EXT_ZONE_III.
    OrdreMission::factory()->create([...$this->cle, 'zone_code' => 'EXT_ZONE_III', 'statut' => 'IMPORTE']);

    $this->actingAs($this->admin)->deleteJson("/api/baremes/{$ii->id}")->assertStatus(422);

    $this->assertDatabaseHas('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_II']);
    $this->assertDatabaseHas('baremes', [...$this->cle, 'zone_code' => 'EXT_ZONE_III']);
});

it('n’a aucun effet croisé pour une zone sans jumelle (INTERIEUR)', function () {
    $int = Bareme::factory()->create(['categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 40000]);

    $this->actingAs($this->admin)->putJson("/api/baremes/{$int->id}", ['taux_journalier' => 42000])->assertOk();

    // Aucune ligne EXT créée ; le barème reste seul.
    expect(Bareme::count())->toBe(1);
});
