<?php

use App\Models\Agent;
use App\Models\OrdreMission;
use App\Models\TauxPerDiem;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MissionService();
    $this->taux = TauxPerDiem::factory()->create([
        'categorie' => 'A1',
        'destination_type' => 'LOCALE',
        'montant_journalier' => 25000,
        'annee' => now()->year,
    ]);
    $this->agent = Agent::factory()->create(['categorie' => 'A1']);
    $this->admin = User::factory()->admin()->create();
});

it('cree un ordre de mission en brouillon', function () {
    $ordre = $this->service->creer([
        'agent_id' => $this->agent->id,
        'objet' => 'Test mission',
        'destination' => 'Parakou',
        'date_depart' => '2026-05-10',
        'date_retour' => '2026-05-14',
        'taux_id' => $this->taux->id,
    ], $this->admin);

    expect($ordre->statut)->toBe('BROUILLON');
    expect($ordre->nb_jours)->toBe(5);
    expect((float) $ordre->montant_per_diem)->toBe(125000.0);
    expect($ordre->numero)->toStartWith('OM-');
});

it('refuse de modifier un ordre non brouillon', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->service->modifier($ordre, ['objet' => 'Modifie']);
})->throws(ValidationException::class);

it('refuse de supprimer un ordre non brouillon', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->service->supprimer($ordre);
})->throws(ValidationException::class);

it('soumet un ordre brouillon vers soumis', function () {
    $ordre = OrdreMission::factory()->create(['taux_id' => $this->taux->id]);

    $result = $this->service->soumettre($ordre);

    expect($result->statut)->toBe('SOUMIS');
});

it('refuse de soumettre un ordre non brouillon', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->service->soumettre($ordre);
})->throws(ValidationException::class);

it('valide N1 avec role CHEF_SERVICE', function () {
    $chef = User::factory()->chefService()->create();
    Agent::factory()->create(['user_id' => $chef->id, 'service' => 'Direction Technique']);
    $agent = Agent::factory()->create(['service' => 'Direction Technique']);
    $ordre = OrdreMission::factory()->soumis()->create(['agent_id' => $agent->id, 'taux_id' => $this->taux->id]);

    $result = $this->service->validerN1($ordre, $chef);

    expect($result->statut)->toBe('VALIDE_N1');
    expect($result->valide_par_chef)->toBe($chef->id);
});

it('refuse validation N1 sans role CHEF_SERVICE', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->service->validerN1($ordre, $agent);
})->throws(ValidationException::class);

it('approuve DG avec role DIRECTEUR', function () {
    $dg = User::factory()->directeur()->create();
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $result = $this->service->approuver($ordre, $dg);

    expect($result->statut)->toBe('APPROUVE_DG');
    expect($result->valide_par_dg)->toBe($dg->id);
});

it('refuse approbation DG sans role DIRECTEUR', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->valideN1()->create(['taux_id' => $this->taux->id]);

    $this->service->approuver($ordre, $agent);
})->throws(ValidationException::class);

it('rejete un ordre avec commentaire', function () {
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $result = $this->service->rejeter($ordre, 'Motif de rejet suffisamment long');

    expect($result->statut)->toBe('REJETE');
    expect($result->commentaire_rejet)->toBe('Motif de rejet suffisamment long');
});

it('refuse de rejeter un ordre en brouillon', function () {
    $ordre = OrdreMission::factory()->create(['taux_id' => $this->taux->id]);

    $this->service->rejeter($ordre, 'Commentaire de rejet test');
})->throws(ValidationException::class);

it('refuse le rejet par un agent (defense en profondeur)', function () {
    $agent = User::factory()->create(['role' => 'AGENT']);
    $ordre = OrdreMission::factory()->soumis()->create(['taux_id' => $this->taux->id]);

    $this->service->rejeter($ordre, 'Commentaire de rejet test', $agent);
})->throws(ValidationException::class);

it('empeche la double validation via verrou pessimiste', function () {
    $chef = User::factory()->chefService()->create();
    Agent::factory()->create(['user_id' => $chef->id, 'service' => 'Direction Technique']);
    $agent = Agent::factory()->create(['service' => 'Direction Technique']);
    $ordre = OrdreMission::factory()->soumis()->create(['agent_id' => $agent->id, 'taux_id' => $this->taux->id]);

    // Premiere validation reussit
    $result = $this->service->validerN1($ordre, $chef);
    expect($result->statut)->toBe('VALIDE_N1');

    // Deuxieme tentative echoue car statut a change
    $this->service->validerN1($ordre, $chef);
})->throws(ValidationException::class);

it('empeche la soumission double', function () {
    $ordre = OrdreMission::factory()->create(['taux_id' => $this->taux->id]);

    $this->service->soumettre($ordre);
    expect($ordre->fresh()->statut)->toBe('SOUMIS');

    // Deuxieme soumission echoue
    $this->service->soumettre($ordre);
})->throws(ValidationException::class);
