<?php

use App\Models\AuditLog;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * Circuit de validation complet en trois étapes :
 *
 *   IMPORTE ──CONTROLEUR──▶ VERIFIE ──DAF──▶ VISA_DAF ──DG──▶ VALIDE (verrouillé)
 *
 * La validation du DG est la validation FINALE : c'est elle qui verrouille l'OM
 * et le rend payable.
 */

beforeEach(function () {
    // Ces tests portent sur le circuit complet : on active le flag.
    config(['workflow.double_validation' => true]);

    $this->controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    $this->daf = User::factory()->daf()->create(['name' => 'Euloge D. ATOHOUN']);
    $this->dg = User::factory()->dg()->create(['name' => 'Richard DADA']);
});

// ── Le chemin nominal ──

it('déroule les trois étapes jusqu’au verrouillage par le DG', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    // Étape 1 — le contrôleur vérifie : l'OM ne devient PAS validé.
    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()->assertJsonPath('data.statut', 'VERIFIE');

    // Étape 2 — le DAF vise.
    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")
        ->assertOk()->assertJsonPath('data.statut', 'VISA_DAF');

    // Étape 3 — le DG valide définitivement.
    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/approuver")
        ->assertOk()->assertJsonPath('data.statut', 'VALIDE');

    $om->refresh();

    // Les trois intervenants sont tracés, avec instantané du nom et scellé.
    expect($om->verifie_nom)->toBe('Paul AGBO')
        ->and($om->visa_daf_nom)->toBe('Euloge D. ATOHOUN')
        ->and($om->valide_dg_nom)->toBe('Richard DADA')
        ->and($om->signature_verif_hash)->toHaveLength(64)
        ->and($om->signature_daf_hash)->toHaveLength(64)
        ->and($om->signature_dg_hash)->toHaveLength(64)
        ->and($om->verifie_le)->not->toBeNull()
        ->and($om->visa_daf_le)->not->toBeNull()
        ->and($om->valide_dg_le)->not->toBeNull();
});

// ── L'ordre des étapes est contraint ──

it('refuse le visa DAF sur un OM non encore vérifié', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")
        ->assertStatus(422);

    expect($om->fresh()->statut)->toBe('IMPORTE');
});

it('refuse la validation finale sur un OM non visé par le DAF', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VERIFIE']);

    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/approuver")
        ->assertStatus(422);

    expect($om->fresh()->statut)->toBe('VERIFIE');
});

// ── Cloisonnement des rôles ──

it('interdit au DAF de prononcer la validation finale', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VISA_DAF']);

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/approuver")
        ->assertForbidden();
});

it('interdit au DG d’apposer le visa DAF', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VERIFIE']);

    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/viser")
        ->assertForbidden();
});

it('interdit au contrôleur de viser ou de valider définitivement', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VERIFIE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/viser")
        ->assertForbidden();
    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/approuver")
        ->assertForbidden();
});

// ── Séparation des tâches ──

it('empêche une même personne de viser puis de valider le même OM', function () {
    // Cas d'un compte cumulant les deux permissions (super admin, ou cumul de fonctions).
    $cumul = User::factory()->superAdmin()->create();
    $om = OrdreMission::factory()->create(['statut' => 'VERIFIE']);

    $this->actingAs($cumul)->postJson("/api/ordres/{$om->id}/viser")
        ->assertOk()->assertJsonPath('data.statut', 'VISA_DAF');

    // La validation finale lui est refusée : il a déjà signé cet OM.
    $this->actingAs($cumul)->postJson("/api/ordres/{$om->id}/approuver")
        ->assertStatus(422);

    expect($om->fresh()->statut)->toBe('VISA_DAF');
});

// ── Rejet à chaque étape ──

it('autorise le rejet motivé à chacune des trois étapes', function () {
    foreach ([
        ['IMPORTE', $this->controleur],
        ['VERIFIE', $this->daf],
        ['VISA_DAF', $this->dg],
    ] as [$statut, $acteur]) {
        $om = OrdreMission::factory()->create(['statut' => $statut]);

        $this->actingAs($acteur)->postJson("/api/ordres/{$om->id}/rejeter", [
            'motif' => 'Pièces justificatives incomplètes au dossier.',
        ])->assertOk()->assertJsonPath('data.statut', 'REJETE');
    }
});

it('efface les signatures amont lorsqu’un OM est rejeté', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")->assertOk();

    // Le DG rejette : les visas portaient sur un OM désormais rejeté.
    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/rejeter", [
        'motif' => 'Montants non conformes au barème en vigueur.',
    ])->assertOk();

    $om->refresh();

    expect($om->statut)->toBe('REJETE')
        ->and($om->verifie_nom)->toBeNull()
        ->and($om->visa_daf_nom)->toBeNull()
        ->and($om->signature_verif_hash)->toBeNull()
        ->and($om->signature_daf_hash)->toBeNull()
        ->and($om->commentaire_rejet)->toContain('barème');
});

// ── Le verrouillage est bien la porte du paiement ──

it('ne rend l’OM payable qu’après la validation finale du DG', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id,
        'type' => 'AVANCE',
        'operateur' => 'MTN',
        'statut' => 'EN_ATTENTE',
    ]);

    $payable = fn () => Versement::eligiblesLot($om->mission_id, 'AVANCE', 'MTN')->count();

    expect($payable())->toBe(0);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();
    expect($payable())->toBe(0);   // vérifié ne suffit pas

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")->assertOk();
    expect($payable())->toBe(0);   // visé DAF ne suffit pas non plus

    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/approuver")->assertOk();
    expect($payable())->toBe(1);   // seule la validation finale du DG ouvre le paiement
});

// ── Les étapes DAF/DG n'existent pas hors du circuit complet ──

it('renvoie une erreur claire si le circuit complet est désactivé', function () {
    config(['workflow.double_validation' => false]);

    $om = OrdreMission::factory()->create(['statut' => 'VERIFIE']);

    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")
        ->assertStatus(422)
        ->assertJsonValidationErrors('workflow');
});

it('conserve la validation en une étape quand le circuit complet est désactivé', function () {
    config(['workflow.double_validation' => false]);

    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    // Comportement historique strictement préservé.
    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")
        ->assertOk()->assertJsonPath('data.statut', 'VALIDE');
});

// ── Journal d'audit ──

it('journalise chacune des trois étapes du circuit', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$om->id}/valider")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/ordres/{$om->id}/viser")->assertOk();
    $this->actingAs($this->dg)->postJson("/api/ordres/{$om->id}/approuver")->assertOk();

    $actions = AuditLog::where('entite', 'ordre_mission')
        ->where('entite_id', $om->id)
        ->pluck('action');

    expect($actions)->toContain('VERIFY_OM')
        ->and($actions)->toContain('VISA_DAF_OM')
        ->and($actions)->toContain('APPROVE_DG_OM');
});
