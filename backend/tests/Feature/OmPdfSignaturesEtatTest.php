<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Services\PdfGenerator;
use App\Support\Workflow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * PDF de l'OM sur le circuit par ÉTAT : les trois signatures vivent sur l'état
 * des avances, pas sur l'OM. Le PDF de l'OM les reprend, pour qu'un OM validé
 * par l'état n'affiche plus ses cases « en attente ». Cf. docs/workflows-par-profil.md §5.
 */

beforeEach(function () {
    Storage::fake('local');

    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    $this->daf = User::factory()->daf()->create(['name' => 'Euloge D. ATOHOUN']);
    $this->dg = User::factory()->dg()->create(['name' => 'Richard DADA']);

    $this->mission = Mission::factory()->create();
    $this->om = OrdreMission::factory()->create(['mission_id' => $this->mission->id, 'statut' => 'IMPORTE']);
    $this->rejete = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id, 'statut' => 'REJETE', 'commentaire_rejet' => 'Pièce justificative manquante.',
    ]);

    $this->pdf = app(PdfGenerator::class);
});

function signerEtatAvances(object $t, string $jusqua): EtatPaiement
{
    $t->actingAs($t->gestionnaire)->postJson("/api/missions/{$t->mission->id}/etat/recapituler")->assertOk();
    $etat = EtatPaiement::where('mission_id', $t->mission->id)->firstOrFail();

    $t->actingAs($t->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    if ($jusqua === 'CONTROLE') {
        return $etat->fresh();
    }

    $t->actingAs($t->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $t->actingAs($t->dg)->putJson("/api/etats/{$etat->id}/numero", ['numero' => '0042/ANaTT/DAF'])->assertOk();
    $t->actingAs($t->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    return $etat->fresh();
}

it('reprend sur le PDF de l’OM les trois signatures de l’état des avances validé', function () {
    $etat = signerEtatAvances($this, 'VALIDE');

    $d = $this->pdf->donnees($this->om->fresh());

    expect($d['verification']['nom'])->toBe('Paul AGBO')
        ->and($d['visa_daf']['nom'])->toBe('Euloge D. ATOHOUN')
        ->and($d['valide_dg']['nom'])->toBe('Richard DADA')
        // Même code de vérification que sur l'état : c'est le même scellé.
        ->and($d['valide_dg']['code'])->not->toBeNull()
        ->and($d['valide_dg']['code'])->toBe(Workflow::codeVerification($etat->signature_dg_hash));
});

it('reprend les signatures déjà apposées pendant le circuit, pas les suivantes', function () {
    signerEtatAvances($this, 'CONTROLE');

    $d = $this->pdf->donnees($this->om->fresh());

    expect($d['verification']['nom'])->toBe('Paul AGBO')
        ->and($d['visa_daf'])->toBeNull()
        ->and($d['valide_dg'])->toBeNull();
});

it('ne prête aucune signature d’état à un OM rejeté, absent de l’état signé', function () {
    signerEtatAvances($this, 'VALIDE');

    $d = $this->pdf->donnees($this->rejete->fresh());

    expect($d['verification'])->toBeNull()
        ->and($d['visa_daf'])->toBeNull()
        ->and($d['valide_dg'])->toBeNull();
});

it('ne montre jamais les signatures de l’état sur un brouillon d’OM', function () {
    signerEtatAvances($this, 'VALIDE');

    $d = $this->pdf->donnees($this->om->fresh(), brouillon: true);

    expect($d['verification'])->toBeNull()->and($d['valide_dg'])->toBeNull();
});

it('régénère le PDF en cache quand l’état progresse sans que l’OM change de statut', function () {
    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$this->mission->id}/etat/recapituler")->assertOk();
    $slug = Str::slug($this->om->reference_semo);

    // Avant tout contrôle : PDF généré et mis en cache.
    $this->actingAs($this->gestionnaire)->get("/api/ordres/{$this->om->id}/pdf/download")->assertOk();
    $avant = Storage::disk('local')->allFiles('ordres');

    // L'état est contrôlé : l'OM reste IMPORTE, mais son PDF doit refléter la signature.
    $etat = EtatPaiement::where('mission_id', $this->mission->id)->firstOrFail();
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs($this->gestionnaire)->get("/api/ordres/{$this->om->id}/pdf/download")->assertOk();

    expect(array_diff(Storage::disk('local')->allFiles('ordres'), $avant))->not->toBeEmpty()
        ->and(Storage::disk('local')->exists("ordres/OM-{$slug}-IMPORTE-ETAT-CONTROLE.pdf"))->toBeTrue();
});
