<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Services\EtatPaiementPdf;
use App\Services\EtatPaiementService;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * INTÉGRITÉ DU DOCUMENT « ÉTAT DE PAIEMENT ».
 *
 * Un état signé est une pièce comptable : ce qu'il montre au moment de la
 * signature est ce qu'il doit montrer pour toujours. Ces tests verrouillent les
 * trois choses qui pouvaient encore bouger sous lui — le paraphe imprimé, le
 * tableau des bénéficiaires, et le fichier dans lequel il est écrit.
 */

beforeEach(function () {
    Storage::fake('local');
    config(['workflow.double_validation' => true]);

    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create();
    $this->daf = User::factory()->daf()->create();
    $this->dg = User::factory()->dg()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/** PNG réel de couleur donnée : deux paraphes distincts et reconnaissables. */
function paraphe(string $couleur): UploadedFile
{
    $image = imagecreatetruecolor(4, 4);
    [$r, $v, $b] = $couleur === 'rouge' ? [255, 0, 0] : [0, 0, 255];
    imagefill($image, 0, 0, imagecolorallocate($image, $r, $v, $b));
    $fichier = tempnam(sys_get_temp_dir(), 'sig').'.png';
    imagepng($image, $fichier);
    imagedestroy($image);

    return new UploadedFile($fichier, 'signature.png', 'image/png', null, true);
}

/** Mission à deux bénéficiaires, dont l'état est conduit jusqu'à VALIDE. */
function etatSigne(object $t, string $numero): EtatPaiement
{
    $mission = Mission::factory()->create();

    foreach ([[210000, 147000, 63000], [135000, 94500, 40500]] as [$total, $avance, $reliquat]) {
        OrdreMission::factory()->create([
            'mission_id' => $mission->id, 'statut' => 'IMPORTE',
            'montant_total' => $total, 'avance' => $avance, 'reliquat' => $reliquat,
        ]);
    }

    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $t->gestionnaire);
    $service->controler($etat, $t->controleur);
    $etat = $service->viser($etat->fresh(), $t->daf);
    $etat = $service->definirNumero($etat, $numero, $t->daf);

    return $service->approuver($etat, $t->dg);
}

// ── Le paraphe est figé au moment de la signature ──

it('imprime toujours le paraphe archivé à la signature, même après changement de titulaire', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => paraphe('rouge')], ['Accept' => 'application/json'])
        ->assertOk();

    $etat = etatSigne($this, 'SIG-1');
    $avant = app(EtatPaiementPdf::class)->donnees($etat, brouillon: false);

    // Un nouveau titulaire arrive et téléverse SON paraphe.
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => paraphe('bleu')], ['Accept' => 'application/json'])
        ->assertOk();

    $apres = app(EtatPaiementPdf::class)->donnees($etat->fresh(), brouillon: false);

    // L'état déjà validé ne bouge plus : ni le nom, ni le paraphe.
    expect($apres['visa_daf']['nom'])->toBe($avant['visa_daf']['nom'])
        ->and($apres['visa_daf']['image'])->toBe($avant['visa_daf']['image']);
});

it('archive un paraphe par étape et par état, sur le disque privé', function () {
    foreach (['controleur', 'daf', 'dg'] as $poste) {
        $this->actingAs($this->superAdmin)
            ->post("/api/admin/signatures/{$poste}", ['signature' => paraphe('rouge')], ['Accept' => 'application/json'])
            ->assertOk();
    }

    $etat = etatSigne($this, 'SIG-2');

    expect($etat->signature_controle_image)->toBe("signatures/etats/{$etat->id}-controleur.png")
        ->and($etat->signature_daf_image)->toBe("signatures/etats/{$etat->id}-daf.png")
        ->and($etat->signature_dg_image)->toBe("signatures/etats/{$etat->id}-dg.png");

    foreach ([$etat->signature_controle_image, $etat->signature_daf_image, $etat->signature_dg_image] as $chemin) {
        expect(Storage::disk('local')->exists($chemin))->toBeTrue();
    }
});

it('efface les paraphes archivés des étapes annulées par un rejet', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => paraphe('rouge')], ['Accept' => 'application/json'])
        ->assertOk();

    $mission = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $mission->id, 'statut' => 'IMPORTE']);

    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $this->gestionnaire);
    $service->controler($etat, $this->controleur);
    $etat = $service->viser($etat->fresh(), $this->daf);

    $archive = $etat->signature_daf_image;
    expect(Storage::disk('local')->exists($archive))->toBeTrue();

    $etat = $service->rejeter($etat, 'Montants non conformes au barème.', $this->dg);

    expect($etat->signature_daf_image)->toBeNull()
        ->and($etat->signature_controle_image)->toBeNull()
        // Une signature manuscrite est une donnée sensible : on ne la laisse pas
        // sur le disque une fois sa raison d'être retirée.
        ->and(Storage::disk('local')->exists($archive))->toBeFalse();
});

// ── Le tableau imprimé est figé au récapitulatif ──

it('fige les lignes du tableau au récapitulatif', function () {
    $etat = etatSigne($this, 'LIG-1');

    expect($etat->lignes)->toHaveCount(2)
        ->and($etat->lignes[0])->toHaveKeys(['numero', 'beneficiaire', 'detail', 'total', 'avance', 'reliquat']);

    // Somme des lignes figées = totaux scellés : l'état ne peut pas s'imprimer
    // avec un tableau qui ne totalise pas son propre total.
    $somme = array_sum(array_column($etat->lignes, 'montant_total_brut'));
    expect($somme)->toBe($etat->montant_total);
});

it('imprime le tableau figé, et non les OM tels qu’ils sont devenus', function () {
    $etat = etatSigne($this, 'LIG-2');
    $avant = app(EtatPaiementPdf::class)->donnees($etat, brouillon: false);

    // Un OM disparaît du périmètre après la validation (reprise de données,
    // correction hors circuit) : le document signé, lui, ne change pas.
    OrdreMission::where('mission_id', $etat->mission_id)->limit(1)->update(['statut' => 'ANNULE']);

    $apres = app(EtatPaiementPdf::class)->donnees($etat->fresh(), brouillon: false);

    expect($apres['lignes'])->toBe($avant['lignes'])
        ->and($apres['nb_beneficiaires'])->toBe(2);
});

it('recalcule le tableau des états antérieurs à l’instantané', function () {
    $etat = etatSigne($this, 'LIG-3');

    // État créé avant la mise en place de l'instantané : pas de lignes figées.
    $etat->forceFill(['lignes' => null])->save();

    $donnees = app(EtatPaiementPdf::class)->donnees($etat->fresh(), brouillon: false);

    expect($donnees['lignes'])->toHaveCount(2);
});

// ── Un fichier par état ──

it('n’écrit jamais deux états dans le même fichier, même à numéro symbolique', function () {
    $a = etatSigne($this, '###');
    $b = etatSigne($this, '...');

    $pdf = app(EtatPaiementPdf::class);

    expect($pdf->generer($a, brouillon: false))->not->toBe($pdf->generer($b, brouillon: false));
});

it('sert le brouillon en flux, sans passer par un fichier partagé', function () {
    $mission = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $mission->id, 'statut' => 'IMPORTE']);
    app(EtatPaiementService::class)->recapituler($mission, $this->gestionnaire);

    $reponse = $this->actingAs($this->gestionnaire)->get("/api/missions/{$mission->id}/etat/brouillon");

    $reponse->assertOk();
    expect($reponse->headers->get('content-type'))->toContain('application/pdf');
    // Aucun fichier d'état laissé derrière : le téléchargement n'écrit rien.
    expect(Storage::disk('local')->files('etats'))->toBeEmpty();
});

// ── Le document dit lui-même qu'il a été rejeté ──

it('porte la mention et le motif du rejet sur le document', function () {
    $mission = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $mission->id, 'statut' => 'IMPORTE']);

    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $this->gestionnaire);
    $etat = $service->rejeter($etat, 'Barème appliqué non conforme.', $this->controleur);

    $donnees = app(EtatPaiementPdf::class)->donnees($etat, brouillon: true);

    expect($donnees['rejet'])->toBe('Barème appliqué non conforme.');
});

it('ne porte aucune mention de rejet sur un état en cours de circuit', function () {
    $mission = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $mission->id, 'statut' => 'IMPORTE']);
    $etat = app(EtatPaiementService::class)->recapituler($mission, $this->gestionnaire);

    expect(app(EtatPaiementPdf::class)->donnees($etat, brouillon: true)['rejet'])->toBeNull();
});

// ── Le circuit prévient l'acteur suivant ──

it('avise chaque acteur du circuit à son tour', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);

    $notifications->shouldReceive('etatAttendSignature')->once()
        ->with(Mockery::type(EtatPaiement::class), User::ROLE_CONTROLEUR);
    $notifications->shouldReceive('etatAttendSignature')->once()
        ->with(Mockery::type(EtatPaiement::class), User::ROLE_DAF);
    $notifications->shouldReceive('etatAttendSignature')->once()
        ->with(Mockery::type(EtatPaiement::class), User::ROLE_DG);
    $notifications->shouldReceive('etatValide')->once();

    etatSigne($this, 'NOTIF-1');
});

it('avise le gestionnaire du rejet, avec son motif', function () {
    $notifications = Mockery::mock(NotificationService::class);
    $this->app->instance(NotificationService::class, $notifications);
    $notifications->shouldReceive('etatAttendSignature')->zeroOrMoreTimes();

    $notifications->shouldReceive('etatRejete')->once()
        ->with(Mockery::type(EtatPaiement::class), 'Barème appliqué non conforme.');

    $mission = Mission::factory()->create();
    OrdreMission::factory()->create(['mission_id' => $mission->id, 'statut' => 'IMPORTE']);

    $service = app(EtatPaiementService::class);
    $etat = $service->recapituler($mission, $this->gestionnaire);
    $service->rejeter($etat, 'Barème appliqué non conforme.', $this->controleur);
});
