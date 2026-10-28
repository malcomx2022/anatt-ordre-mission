<?php

use App\Models\Agent;
use App\Models\EtatPaiement;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Parametres;
use Database\Seeders\BaremeSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\OpenApiSchema;

/*
 * Contrat des RÉPONSES : les JSON réels des principaux endpoints sont validés
 * contre les schémas déclarés dans docs/openapi.yaml (dette « Forme des réponses
 * non vérifiée en CI », revue 2026-10 §7.2). OpenApiTest vérifie que chaque route
 * est décrite avec sa permission ; ici, c'est la FORME des données qui est
 * verrouillée : type, nullabilité, énumérations, formats de date, et toute
 * propriété renvoyée sans être documentée (mode strict de Tests\Support\OpenApiSchema).
 *
 * Scénario unique, rejoué par l'API pour chaque test : retranscription de deux OM
 * (MTN + Moov), état des avances validé en trois signatures, lot MTN généré puis
 * confirmé, amendement réducteur en trop-perçu, état des reliquats en brouillon.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->seed(BaremeSeeder::class);

    $this->su = User::factory()->superAdmin()->create();
    $this->gestion = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();
    $this->finance = User::factory()->agentFinancier()->create();

    $this->agentMtn = Agent::factory()->categorie('INDICE_SUP_800')->mtn()->create();
    $this->agentMoov = Agent::factory()->categorie('INDICE_INF_400')->moov()->create();

    // Retranscription : 3 jours à l'intérieur, journée complète.
    $this->retranscription = $this->actingAs($this->gestion)->postJson('/api/missions', [
        'objet' => 'Contrôle des auto-écoles',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01',
        'date_retour' => '2026-06-03',
        'imputation_budgetaire' => 'BN-2026-62',
        'ordres' => [
            ['reference_semo' => 'SEMO-CTR-1', 'agent_id' => $this->agentMtn->id, 'role' => 'PARTICIPANT', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
            ['reference_semo' => 'SEMO-CTR-2', 'agent_id' => $this->agentMoov->id, 'role' => 'CAV', 'type_prise_en_charge' => 'JOURNEE_COMPLETE'],
        ],
    ])->assertCreated();

    $this->mission = Mission::latest('id')->firstOrFail();
    $this->omMtn = OrdreMission::where('reference_semo', 'SEMO-CTR-1')->firstOrFail();

    // État des avances : récapitulatif → contrôle → visa → numéro → validation DG.
    $this->actingAs($this->gestion)->postJson("/api/missions/{$this->mission->id}/etat/recapituler")->assertOk();
    $etat = EtatPaiement::where('mission_id', $this->mission->id)->firstOrFail();
    $this->circuit = [
        'POST /etats/{etat}/controler' => $this->actingAs($this->controleur)->postJson("/api/etats/{$etat->id}/controler"),
        'POST /etats/{etat}/viser' => $this->actingAs($this->daf)->postJson("/api/etats/{$etat->id}/viser"),
        'PUT /etats/{etat}/numero' => $this->actingAs($this->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => '0101/ANaTT/DAF/C-SFC']),
        'POST /etats/{etat}/approuver' => $this->actingAs($this->dg)->postJson("/api/etats/{$etat->id}/approuver"),
    ];
    foreach ($this->circuit as $reponse) {
        $reponse->assertOk();
    }

    // Lot MTN des avances, généré puis confirmé (le lot Moov reste à générer : aperçu non vide).
    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', [
        'mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'operateur' => 'MTN',
    ])->assertOk();
    $this->lot = LotPaiement::where('mission_id', $this->mission->id)->firstOrFail();
    $this->confirmation = $this->actingAs($this->finance)->postJson("/api/paiements/lots/{$this->lot->id}/confirmer")->assertOk();

    // Amendement réducteur : 1 jour au lieu de 3, l'avance payée dépasse le total → trop-perçu.
    $this->amendement = $this->actingAs($this->gestion)->postJson("/api/ordres/{$this->omMtn->id}/amender", [
        'nb_jours' => 1, 'motif' => 'Mission écourtée sur ordre du directeur.',
    ])->assertOk();

    // État des reliquats en brouillon.
    $this->recapReliquat = $this->actingAs($this->gestion)
        ->postJson("/api/missions/{$this->mission->id}/etat/recapituler", ['tranche' => 'RELIQUAT'])
        ->assertOk();

    Notification::factory()->create(['user_id' => $this->controleur->id]);
});

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

/**
 * Valide le corps JSON d'une réponse contre le schéma documenté de l'opération.
 * `$chemin` est le gabarit de la spécification (ex. `/ordres/{ordre}`).
 */
function respecteLeContrat(TestResponse $reponse, string $methode, string $chemin, int $statut = 200): void
{
    $reponse->assertStatus($statut);

    static $schema = null;
    $schema ??= OpenApiSchema::depuisFichier();

    $corps = json_decode($reponse->getContent(), false, 512, JSON_THROW_ON_ERROR);
    $ecarts = $schema->valider($corps, $schema->schemaReponse($methode, $chemin, $statut));

    expect($ecarts)->toBe([], "$methode $chemin ($statut) diverge de docs/openapi.yaml");
}

// ── Authentification ──

it('POST /auth/login respecte le contrat', function () {
    respecteLeContrat(
        $this->postJson('/api/auth/login', ['email' => $this->controleur->email, 'password' => 'password']),
        'POST', '/auth/login'
    );
});

it('GET /auth/me et /auth/me/permissions respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->controleur)->getJson('/api/auth/me'), 'GET', '/auth/me');
    respecteLeContrat($this->actingAs($this->controleur)->getJson('/api/auth/me/permissions'), 'GET', '/auth/me/permissions');
});

it('GET /notifications respecte le contrat', function () {
    $reponse = $this->actingAs($this->controleur)->getJson('/api/notifications');

    expect($reponse->json('data'))->not->toBeEmpty();
    respecteLeContrat($reponse, 'GET', '/notifications');
});

// ── Agents ──

it('GET /agents, /agents/stats et /agents/{agent} respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->gestion)->getJson('/api/agents'), 'GET', '/agents');
    respecteLeContrat($this->actingAs($this->gestion)->getJson('/api/agents/stats'), 'GET', '/agents/stats');
    respecteLeContrat($this->actingAs($this->gestion)->getJson("/api/agents/{$this->agentMtn->id}"), 'GET', '/agents/{agent}');
});

// ── Missions et ordres ──

it('POST /missions (retranscription) respecte le contrat', function () {
    respecteLeContrat($this->retranscription, 'POST', '/missions', 201);
});

it('GET /missions respecte le contrat', function () {
    respecteLeContrat($this->actingAs($this->gestion)->getJson('/api/missions'), 'GET', '/missions');
});

it('GET /ordres et /ordres/{ordre} respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->gestion)->getJson('/api/ordres'), 'GET', '/ordres');

    // OM amendé : versements (dont un trop-perçu) et amendements chargés.
    $fiche = $this->actingAs($this->gestion)->getJson("/api/ordres/{$this->omMtn->id}");
    expect($fiche->json('data.amendements'))->toHaveCount(1);
    respecteLeContrat($fiche, 'GET', '/ordres/{ordre}');
});

// ── États de paiement ──

it('POST /ordres/{ordre}/amender respecte le contrat', function () {
    expect($this->amendement->json('data.reliquat'))->toBeLessThan(0);   // trop-perçu
    respecteLeContrat($this->amendement, 'POST', '/ordres/{ordre}/amender');
});

it('les étapes du circuit de l’état (contrôle, visa, numéro, validation) respectent le contrat', function () {
    foreach ($this->circuit as $operation => $reponse) {
        [$methode, $chemin] = explode(' ', $operation);
        respecteLeContrat($reponse, $methode, $chemin);
    }
});

it('GET /missions/{mission}/etat respecte le contrat (deux tranches établies)', function () {
    $reponse = $this->actingAs($this->gestion)->getJson("/api/missions/{$this->mission->id}/etat");

    expect($reponse->json('data.avance.statut'))->toBe('VALIDE')
        ->and($reponse->json('data.reliquat.statut'))->toBe('BROUILLON');
    respecteLeContrat($reponse, 'GET', '/missions/{mission}/etat');
});

it('POST /missions/{mission}/etat/recapituler respecte le contrat', function () {
    respecteLeContrat($this->recapReliquat, 'POST', '/missions/{mission}/etat/recapituler');
});

it('GET /etats respecte le contrat', function () {
    respecteLeContrat($this->actingAs($this->controleur)->getJson('/api/etats'), 'GET', '/etats');
});

// ── Paiements ──

it('GET /paiements/lots/previsualiser respecte le contrat', function () {
    $reponse = $this->actingAs($this->finance)->getJson(
        "/api/paiements/lots/previsualiser?mission_id={$this->mission->id}&tranche=AVANCE&operateur=MOOV"
    );

    expect($reponse->json('data.beneficiaires'))->toHaveCount(1);
    respecteLeContrat($reponse, 'GET', '/paiements/lots/previsualiser');
});

it('GET /paiements/lots et POST /paiements/lots/{lot}/confirmer respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->finance)->getJson('/api/paiements/lots'), 'GET', '/paiements/lots');
    respecteLeContrat($this->confirmation, 'POST', '/paiements/lots/{lot}/confirmer');
});

it('GET /paiements/statuts et /paiements/historique respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->finance)->getJson('/api/paiements/statuts'), 'GET', '/paiements/statuts');
    respecteLeContrat($this->actingAs($this->finance)->getJson('/api/paiements/historique'), 'GET', '/paiements/historique');
});

it('GET /paiements/regularisations respecte le contrat', function () {
    $reponse = $this->actingAs($this->finance)->getJson('/api/paiements/regularisations');

    expect($reponse->json('data'))->toHaveCount(1);
    respecteLeContrat($reponse, 'GET', '/paiements/regularisations');
});

it('POST /paiements/regularisations/{versement}/recouvrer respecte le contrat', function () {
    $versement = $this->actingAs($this->finance)->getJson('/api/paiements/regularisations')->json('data.0.versement_id');

    respecteLeContrat(
        $this->actingAs($this->finance)->postJson("/api/paiements/regularisations/{$versement}/recouvrer"),
        'POST', '/paiements/regularisations/{versement}/recouvrer'
    );
});

// ── Barème, rapports, audit ──

it('GET /baremes respecte le contrat', function () {
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/baremes'), 'GET', '/baremes');
});

it('GET /rapports/mensuel respecte le contrat', function () {
    // Le lot a été confirmé maintenant : le mois courant porte des versements.
    $reponse = $this->actingAs($this->su)->getJson('/api/rapports/mensuel?mois='.now()->format('Y-m'));

    expect($reponse->json('data.synthese.nb_versements'))->toBeGreaterThan(0);
    respecteLeContrat($reponse, 'GET', '/rapports/mensuel');
});

it('GET /audit respecte le contrat', function () {
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/audit'), 'GET', '/audit');
});

// ── Administration et paramètres ──

it('GET /admin/users et /admin/roles respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/admin/users'), 'GET', '/admin/users');
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/admin/roles'), 'GET', '/admin/roles');
});

it('GET /admin/signatures respecte le contrat', function () {
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/admin/signatures'), 'GET', '/admin/signatures');
});

it('GET /admin/parametres/part-avance, /annee-fiscale et /application respectent le contrat', function () {
    respecteLeContrat($this->actingAs($this->dg)->getJson('/api/admin/parametres/part-avance'), 'GET', '/admin/parametres/part-avance');
    respecteLeContrat($this->actingAs($this->dg)->getJson('/api/admin/parametres/annee-fiscale'), 'GET', '/admin/parametres/annee-fiscale');
    respecteLeContrat($this->actingAs($this->dg)->getJson('/api/admin/parametres/application'), 'GET', '/admin/parametres/application');
});

it('GET et PUT /admin/settings respectent le contrat (super admin)', function () {
    respecteLeContrat($this->actingAs($this->su)->getJson('/api/admin/settings'), 'GET', '/admin/settings');

    $ecriture = $this->actingAs($this->su)->putJson('/api/admin/settings', [
        'appName' => 'ANaTT Missions', 'partAvance' => 70, 'fiscalYearStartMonth' => 1,
        'smtpPassword' => 's3cret', 'mtnEnabled' => true,
    ]);
    respecteLeContrat($ecriture, 'PUT', '/admin/settings');

    respecteLeContrat($this->actingAs($this->su)->getJson('/api/admin/settings'), 'GET', '/admin/settings');
});

// ── Le validateur lui-même : une dérive doit être détectée ──

it('signale une propriété non documentée, un type faux et une valeur hors énumération', function () {
    $schema = OpenApiSchema::depuisFichier();
    $lot = json_decode(json_encode([
        'id' => '12', 'statut' => 'PERDU', 'sequence' => 1, 'champ_fantome' => true, 'genere_le' => '28/10/2026',
    ]), false);

    $ecarts = $schema->valider($lot, ['$ref' => '#/components/schemas/LotPaiement']);

    expect($ecarts)->toContain('$.id : type attendu integer, reçu string ("12")')
        ->and(implode("\n", $ecarts))->toContain('$.statut : valeur "PERDU" hors énumération')
        ->and($ecarts)->toContain('$.champ_fantome : propriété non documentée')
        ->and(implode("\n", $ecarts))->toContain('$.genere_le : "28/10/2026" ne respecte pas le format date-time');
});

it('distingue integer de number et respecte nullable, oneOf et allOf', function () {
    $schema = OpenApiSchema::depuisFichier();

    expect($schema->valider(1.5, ['type' => 'integer']))->not->toBe([])
        ->and($schema->valider(1.5, ['type' => 'number']))->toBe([])
        ->and($schema->valider(null, ['type' => 'string']))->not->toBe([])
        ->and($schema->valider(null, ['type' => 'string', 'nullable' => true]))->toBe([])
        // EtatPaiement OU null (GET /missions/{mission}/etat)
        ->and($schema->valider(null, $schema->schemaReponse('GET', '/missions/{mission}/etat', 200)['properties']['data']['properties']['reliquat']))->toBe([])
        // allOf PaginationMeta + total_montant : les deux jeux de propriétés sont admis
        ->and($schema->valider(
            json_decode('{"current_page":1,"last_page":1,"per_page":15,"total":0,"total_montant":0}', false),
            $schema->schemaReponse('GET', '/paiements/historique', 200)['properties']['meta']
        ))->toBe([]);
});
