<?php

use App\Models\EtatPaiement;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\LotPaiementService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
 * Seconde barrière : aucun fichier de paiement n'est écrit tant que l'état de la
 * mission n'est pas validé par le Directeur Général.
 *
 * Le périmètre payable est déjà borné par scopeEligiblesLot (OM VALIDE). Ces
 * tests forcent délibérément des OM à VALIDE SANS passer par le circuit, pour
 * vérifier que la barrière tient même si la première règle est contournée —
 * c'est tout l'intérêt d'en avoir deux.
 */

beforeEach(function () {
    Storage::fake('local');

    $this->agent = User::factory()->agentFinancier()->create();
    $this->mission = Mission::factory()->create();

    $om = OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'VALIDE',   // forcé : on court-circuite le circuit exprès
    ]);

    Versement::factory()->create([
        'ordre_mission_id' => $om->id,
        'type' => 'AVANCE',
        'operateur' => 'MTN',
        'statut' => 'EN_ATTENTE',
    ]);

    $this->generer = fn () => app(LotPaiementService::class)
        ->generer($this->mission->id, 'AVANCE', 'MTN', $this->agent);
});

it('refuse de générer tant que l’état n’est pas validé', function (string $statut) {
    EtatPaiement::create([
        'mission_id' => $this->mission->id,
        'statut' => $statut,
        'nb_beneficiaires' => 1,
        'montant_total' => 100000,
        'montant_avance' => 70000,
        'montant_reliquat' => 30000,
    ]);

    expect($this->generer)->toThrow(ValidationException::class);

    // Aucun fichier écrit, aucun lot créé.
    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(LotPaiement::count())->toBe(0);
})->with(['BROUILLON', 'CONTROLE', 'VISA_DAF', 'REJETE']);

it('génère une fois l’état validé par le Directeur Général', function () {
    EtatPaiement::create([
        'mission_id' => $this->mission->id,
        'numero' => '0069/ANaTT/DAF/C-SFC',
        'statut' => 'VALIDE',
        'nb_beneficiaires' => 1,
        'montant_total' => 100000,
        'montant_avance' => 70000,
        'montant_reliquat' => 30000,
    ]);

    $resultat = ($this->generer)();

    expect($resultat['lot']->statut)->toBe('GENERE')
        ->and(Storage::disk('local')->exists($resultat['chemin']))->toBeTrue();
});

it('laisse passer une mission sans état, pour la reprise de données', function () {
    // Aucun état : le périmètre par OM fait foi, comme avant les états.
    $resultat = ($this->generer)();

    expect($resultat['lot']->statut)->toBe('GENERE');
});
