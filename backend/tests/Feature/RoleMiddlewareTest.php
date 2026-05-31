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

// ── admin a toujours accès ──
it('admin accède aux routes protégées', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/rapports/mensuel')->assertOk();
    $this->actingAs($admin)->getJson('/api/audit')->assertOk();
    $this->actingAs($admin)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_3', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 8500,
    ])->assertStatus(201);
});

// ── Agents : gestionnaire_om écrit, lecteur non ──
it('gestionnaire_om peut créer un agent', function () {
    $user = User::factory()->gestionnaireOm()->create();
    $this->actingAs($user)->postJson('/api/agents', agentPayload())->assertStatus(201);
});

it('lecteur ne peut pas créer un agent', function () {
    $user = User::factory()->lecteur()->create();
    $this->actingAs($user)->postJson('/api/agents', agentPayload())->assertStatus(403);
});

it('lecteur peut lire la liste des agents', function () {
    $user = User::factory()->lecteur()->create();
    $this->actingAs($user)->getJson('/api/agents')->assertOk();
});

// ── Validation OM : gestionnaire_om oui, lecteur non ──
it('gestionnaire_om peut valider un OM', function () {
    $user = User::factory()->gestionnaireOm()->create();
    $ordre = OrdreMission::factory()->create();
    $this->actingAs($user)->postJson("/api/ordres/{$ordre->id}/valider")->assertOk();
});

it('lecteur ne peut pas valider un OM', function () {
    $user = User::factory()->lecteur()->create();
    $ordre = OrdreMission::factory()->create();
    $this->actingAs($user)->postJson("/api/ordres/{$ordre->id}/valider")->assertStatus(403);
});

// ── Paiements : agent_financier oui (passe la garde), lecteur non ──
it('agent_financier passe la garde de génération de lot', function () {
    $user = User::factory()->agentFinancier()->create();
    // mission inexistante -> 422 (validation), mais PAS 403 : la garde de rôle est franchie.
    $this->actingAs($user)->postJson('/api/paiements/lots/generer', [
        'mission_id' => 999999, 'tranche' => 'AVANCE', 'operateur' => 'MTN',
    ])->assertStatus(422);
});

it('lecteur ne peut pas générer un lot', function () {
    $user = User::factory()->lecteur()->create();
    $this->actingAs($user)->postJson('/api/paiements/lots/generer', [
        'mission_id' => 1, 'tranche' => 'AVANCE', 'operateur' => 'MTN',
    ])->assertStatus(403);
});

// ── Barème : admin écrit, gestionnaire_om non ──
it('gestionnaire_om ne peut pas écrire le barème', function () {
    $user = User::factory()->gestionnaireOm()->create();
    $this->actingAs($user)->postJson('/api/baremes', [
        'categorie' => 'ORDRE_3', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 8500,
    ])->assertStatus(403);
});

// ── Rapports : tout authentifié ; Audit : admin only ──
it('lecteur peut voir les rapports', function () {
    $user = User::factory()->lecteur()->create();
    $this->actingAs($user)->getJson('/api/rapports/mensuel')->assertOk();
});

it('gestionnaire_om ne peut pas voir le journal audit', function () {
    $user = User::factory()->gestionnaireOm()->create();
    $this->actingAs($user)->getJson('/api/audit')->assertStatus(403);
});
