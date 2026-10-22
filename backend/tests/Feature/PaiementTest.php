<?php

use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->finance = User::factory()->agentFinancier()->create();

    $this->mission = Mission::factory()->create(['objet' => 'Mission paiement', 'destination' => 'Bohicon']);
    $agent = Agent::factory()->mtn()->create(['nom' => 'DADA', 'prenoms' => 'Richard']);
    $this->om = OrdreMission::factory()->statut('VALIDE')->create([
        'mission_id' => $this->mission->id, 'agent_id' => $agent->id,
    ]);
    $this->versement = Versement::factory()->create([
        'ordre_mission_id' => $this->om->id, 'type' => 'AVANCE',
        'operateur' => 'MTN', 'telephone' => $agent->telephone, 'montant' => 45000, 'statut' => 'EN_ATTENTE',
    ]);

    $this->selection = ['mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'operateur' => 'MTN'];
});

it('prévisualise les bénéficiaires éligibles', function () {
    $this->actingAs($this->finance)->getJson('/api/paiements/lots/previsualiser?'.http_build_query($this->selection))
        ->assertOk()
        ->assertJsonPath('data.nb_lignes', 1)
        ->assertJsonPath('data.total', 45000);
});

it('génère le fichier et passe le lot en GENERE', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertOk()
        ->assertHeader('content-disposition');

    $lot = LotPaiement::first();
    expect($lot->statut)->toBe('GENERE')->and($lot->nb_lignes)->toBe(1);
    expect(Versement::find($this->versement->id)->statut)->toBe('GENERE');
});

it('confirme un lot et solde les versements', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection);
    $lot = LotPaiement::first();

    $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$lot->id}/confirmer")
        ->assertOk()
        ->assertJsonPath('data.statut', 'CONFIRME');

    $v = Versement::find($this->versement->id);
    expect($v->statut)->toBe('CONFIRME')->and($v->date_paiement)->not->toBeNull();
});

it('bloque la régénération d’un lot confirmé (anti-double-paiement)', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection);
    $lot = LotPaiement::first();
    $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$lot->id}/confirmer");

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertStatus(422);
});

it('marque un lot en erreur puis le régénère', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection);
    $lot = LotPaiement::first();

    $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$lot->id}/erreur")
        ->assertOk()->assertJsonPath('data.statut', 'ERREUR');

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)->assertOk();
    expect(LotPaiement::find($lot->id)->statut)->toBe('GENERE');
});

it('liste les lots de paiement', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection);

    $this->actingAs($this->finance)->getJson('/api/paiements/lots')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('expose la synthèse des statuts de versement', function () {
    $this->actingAs($this->finance)->getJson('/api/paiements/statuts')->assertOk();
});

it('exclut un OM non validé : ni prévisualisé ni payable', function () {
    // L'OM redevient IMPORTE (non validé par le CONTROLEUR).
    $this->om->update(['statut' => 'IMPORTE']);

    $this->actingAs($this->finance)->getJson('/api/paiements/lots/previsualiser?'.http_build_query($this->selection))
        ->assertOk()
        ->assertJsonPath('data.nb_lignes', 0)
        ->assertJsonPath('data.total', 0);

    // La génération échoue faute de bénéficiaire éligible.
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertStatus(422);

    expect(LotPaiement::count())->toBe(0);
    expect(Versement::find($this->versement->id)->statut)->toBe('EN_ATTENTE');
});

it('ouvre un lot complémentaire pour un versement devenu payable après confirmation', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)->assertOk();
    $initial = LotPaiement::first();
    $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$initial->id}/confirmer")->assertOk();

    // Un second bénéficiaire devient payable APRÈS la confirmation du lot.
    $agent = Agent::factory()->mtn()->create(['nom' => 'AGBO']);
    $om = OrdreMission::factory()->statut('VALIDE')->create(['mission_id' => $this->mission->id, 'agent_id' => $agent->id]);
    $tardif = Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'AVANCE', 'operateur' => 'MTN',
        'telephone' => $agent->telephone, 'montant' => 30000, 'statut' => 'EN_ATTENTE',
    ]);

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)->assertOk();

    $compl = LotPaiement::where('sequence', 2)->firstOrFail();
    expect(LotPaiement::find($initial->id)->statut)->toBe('CONFIRME')   // jamais réécrit
        ->and($compl->nb_lignes)->toBe(1)
        ->and($compl->montant_total)->toBe(30000)
        ->and(basename($compl->nom_fichier))->toEndWith('_AVANCE_COMPL2.txt')
        ->and($tardif->fresh()->lot_id)->toBe($compl->id)
        ->and($this->versement->fresh()->lot_id)->toBe($initial->id);   // pas réinclus
});

it('remet les versements d’un lot en erreur EN_ATTENTE avec le numéro corrigé de l’agent', function () {
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection);
    $lot = LotPaiement::first();

    // Le dépôt échoue sur un mauvais numéro : on corrige l'agent.
    $this->om->agent->update(['telephone' => '0197000001']);
    $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$lot->id}/erreur")->assertOk();

    $v = $this->versement->fresh();
    expect($v->statut)->toBe('EN_ATTENTE')->and($v->telephone)->toBe('0197000001');

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)->assertOk();
    expect(Storage::disk('local')->get(LotPaiement::first()->nom_fichier))->toContain('2290197000001');
});
