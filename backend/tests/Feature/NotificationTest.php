<?php

use App\Jobs\SendNotification;
use App\Models\OrdreMission;
use App\Models\User;
use App\Services\MissionService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->service = app(MissionService::class);
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

it('notifie l’agent financier à la validation d’un OM', function () {
    User::factory()->agentFinancier()->create();
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    Queue::assertPushed(SendNotification::class);
});

it('notifie le gestionnaire OM au rejet d’un OM', function () {
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->rejeter($om, 'Document non conforme', $this->gestion);

    Queue::assertPushed(SendNotification::class);
});

it('n’envoie aucune notification si aucun destinataire du rôle', function () {
    // Aucun agent_financier en base -> aucune notification à la validation.
    $om = OrdreMission::factory()->create(['statut' => 'IMPORTE']);

    $this->service->valider($om, $this->gestion);

    Queue::assertNotPushed(SendNotification::class);
});
