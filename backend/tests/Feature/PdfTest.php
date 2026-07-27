<?php

use App\Models\OrdreMission;
use App\Models\User;
use App\Services\PdfGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->lecteur()->create();
    $this->om = OrdreMission::factory()->create();
});

it('génère le PDF d’un OM et renvoie son chemin', function () {
    $this->actingAs($this->user)->getJson("/api/ordres/{$this->om->id}/pdf")
        ->assertOk()
        ->assertJsonStructure(['data' => ['url', 'fichier']]);

    // Le chemin de cache porte l'état du document : un brouillon et un document
    // signé ne peuvent jamais s'écraser mutuellement.
    $slug = Str::slug($this->om->reference_semo);
    expect(Storage::disk('local')->exists("ordres/OM-{$slug}-{$this->om->statut}.pdf"))->toBeTrue();
});

it('télécharge un PDF valide', function () {
    $response = $this->actingAs($this->user)->get("/api/ordres/{$this->om->id}/pdf/download");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
});

// ── Brouillon (épreuve téléchargeable avant validation) ──

it('télécharge un brouillon d’OM avant toute validation', function () {
    $response = $this->actingAs($this->user)->get("/api/ordres/{$this->om->id}/pdf/brouillon");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect(substr($response->streamedContent(), 0, 5))->toBe('%PDF-');
});

it('écrit le brouillon dans un fichier distinct du PDF officiel', function () {
    $slug = Str::slug($this->om->reference_semo);

    $this->actingAs($this->user)->get("/api/ordres/{$this->om->id}/pdf/brouillon")->assertOk();

    // Le brouillon existe, et n'a PAS écrasé le document officiel de l'étape courante.
    expect(Storage::disk('local')->exists("ordres/OM-{$slug}-BROUILLON.pdf"))->toBeTrue()
        ->and(Storage::disk('local')->exists("ordres/OM-{$slug}-{$this->om->statut}.pdf"))->toBeFalse();
});

it('ne fait jamais apparaître de signature sur un brouillon', function () {
    // OM déjà signé par le DAF : le brouillon doit malgré tout rester vierge de visa.
    $signataire = User::factory()->create(['name' => 'Euloge D. ATOHOUN']);
    $this->om->update([
        'visa_daf_par' => $signataire->id,
        'visa_daf_le' => now(),
        'visa_daf_nom' => 'Euloge D. ATOHOUN',
        'visa_daf_fonction' => "Directeur de l'Administration et des Finances",
        'signature_daf_hash' => str_repeat('a', 64),
    ]);

    $genere = app(PdfGenerator::class);
    $om = $this->om->fresh();

    // Le document officiel porte le visa…
    $officiel = $genere->donnees($om, brouillon: false);
    expect($officiel['visa_daf'])->not->toBeNull()
        ->and($officiel['visa_daf']['nom'])->toBe('Euloge D. ATOHOUN')
        ->and($officiel['visa_daf']['code'])->not->toBeNull();

    // …le brouillon, jamais, et il s'annonce comme provisoire.
    $brouillon = $genere->donnees($om, brouillon: true);
    expect($brouillon['visa_daf'])->toBeNull()
        ->and($brouillon['valide_dg'])->toBeNull()
        ->and($brouillon['brouillon'])->toBeTrue();
});

it('produit un brouillon et un document officiel de contenus différents', function () {
    $genere = app(PdfGenerator::class);

    $genere->genererOrdreMission($this->om, brouillon: true);
    $genere->genererOrdreMission($this->om);

    $slug = Str::slug($this->om->reference_semo);
    $disque = Storage::disk('local');

    // Le filigrane est apposé sur le seul brouillon : les octets diffèrent.
    expect($disque->get("ordres/OM-{$slug}-BROUILLON.pdf"))
        ->not->toBe($disque->get("ordres/OM-{$slug}-{$this->om->statut}.pdf"));
});
