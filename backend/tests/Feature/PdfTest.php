<?php

use App\Models\OrdreMission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->lecteur()->create();
    $this->om = OrdreMission::factory()->create();
});

it('génère le PDF d’un OM et renvoie son chemin', function () {
    $this->actingAs($this->user)->getJson("/api/ordres/{$this->om->id}/pdf")
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'fichier']]);

    expect(Storage::disk('local')->exists("ordres/OM-{$this->om->reference_semo}.pdf"))->toBeTrue();
});

it('télécharge un PDF valide', function () {
    $response = $this->actingAs($this->user)->get("/api/ordres/{$this->om->id}/pdf/download");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
});
