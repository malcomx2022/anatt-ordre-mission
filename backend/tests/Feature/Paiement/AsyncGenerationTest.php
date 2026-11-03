<?php

use App\Jobs\GenererLotPaiementJob;
use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\LotPaiementService;
use App\Services\Paiement\MtnFileGenerator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->finance = User::factory()->agentFinancier()->create();
    $this->mission = Mission::factory()->create(['objet' => 'Mission async', 'destination' => 'Bohicon']);
    $agent = Agent::factory()->mtn()->create();
    $om = OrdreMission::factory()->statut('VALIDE')->create(['mission_id' => $this->mission->id, 'agent_id' => $agent->id]);
    Versement::factory()->create([
        'ordre_mission_id' => $om->id, 'type' => 'AVANCE',
        'operateur' => 'MTN', 'telephone' => $agent->telephone, 'montant' => 45000, 'statut' => 'EN_ATTENTE',
    ]);
    $this->selection = ['mission_id' => $this->mission->id, 'tranche' => 'AVANCE', 'operateur' => 'MTN'];
});

it('bascule un gros lot en file Redis (202 + tracking_id)', function () {
    config(['paiement.async_seuil' => 0]); // tout lot devient "gros"
    Queue::fake();

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertStatus(202)
        ->assertJsonPath('async', true)
        ->assertJsonStructure(['tracking_id', 'nb_lignes', 'message']);

    Queue::assertPushed(GenererLotPaiementJob::class);
});

it('garde le téléchargement direct pour un petit lot', function () {
    config(['paiement.async_seuil' => 50]);
    Queue::fake();

    $this->actingAs($this->finance)->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertOk()
        ->assertHeader('content-disposition');

    Queue::assertNotPushed(GenererLotPaiementJob::class);
});

it('exécute le job : génère le lot et renseigne le suivi', function () {
    $trackingId = (string) Str::uuid();

    GenererLotPaiementJob::dispatchSync(
        $this->mission->id, 'AVANCE', 'MTN', $this->finance->id, $trackingId,
    );

    $lot = LotPaiement::first();
    expect($lot->statut)->toBe('GENERE');

    $suivi = Cache::get("lot_job:{$trackingId}");
    expect($suivi['statut'])->toBe('TERMINE')
        ->and($suivi['lot_id'])->toBe($lot->id);
});

it('trace l’erreur et bascule le lot en ERREUR si le job échoue', function () {
    // Mission inexistante -> findOrFail lève, le job échoue.
    $trackingId = (string) Str::uuid();

    try {
        GenererLotPaiementJob::dispatchSync(999999, 'AVANCE', 'MTN', $this->finance->id, $trackingId);
    } catch (Throwable) {
        // le job a échoué (sync) : failed() a été déclenché
    }

    expect(Cache::get("lot_job:{$trackingId}")['statut'])->toBe('ERREUR');
});

it('expose le suivi via l’endpoint dédié', function () {
    Cache::put('lot_job:abc', ['statut' => 'TERMINE', 'lot_id' => 1], now()->addDay());

    $this->actingAs($this->finance)->getJson('/api/paiements/lots/job/abc')
        ->assertOk()
        ->assertJsonPath('data.statut', 'TERMINE');
});

/**
 * Fait échouer la génération du prochain job : `$avant` simule ce qui se passe
 * pendant le traitement (autre agent), puis le service lève.
 */
function echouerGeneration(?Closure $avant = null): void
{
    app()->instance(LotPaiementService::class, Mockery::mock(LotPaiementService::class, function ($m) use ($avant) {
        $m->shouldReceive('generer')->andReturnUsing(function () use ($avant) {
            $avant && $avant();
            throw new RuntimeException('disque plein');
        });
        $m->shouldReceive('marquerErreur')->passthru();
    })->makePartial());
}

it('remet les versements EN_ATTENTE quand le job échoue sur un lot déjà généré', function () {
    // Un premier fichier a été produit (lot GENERE, versement GENERE).
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot = LotPaiement::first();
    $versement = Versement::first();
    expect($versement->statut)->toBe('GENERE');

    // L'échec définitif d'une régénération suit le même chemin que « Marquer en erreur ».
    echouerGeneration();
    try {
        GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    } catch (RuntimeException) {
        // sync : l'exception remonte après failed()
    }

    expect($lot->fresh()->statut)->toBe('ERREUR')
        ->and($versement->fresh()->statut)->toBe('EN_ATTENTE');
});

it('ne touche jamais un lot confirmé quand le job échoue', function () {
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot = LotPaiement::first();
    $lot->update(['statut' => 'CONFIRME']);
    Versement::query()->update(['statut' => 'CONFIRME']);

    echouerGeneration();
    try {
        GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    } catch (RuntimeException) {
    }

    expect($lot->fresh()->statut)->toBe('CONFIRME')
        ->and(Versement::first()->statut)->toBe('CONFIRME');
});

it('failed() marque le lot du job et non un lot complémentaire généré entre-temps', function () {
    // Lot 1 (séquence 1) généré : le job qui échoue le vise.
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot1 = LotPaiement::first();

    // Un second bénéficiaire devient payable plus tard.
    $agent2 = Agent::factory()->mtn()->create();
    $om2 = OrdreMission::factory()->statut('VALIDE')->create(['mission_id' => $this->mission->id, 'agent_id' => $agent2->id]);
    $v2 = Versement::factory()->create([
        'ordre_mission_id' => $om2->id, 'type' => 'AVANCE',
        'operateur' => 'MTN', 'telephone' => $agent2->telephone, 'montant' => 30000, 'statut' => 'EN_ATTENTE',
    ]);

    // Pendant le traitement du job : un autre agent confirme le lot 1 et génère
    // le lot complémentaire (séquence 2) ; puis le job échoue.
    echouerGeneration(function () use ($lot1) {
        $lot1->update(['statut' => 'CONFIRME']);
        Versement::where('lot_id', $lot1->id)->update(['statut' => 'CONFIRME']);
        app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->finance);
    });

    $trackingId = (string) Str::uuid();
    try {
        GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, $trackingId);
    } catch (RuntimeException) {
    }

    $lot2 = LotPaiement::where('sequence', 2)->firstOrFail();
    expect(Cache::get("lot_job:{$trackingId}")['statut'])->toBe('ERREUR')
        ->and($lot1->fresh()->statut)->toBe('CONFIRME')
        // Le lot complémentaire n'appartient pas au job : il reste GENERE.
        ->and($lot2->statut)->toBe('GENERE')
        ->and($v2->fresh()->statut)->toBe('GENERE');
});

it('failed() marque le lot visé même si le job est rechargé depuis sa charge utile', function () {
    // En worker, failed() tourne sur une instance désérialisée à neuf : le lot visé
    // doit survivre hors de l'instance (cache), pas dans une propriété.
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot = LotPaiement::first();

    echouerGeneration();
    $job = new GenererLotPaiementJob($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    try {
        $job->handle(app(LotPaiementService::class));
    } catch (RuntimeException $e) {
        unserialize(serialize($job))->failed($e);
    }

    expect($lot->fresh()->statut)->toBe('ERREUR');
});

it('en mode sync, un gros lot réussi répond 202 et son suivi est déjà TERMINE', function () {
    config(['paiement.async_seuil' => 0]); // file réelle : QUEUE_CONNECTION=sync (phpunit.xml)

    $trackingId = $this->actingAs($this->finance)
        ->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertStatus(202)
        ->json('tracking_id');

    $this->actingAs($this->finance)->getJson("/api/paiements/lots/job/{$trackingId}")
        ->assertOk()
        ->assertJsonPath('data.statut', 'TERMINE');
});

it('en mode sync, l’échec d’un gros lot remonte dans la réponse même, sans 202', function () {
    // docs/queues.md : en sync, aucun 202 n'est renvoyé pour un job qui échoue ;
    // l'erreur revient dans la réponse du POST (failed() a déjà tourné).
    config(['paiement.async_seuil' => 0]);
    app()->instance(LotPaiementService::class, Mockery::mock(LotPaiementService::class, function ($m) {
        $m->shouldReceive('previsualiser')->passthru();
        $m->shouldReceive('generer')->andThrow(new RuntimeException('disque plein'));
    })->makePartial());

    $this->actingAs($this->finance)
        ->postJson('/api/paiements/lots/generer', $this->selection)
        ->assertStatus(500);
});
