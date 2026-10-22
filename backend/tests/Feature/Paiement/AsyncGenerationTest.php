<?php

use App\Jobs\GenererLotPaiementJob;
use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
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

it('remet les versements EN_ATTENTE quand le job échoue sur un lot déjà généré', function () {
    // Un premier fichier a été produit (lot GENERE, versement GENERE).
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot = LotPaiement::first();
    $versement = Versement::first();
    expect($versement->statut)->toBe('GENERE');

    // L'échec définitif d'une régénération suit le même chemin que « Marquer en erreur ».
    $job = new GenererLotPaiementJob($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $job->failed(new RuntimeException('disque plein'));

    expect($lot->fresh()->statut)->toBe('ERREUR')
        ->and($versement->fresh()->statut)->toBe('EN_ATTENTE');
});

it('ne touche jamais un lot confirmé quand le job échoue', function () {
    GenererLotPaiementJob::dispatchSync($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid());
    $lot = LotPaiement::first();
    $lot->update(['statut' => 'CONFIRME']);
    Versement::query()->update(['statut' => 'CONFIRME']);

    (new GenererLotPaiementJob($this->mission->id, 'AVANCE', 'MTN', $this->finance->id, (string) Str::uuid()))
        ->failed(new RuntimeException('échec'));

    expect($lot->fresh()->statut)->toBe('CONFIRME')
        ->and(Versement::first()->statut)->toBe('CONFIRME');
});
