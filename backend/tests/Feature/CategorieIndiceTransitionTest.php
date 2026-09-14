<?php

use App\Models\Agent;
use App\Models\Bareme;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verrou de la bascule ORDRE_1/2/3 → catégories d'indice nominatives.
 *
 * Deux exigences distinctes :
 *  1. l'application ne PRODUIT plus que le vocabulaire courant ;
 *  2. elle ACCEPTE encore l'ancien en entrée — fichiers d'import déjà préparés
 *     par l'ANaTT, filtres en favori, intégrations existantes.
 */
beforeEach(function () {
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

it('ne stocke plus que le vocabulaire courant en base', function () {
    foreach (['agents', 'baremes', 'ordre_missions'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    Agent::factory()->count(3)->create();

    expect(Agent::pluck('categorie')->unique()->diff(
        ['INDICE_SUP_800', 'INDICE_400_799', 'INDICE_INF_400']
    ))->toBeEmpty();
});

it('accepte un ancien code ORDRE_* à la création d’un agent et le traduit', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'SOUMANOU', 'prenoms' => 'Sabirou', 'matricule' => 'ANT-7001',
        'telephone' => '0190000701', 'operateur' => 'MTN',
        'indice' => 850, 'categorie' => 'ORDRE_1',
    ])->assertStatus(201)
        ->assertJsonPath('data.categorie', 'INDICE_SUP_800');

    expect(Agent::where('matricule', 'ANT-7001')->value('categorie'))->toBe('INDICE_SUP_800');
});

it('accepte un ancien code dans le filtre de la liste des agents', function () {
    Agent::factory()->create(['matricule' => 'ANT-7002', 'categorie' => 'INDICE_SUP_800']);
    Agent::factory()->create(['matricule' => 'ANT-7003', 'categorie' => 'INDICE_INF_400']);

    // Lien en favori d'avant la bascule : il doit continuer à fonctionner.
    $reponse = $this->actingAs($this->gestion)
        ->getJson('/api/agents?categorie=ORDRE_1')
        ->assertOk();

    $matricules = collect($reponse->json('data'))->pluck('matricule');

    expect($matricules)->toContain('ANT-7002')
        ->and($matricules)->not->toContain('ANT-7003');
});

it('accepte un ancien code dans le filtre du barème', function () {
    Bareme::factory()->create([
        'categorie' => 'INDICE_400_799', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 10000,
    ]);

    $this->actingAs($this->gestion)
        ->getJson('/api/baremes?categorie=ORDRE_2')
        ->assertOk()
        ->assertJsonPath('data.0.categorie', 'INDICE_400_799');
});

it('traduit les anciens codes d’un fichier d’import déjà préparé', function () {
    $csv = implode("\n", [
        'nom,prenoms,matricule,ifu,grade,indice,salaire_brut,categorie,telephone,operateur',
        'SOUMANOU,Sabirou,ANT-7101,,Cadre,850,520000,ORDRE_1,0197984877,MTN',
        'ABLESSOU,Sacre,ANT-7102,,Agent,350,150000,ORDRE_3,0155112233,MTN',
    ]);

    $fichier = UploadedFile::fake()->createWithContent('agents.csv', $csv);

    $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => $fichier])
        ->assertOk()
        ->assertJsonPath('data.importes', 2);

    expect(Agent::where('matricule', 'ANT-7101')->value('categorie'))->toBe('INDICE_SUP_800')
        ->and(Agent::where('matricule', 'ANT-7102')->value('categorie'))->toBe('INDICE_INF_400');
});

it('refuse un code de catégorie qui n’existe dans aucun des deux vocabulaires', function () {
    $this->actingAs($this->gestion)->postJson('/api/agents', [
        'nom' => 'X', 'prenoms' => 'Y', 'matricule' => 'ANT-7200',
        'telephone' => '0190000720', 'operateur' => 'MTN',
        'indice' => 500, 'categorie' => 'ORDRE_9',
    ])->assertStatus(422)->assertJsonValidationErrors(['categorie']);
});

it('traduit les données existantes dans les deux sens, sans perte', function () {
    // La suite de tests joue la migration sur des tables vides : ce cas exerce
    // le cœur de la bascule — le déroulé « élargir / traduire / refermer » —
    // avec de vraies lignes, dans les deux sens.
    $migration = require database_path('migrations/2026_09_14_090000_renommer_categories_ordre_en_indice.php');

    $agent = Agent::factory()->create(['matricule' => 'ANT-8001', 'categorie' => 'INDICE_SUP_800']);
    $bareme = Bareme::factory()->create([
        'categorie' => 'INDICE_INF_400', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'UN_REPAS', 'taux_journalier' => 8500,
    ]);

    $categorie = fn (string $table, int $id) => DB::table($table)->where('id', $id)->value('categorie');

    // Retour arrière : le vocabulaire hérité est restitué à l'identique.
    $migration->down();
    expect($categorie('agents', $agent->id))->toBe('ORDRE_1')
        ->and($categorie('baremes', $bareme->id))->toBe('ORDRE_3');

    // Bascule : traduction complète, et le taux du barème n'a pas bougé.
    $migration->up();
    expect($categorie('agents', $agent->id))->toBe('INDICE_SUP_800')
        ->and($categorie('baremes', $bareme->id))->toBe('INDICE_INF_400')
        ->and(DB::table('baremes')->where('id', $bareme->id)->value('taux_journalier'))->toBe(8500);
});
