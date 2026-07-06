<?php

use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\LotPaiementService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->service = app(LotPaiementService::class);
    $this->user = User::factory()->agentFinancier()->create();
    $this->mission = Mission::factory()->create();
    $agent = Agent::factory()->mtn()->create();
    $this->om = OrdreMission::factory()->statut('VALIDE')->create([
        'mission_id' => $this->mission->id, 'agent_id' => $agent->id,
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
    foreach (['AVANCE' => 94500, 'RELIQUAT' => 40500] as $type => $montant) {
        Versement::factory()->create([
            'ordre_mission_id' => $this->om->id, 'type' => $type, 'montant' => $montant,
            'operateur' => 'MTN', 'telephone' => $agent->telephone, 'statut' => 'EN_ATTENTE',
        ]);
    }
});

function genererEtConfirmer(LotPaiementService $service, int $missionId, string $tranche, User $user): void
{
    $service->generer($missionId, $tranche, 'MTN', $user);
    $lot = LotPaiement::where('mission_id', $missionId)->where('tranche', $tranche)->where('operateur', 'MTN')->first();
    $service->confirmer($lot, $user);
}

it('ne solde pas l’OM tant qu’une tranche reste à confirmer', function () {
    genererEtConfirmer($this->service, $this->mission->id, 'AVANCE', $this->user);

    expect($this->om->fresh()->statut)->not->toBe('SOLDE'); // reliquat encore EN_ATTENTE
});

it('passe l’OM à SOLDE quand TOUS ses versements sont confirmés', function () {
    genererEtConfirmer($this->service, $this->mission->id, 'AVANCE', $this->user);
    genererEtConfirmer($this->service, $this->mission->id, 'RELIQUAT', $this->user);

    expect($this->om->fresh()->statut)->toBe('SOLDE');
    expect(Versement::where('ordre_mission_id', $this->om->id)->where('statut', 'CONFIRME')->count())->toBe(2);
});

it('régénère un lot tant qu’il n’est pas confirmé', function () {
    $this->service->generer($this->mission->id, 'AVANCE', 'MTN', $this->user);
    $lot1 = LotPaiement::where('tranche', 'AVANCE')->first();
    expect($lot1->statut)->toBe('GENERE');

    // Régénération autorisée (lot non confirmé) : même lot, mis à jour.
    $this->service->generer($this->mission->id, 'AVANCE', 'MTN', $this->user);

    expect(LotPaiement::where('tranche', 'AVANCE')->count())->toBe(1)
        ->and(LotPaiement::find($lot1->id)->statut)->toBe('GENERE');
    expect(Versement::where('type', 'AVANCE')->first()->statut)->toBe('GENERE');
});
