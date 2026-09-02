<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCircuitSeeder;
use Illuminate\Support\Facades\Hash;

/*
 * Le bac à sable du circuit CONTROLEUR -> DAF -> DG.
 *
 * Un seeder de démonstration qui pose un mot de passe connu est utile tant
 * qu'il reste hors production, et inutile s'il n'installe pas RÉELLEMENT de
 * quoi dérouler le circuit. Ces tests vérifient les deux.
 */

/*
 * settings.json est un fichier réel, partagé par toute la suite : le seeder y
 * écrit les identités des signataires, et sans restauration il modifierait le
 * résultat des autres tests (et le poste de travail du développeur).
 */
beforeEach(function () {
    $this->fichierParametres = storage_path('app/settings.json');
    $this->parametresInitiaux = file_exists($this->fichierParametres)
        ? file_get_contents($this->fichierParametres)
        : null;

    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoCircuitSeeder::class);
});

afterEach(function () {
    if ($this->parametresInitiaux === null) {
        @unlink($this->fichierParametres);
    } else {
        file_put_contents($this->fichierParametres, $this->parametresInitiaux);
    }
});

it('installe les trois signataires avec le bon profil', function () {
    $profils = User::whereIn('email', [
        'demo.controleur@anatt.bj',
        'demo.daf@anatt.bj',
        'demo.dg@anatt.bj',
    ])->pluck('role', 'email');

    expect($profils['demo.controleur@anatt.bj'])->toBe(Permissions::CONTROLEUR)
        ->and($profils['demo.daf@anatt.bj'])->toBe(Permissions::DAF)
        ->and($profils['demo.dg@anatt.bj'])->toBe(Permissions::DG);
});

it('rend les comptes utilisables immédiatement', function () {
    $daf = User::where('email', 'demo.daf@anatt.bj')->first();

    expect(Hash::check(DemoCircuitSeeder::MOT_DE_PASSE, $daf->password))->toBeTrue()
        ->and($daf->must_change_password)->toBeFalse()
        ->and($daf->actif)->toBeTrue();

    // Le mot de passe annoncé dans la console est bien celui qui ouvre l'API.
    $this->postJson('/api/auth/login', [
        'email' => 'demo.daf@anatt.bj',
        'password' => DemoCircuitSeeder::MOT_DE_PASSE,
    ])->assertOk();
});

it('enregistre les identités imprimées sous les signatures', function () {
    $params = json_decode((string) file_get_contents(storage_path('app/settings.json')), true);

    // Sans elles, l'état sort sans signataire — la première surprise de tous.
    expect($params['dafNom'])->toBe('ATOHOUN')
        ->and($params['dgNom'])->toBe('DADA')
        ->and($params['controleurNom'])->toBe('AGBO')
        ->and($params['dgFonction'])->toBe('Directeur Général');
});

it('crée une mission d’essai sans état, pour partir du tout début', function () {
    $ordre = OrdreMission::where('reference_semo', 'DEMO-CIRCUIT-001')->first();

    expect($ordre)->not->toBeNull()
        ->and($ordre->statut)->toBe('IMPORTE');

    $mission = Mission::find($ordre->mission_id);

    expect($mission->ordresMission()->count())->toBe(3)
        ->and(EtatPaiement::where('mission_id', $mission->id)->exists())->toBeFalse();
});

it('confie le dernier bénéficiaire au conducteur', function () {
    $roles = OrdreMission::where('reference_semo', 'like', 'DEMO-CIRCUIT%')
        ->orderBy('reference_semo')
        ->pluck('role')
        ->all();

    expect($roles)->toBe(['PARTICIPANT', 'PARTICIPANT', 'CAV']);
});

it('est relançable sans créer de doublon', function () {
    $this->seed(DemoCircuitSeeder::class);
    $this->seed(DemoCircuitSeeder::class);

    expect(OrdreMission::where('reference_semo', 'like', 'DEMO-CIRCUIT%')->count())->toBe(3)
        ->and(User::where('email', 'demo.daf@anatt.bj')->count())->toBe(1);
});

it('refuse de s’exécuter en production', function () {
    User::whereIn('email', ['demo.daf@anatt.bj', 'demo.dg@anatt.bj', 'demo.controleur@anatt.bj'])->delete();

    app()->detectEnvironment(fn () => 'production');

    // Invocation directe : `db:seed` demanderait confirmation en production,
    // ce qui masquerait le garde-fou que l'on veut justement vérifier.
    (new DemoCircuitSeeder)->setContainer(app())->run();

    // Un mot de passe connu n'a rien à faire sur un serveur de production.
    expect(User::where('email', 'demo.daf@anatt.bj')->exists())->toBeFalse();
});

it('permet de dérouler le circuit entier avec les comptes installés', function () {
    $mission = Mission::find(
        OrdreMission::where('reference_semo', 'DEMO-CIRCUIT-001')->value('mission_id')
    );

    $connexion = fn (string $email) => User::where('email', $email)->first();

    // 1. Récapitulatif par le gestionnaire : le brouillon d'état apparaît.
    $this->actingAs(User::where('role', Permissions::GESTIONNAIRE_OM)->first())
        ->postJson("/api/missions/{$mission->id}/etat/recapituler")
        ->assertOk();

    $etat = EtatPaiement::where('mission_id', $mission->id)->firstOrFail();
    expect($etat->statut)->toBe('BROUILLON')
        ->and($etat->nb_beneficiaires)->toBe(3);

    // 2. Contrôle de conformité.
    $this->actingAs($connexion('demo.controleur@anatt.bj'))
        ->postJson("/api/etats/{$etat->id}/controler")
        ->assertOk();

    // 3. Numérotation manuelle, puis visa du DAF.
    $this->actingAs($connexion('demo.daf@anatt.bj'))
        ->putJson("/api/etats/{$etat->id}/numero", ['numero' => '0070/ANaTT/DAF/C-SFC'])
        ->assertOk();

    $this->actingAs($connexion('demo.daf@anatt.bj'))
        ->postJson("/api/etats/{$etat->id}/viser")
        ->assertOk();

    expect($etat->fresh()->statut)->toBe('VISA_DAF');

    // 4. Validation finale du Directeur Général : l'état est arrêté.
    $this->actingAs($connexion('demo.dg@anatt.bj'))
        ->postJson("/api/etats/{$etat->id}/approuver")
        ->assertOk();

    expect($etat->fresh()->statut)->toBe('VALIDE');

    // Les OM de la mission suivent, et deviennent payables.
    expect($mission->ordresMission()->pluck('statut')->unique()->all())->toBe(['VALIDE']);
});

it('imprime le nom complet du signataire, prénoms compris', function () {
    $mission = Mission::find(
        OrdreMission::where('reference_semo', 'DEMO-CIRCUIT-001')->value('mission_id')
    );

    $this->actingAs(User::where('role', Permissions::GESTIONNAIRE_OM)->first())
        ->postJson("/api/missions/{$mission->id}/etat/recapituler")->assertOk();

    $etat = EtatPaiement::where('mission_id', $mission->id)->firstOrFail();

    $this->actingAs(User::where('email', 'demo.controleur@anatt.bj')->first())
        ->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $this->actingAs(User::where('email', 'demo.daf@anatt.bj')->first())
        ->postJson("/api/etats/{$etat->id}/viser")->assertOk();

    // Les prénoms sont saisis dans les paramètres pour figurer sous la
    // signature : n'y porter que le patronyme les rendrait inutiles.
    expect($etat->fresh()->controle_nom)->toBe('Paul AGBO')
        ->and($etat->fresh()->visa_daf_nom)->toBe('Euloge D. ATOHOUN')
        ->and($etat->fresh()->visa_daf_fonction)->toBe("Directeur de l'Administration et des Finances");
});

it('interdit au DG de sauter le visa du DAF', function () {
    $mission = Mission::find(
        OrdreMission::where('reference_semo', 'DEMO-CIRCUIT-001')->value('mission_id')
    );

    $this->actingAs(User::where('role', Permissions::GESTIONNAIRE_OM)->first())
        ->postJson("/api/missions/{$mission->id}/etat/recapituler")
        ->assertOk();

    $etat = EtatPaiement::where('mission_id', $mission->id)->firstOrFail();

    // L'ordre des signatures est le contrôle lui-même : il ne se contourne pas.
    $this->actingAs(User::where('email', 'demo.dg@anatt.bj')->first())
        ->postJson("/api/etats/{$etat->id}/approuver")
        ->assertStatus(422);

    expect($etat->fresh()->statut)->toBe('BROUILLON');
});
