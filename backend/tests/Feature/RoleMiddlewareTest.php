<?php

use App\Models\OrdreMission;
use App\Models\User;

function agentPayload(array $overrides = []): array
{
    return array_merge([
        'nom' => 'TEST',
        'prenoms' => 'Utilisateur',
        'matricule' => 'ANT-'.uniqid(),
        'telephone' => '0190000000',   // 019X -> MTN
        'operateur' => 'MTN',
        'indice' => 850,
    ], $overrides);
}

// ── SUPER_ADMIN : accès implicite à tout (Gate::before) ──
it('super_admin accède à tout', function () {
    $su = User::factory()->superAdmin()->create();

    $this->actingAs($su)->getJson('/api/audit')->assertOk();
    $this->actingAs($su)->getJson('/api/admin/users')->assertOk();
    $this->actingAs($su)->postJson('/api/agents', agentPayload())->assertStatus(201);
    $this->actingAs($su)->getJson('/api/admin/settings')->assertOk();           // parametres.gerer (SU only)
    $this->actingAs($su)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_3', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 8500,
    ])->assertStatus(201);
});

// ── ADMIN : utilisateurs + barème + agents, mais ni retranscription ni paiements ni validation ──
it('admin gère agents, barème, utilisateurs', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/agents', agentPayload())->assertStatus(201);
    $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();
    $this->actingAs($admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_2', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 10000,
    ])->assertStatus(201);
});

it('admin ne peut ni retranscrire, ni payer, ni accéder aux paramètres', function () {
    $admin = User::factory()->admin()->create();
    $ordre = OrdreMission::factory()->create();

    $this->actingAs($admin)->postJson('/api/missions', [])->assertStatus(403);
    $this->actingAs($admin)->postJson('/api/paiements/lots/generer', ['mission_id' => 1, 'tranche' => 'AVANCE', 'operateur' => 'MTN'])->assertStatus(403);
    $this->actingAs($admin)->postJson("/api/ordres/{$ordre->id}/valider")->assertStatus(403);
    $this->actingAs($admin)->getJson('/api/admin/settings')->assertStatus(403);
});

// ── GESTIONNAIRE_OM : retranscription + agents ; pas de validation ni paiement ──
it('gestionnaire_om retranscrit mais ne valide pas', function () {
    $u = User::factory()->gestionnaireOm()->create();
    $ordre = OrdreMission::factory()->create();

    $this->actingAs($u)->postJson('/api/missions', [])->assertStatus(422);   // garde franchie (422 = validation)
    $this->actingAs($u)->postJson('/api/agents', agentPayload())->assertStatus(201);
    $this->actingAs($u)->postJson("/api/ordres/{$ordre->id}/valider")->assertStatus(403);
});

// ── AGENT_FINANCIER : paiements ; pas de validation ni retranscription ──
it('agent_financier paie mais ne valide pas', function () {
    $u = User::factory()->agentFinancier()->create();
    $ordre = OrdreMission::factory()->create();

    $this->actingAs($u)->postJson('/api/paiements/lots/generer', ['mission_id' => 999999, 'tranche' => 'AVANCE', 'operateur' => 'MTN'])->assertStatus(422);
    $this->actingAs($u)->postJson("/api/ordres/{$ordre->id}/valider")->assertStatus(403);
    $this->actingAs($u)->postJson('/api/agents', agentPayload())->assertStatus(403); // pas personnel.gerer
});

// ── CONTROLEUR : validation + audit ; pas de retranscription ni paiement ──
it('controleur valide les OM et consulte l’audit', function () {
    $u = User::factory()->controleur()->create();
    $ordre = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->actingAs($u)->postJson("/api/ordres/{$ordre->id}/valider")->assertOk();
    $this->actingAs($u)->getJson('/api/audit')->assertOk();
    $this->actingAs($u)->postJson('/api/missions', [])->assertStatus(403);
});

// ── LECTEUR : consultation OM/rapports uniquement ──
it('lecteur ne consulte que OM et rapports', function () {
    $u = User::factory()->lecteur()->create();

    $this->actingAs($u)->getJson('/api/ordres')->assertOk();
    $this->actingAs($u)->getJson('/api/rapports/mensuel')->assertOk();
    $this->actingAs($u)->getJson('/api/agents')->assertStatus(403);      // pas personnel.consulter
    $this->actingAs($u)->getJson('/api/paiements/lots')->assertStatus(403);
    $this->actingAs($u)->getJson('/api/baremes')->assertStatus(403);
    $this->actingAs($u)->getJson('/api/audit')->assertStatus(403);
});
