<?php

use App\Models\AuditLog;
use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;

/*
 * ÉTAT DE PAIEMENT d'une mission — l'objet que le circuit valide réellement.
 *
 *   récapitulatif ──▶ BROUILLON ──contrôle──▶ CONTROLE ──DAF──▶ VISA_DAF
 *                 ──DG (final)──▶ VALIDE  →  les fichiers de paiement s'ouvrent
 *
 * Une signature unique sur l'état vaut pour ses N bénéficiaires.
 */

beforeEach(function () {
    config(['workflow.double_validation' => true]);

    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    $this->daf = User::factory()->daf()->create(['name' => 'Euloge D. ATOHOUN']);
    $this->dg = User::factory()->dg()->create(['name' => 'Richard DADA']);

    $this->mission = Mission::factory()->create(['objet' => 'Vérification des hangars']);

    // Deux bénéficiaires : 210 000 et 135 000, comme sur l'état de référence.
    $this->om1 = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'IMPORTE',
        'montant_total' => 210000, 'avance' => 147000, 'reliquat' => 63000,
    ]);
    $this->om2 = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'IMPORTE',
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
});

/** Amène l'état jusqu'au statut demandé. */
function conduire(object $t, string $jusqua): EtatPaiement
{
    $t->actingAs($t->gestionnaire)->postJson("/api/missions/{$t->mission->id}/etat/recapituler")->assertOk();
    $etat = EtatPaiement::where('mission_id', $t->mission->id)->firstOrFail();

    if ($jusqua === 'BROUILLON') {
        return $etat;
    }

    $t->actingAs($t->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    if ($jusqua === 'CONTROLE') {
        return $etat->fresh();
    }

    $t->actingAs($t->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    if ($jusqua === 'VISA_DAF') {
        return $etat->fresh();
    }

    $t->actingAs($t->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => '0069/ANaTT/DAF/C-SFC'])->assertOk();
    $t->actingAs($t->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    return $etat->fresh();
}

// ── Récapitulatif ──

it('établit le récapitulatif en agrégeant les OM de la mission', function () {
    $this->actingAs($this->gestionnaire)
        ->postJson("/api/missions/{$this->mission->id}/etat/recapituler")
        ->assertOk()
        ->assertJsonPath('data.statut', 'BROUILLON')
        ->assertJsonPath('data.nb_beneficiaires', 2)
        ->assertJsonPath('data.montant_total', 345000)      // 210 000 + 135 000
        ->assertJsonPath('data.montant_avance', 241500)     // 147 000 + 94 500
        ->assertJsonPath('data.montant_reliquat', 103500);  // 63 000 + 40 500
});

it('exprime le total général en lettres, avance et reliquat confondus', function () {
    $this->actingAs($this->gestionnaire)
        ->postJson("/api/missions/{$this->mission->id}/etat/recapituler")
        ->assertOk()
        ->assertJsonPath('data.montant_total_lettres', 'Trois cent quarante-cinq mille (345 000) FRANCS CFA');
});

it('refuse un récapitulatif sur une mission sans OM', function () {
    $vide = Mission::factory()->create();

    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$vide->id}/etat/recapituler")
        ->assertStatus(422);
});

it('refuse de rafraîchir un récapitulatif déjà signé', function () {
    $etat = conduire($this, 'CONTROLE');

    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$this->mission->id}/etat/recapituler")
        ->assertStatus(422);

    expect($etat->fresh()->statut)->toBe('CONTROLE');
});

// ── Le circuit ──

it('déroule contrôle, visa DAF puis validation finale du DG', function () {
    $etat = conduire($this, 'VALIDE');

    expect($etat->statut)->toBe('VALIDE')
        ->and($etat->controle_nom)->toBe('Paul AGBO')
        ->and($etat->visa_daf_nom)->toBe('Euloge D. ATOHOUN')
        ->and($etat->valide_dg_nom)->toBe('Richard DADA')
        ->and($etat->signature_controle_hash)->toHaveLength(64)
        ->and($etat->signature_daf_hash)->toHaveLength(64)
        ->and($etat->signature_dg_hash)->toHaveLength(64);
});

it('laisse les OM à leur statut SEMO jusqu’à la validation finale', function () {
    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$this->mission->id}/etat/recapituler")->assertOk();
    $etat = EtatPaiement::where('mission_id', $this->mission->id)->firstOrFail();

    $statuts = fn () => OrdreMission::where('mission_id', $this->mission->id)->pluck('statut')->unique()->values()->all();

    // Le circuit se joue sur l'état : les OM restent IMPORTE (leur statut de
    // retranscription SEMO) pendant le contrôle et le visa du DAF.
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    expect($statuts())->toBe(['IMPORTE']);

    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    expect($statuts())->toBe(['IMPORTE']);

    // Seule la validation finale du DG les fait passer à VALIDE (payables).
    $this->actingAs($this->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'X-1'])->assertOk();
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();
    expect($statuts())->toBe(['VALIDE']);
});

it('contraint l’ordre des étapes', function () {
    $etat = conduire($this, 'BROUILLON');

    // Le DAF ne peut pas viser un état non contrôlé.
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertStatus(422);
    // Le DG ne peut pas valider un état non visé.
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertStatus(422);

    expect($etat->fresh()->statut)->toBe('BROUILLON');
});

it('cloisonne les rôles sur chaque étape', function () {
    $etat = conduire($this, 'CONTROLE');

    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/viser")->assertForbidden();
    $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/viser")->assertForbidden();

    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/approuver")->assertForbidden();
});

// ── Numérotation manuelle ──

it('exige un numéro avant la validation finale', function () {
    $etat = conduire($this, 'VISA_DAF');

    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")
        ->assertStatus(422)
        ->assertJsonValidationErrors('numero');

    expect($etat->fresh()->statut)->toBe('VISA_DAF');
});

it('n’attribue jamais deux fois le même numéro', function () {
    $etat = conduire($this, 'VALIDE');

    // Un second état, sur une autre mission.
    $autre = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $autre->id, 'statut' => 'IMPORTE']);
    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$autre->id}/etat/recapituler")->assertOk();
    $etat2 = EtatPaiement::where('mission_id', $autre->id)->firstOrFail();

    $this->actingAs($this->daf)->putJson("/api/etats/{$etat2->id}/numero", ['numero' => $etat->numero])
        ->assertStatus(422)
        ->assertJsonValidationErrors('numero');
});

it('fige le numéro après la validation finale', function () {
    $etat = conduire($this, 'VALIDE');

    $this->actingAs($this->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'AUTRE-1'])
        ->assertStatus(422);

    expect($etat->fresh()->numero)->toBe('0069/ANaTT/DAF/C-SFC');
});

// ── Séparation des tâches ──

it('empêche le même signataire de viser puis de valider l’état', function () {
    $cumul = User::factory()->superAdmin()->create();
    $etat = conduire($this, 'CONTROLE');

    $this->actingAs($cumul)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $this->actingAs($cumul)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'Y-1'])->assertOk();

    $this->actingAs($cumul)->postJson("/api/etats/{$etat->id}/approuver")
        ->assertStatus(422)
        ->assertJsonValidationErrors('signataire');
});

// ── Le verrouillage ouvre le paiement ──

it('n’ouvre les fichiers de paiement qu’après la validation finale du DG', function () {
    Versement::factory()->create([
        'ordre_mission_id' => $this->om1->id, 'type' => 'AVANCE',
        'operateur' => 'MTN', 'statut' => 'EN_ATTENTE',
    ]);

    $payable = fn () => Versement::eligiblesLot($this->mission->id, 'AVANCE', 'MTN')->count();

    $etat = conduire($this, 'VISA_DAF');
    expect($payable())->toBe(0);   // visé DAF ne suffit pas

    $this->actingAs($this->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => 'Z-1'])->assertOk();
    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    expect($payable())->toBe(1);   // la validation finale ouvre le paiement
});

it('permet de générer l’avance puis le reliquat après validation', function () {
    foreach (['AVANCE', 'RELIQUAT'] as $tranche) {
        Versement::factory()->create([
            'ordre_mission_id' => $this->om1->id, 'type' => $tranche,
            'operateur' => 'MTN', 'statut' => 'EN_ATTENTE',
        ]);
    }

    conduire($this, 'VALIDE');

    expect(Versement::eligiblesLot($this->mission->id, 'AVANCE', 'MTN')->count())->toBe(1)
        ->and(Versement::eligiblesLot($this->mission->id, 'RELIQUAT', 'MTN')->count())->toBe(1);
});

// ── Rejet ──

it('autorise le rejet motivé à chaque étape et efface les signatures amont', function () {
    $etat = conduire($this, 'VISA_DAF');

    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/rejeter", [
        'motif' => 'Montants non conformes au barème en vigueur.',
    ])->assertOk()->assertJsonPath('data.statut', 'REJETE');

    $etat->refresh();

    expect($etat->controle_nom)->toBeNull()
        ->and($etat->visa_daf_nom)->toBeNull()
        ->and($etat->signature_daf_hash)->toBeNull()
        // Le rejet vise l'état, pas les OM : ceux-ci gardent leur statut SEMO.
        ->and(OrdreMission::where('mission_id', $this->mission->id)->pluck('statut')->unique()->all())->toBe(['IMPORTE']);
});

it('permet de refaire le récapitulatif après un rejet', function () {
    $etat = conduire($this, 'VISA_DAF');

    $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/rejeter", [
        'motif' => 'Montants à corriger avant nouvelle présentation.',
    ])->assertOk();

    // Un état rejeté n'est pas un cul-de-sac : on refait le brouillon.
    $this->actingAs($this->gestionnaire)->postJson("/api/missions/{$this->mission->id}/etat/recapituler")
        ->assertOk()
        ->assertJsonPath('data.statut', 'BROUILLON');

    expect($etat->fresh()->commentaire_rejet)->toBeNull();
});

// ── Cohabitation avec le circuit OM par OM ──

it('renvoie vers l’état lorsqu’on tente d’agir OM par OM', function () {
    $etat = conduire($this, 'BROUILLON');

    $this->actingAs($this->controleur)->postJson("/api/ordres/{$this->om1->id}/valider")
        ->assertStatus(422)
        ->assertJsonValidationErrors('etat');

    expect($etat->fresh()->statut)->toBe('BROUILLON');
});

// ── File de travail (écran d'entrée du circuit) ──

it('liste les états avec le résumé de leur mission', function () {
    conduire($this, 'CONTROLE');

    $this->actingAs($this->daf)->getJson('/api/etats')
        ->assertOk()
        ->assertJsonPath('data.0.statut', 'CONTROLE')
        ->assertJsonPath('data.0.mission.objet', 'Vérification des hangars')
        ->assertJsonStructure(['data' => [['id', 'mission_id', 'mission', 'statut', 'montant_total']], 'meta']);
});

it('filtre la file de travail par statut', function () {
    conduire($this, 'CONTROLE');

    // Le DAF ne traite que les états contrôlés.
    $this->actingAs($this->daf)->getJson('/api/etats?statut=CONTROLE')
        ->assertOk()->assertJsonCount(1, 'data');

    // Rien n'attend encore le DG.
    $this->actingAs($this->dg)->getJson('/api/etats?statut=VISA_DAF')
        ->assertOk()->assertJsonCount(0, 'data');
});

it('recherche un état par numéro ou par objet de mission', function () {
    conduire($this, 'VALIDE');

    $this->actingAs($this->daf)->getJson('/api/etats?search=0069')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->daf)->getJson('/api/etats?search=hangars')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->daf)->getJson('/api/etats?search=introuvable')
        ->assertOk()->assertJsonCount(0, 'data');
});

// ── Journal d'audit ──

it('journalise le récapitulatif et les trois étapes', function () {
    $etat = conduire($this, 'VALIDE');

    $actions = AuditLog::where('entite', 'etat_paiement')
        ->where('entite_id', $etat->id)->pluck('action');

    foreach (['RECAP_ETAT', 'CONTROL_ETAT', 'VISA_DAF_ETAT', 'APPROVE_DG_ETAT', 'NUMERO_ETAT'] as $attendue) {
        expect($actions)->toContain($attendue);
    }
});
