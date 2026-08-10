<?php

use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Support\Permissions;
use App\Support\Workflow;

/*
 * Socle du circuit de validation : RBAC des signataires (contrôleur/DAF/DG),
 * séparation ordonnateur/payeur, et scellé d'intégrité. Le circuit à trois
 * signatures se joue sur l'état de paiement ; la validation OM par OM tient en
 * une étape (IMPORTE → VALIDE).
 */

// ── Profils signataires ──

it('accorde au DAF le visa et rien de plus sur le circuit', function () {
    $daf = User::factory()->daf()->create();

    expect($daf->hasPermission(Permissions::OM_VISER))->toBeTrue()
        ->and($daf->hasPermission(Permissions::OM_APPROUVER))->toBeFalse()
        ->and($daf->hasPermission(Permissions::OM_VALIDER))->toBeFalse();
});

it('accorde au DG l’approbation et rien de plus sur le circuit', function () {
    $dg = User::factory()->dg()->create();

    expect($dg->hasPermission(Permissions::OM_APPROUVER))->toBeTrue()
        ->and($dg->hasPermission(Permissions::OM_VISER))->toBeFalse()
        ->and($dg->hasPermission(Permissions::OM_VALIDER))->toBeFalse();
});

it('n’autorise ni le DAF ni le DG à exécuter les paiements', function () {
    // Séparation ordonnateur / payeur : les deux signataires autorisent,
    // l'AGENT_FINANCIER exécute.
    foreach ([User::factory()->daf()->create(), User::factory()->dg()->create()] as $signataire) {
        expect($signataire->hasPermission(Permissions::PAIEMENTS_GENERER))->toBeFalse()
            ->and($signataire->hasPermission(Permissions::PAIEMENTS_CONFIRMER))->toBeFalse()
            ->and($signataire->hasPermission(Permissions::PAIEMENTS_CONSULTER))->toBeTrue();
    }
});

it('laisse le CONTROLEUR sur la permission historique om.valider', function () {
    $controleur = User::factory()->controleur()->create();

    expect($controleur->hasPermission(Permissions::OM_VALIDER))->toBeTrue();
});

// ── Cohérence des sources du RBAC ──

it('garde la config des profils alignée sur la classe Permissions', function () {
    // Une désynchronisation doit casser la CI, jamais produire un 403 silencieux.
    expect(config('profils.permissions'))->toEqualCanonicalizing(Permissions::TOUTES)
        ->and(config('profils.profils'))->toEqualCanonicalizing(Permissions::PROFILS);

    foreach (config('profils.matrice') as $profil => $permissions) {
        expect(Permissions::pour($profil))->toEqualCanonicalizing($permissions);
    }
});

it('n’attribue que des permissions existantes', function () {
    foreach (config('profils.matrice') as $profil => $permissions) {
        $inconnues = array_diff($permissions, Permissions::TOUTES);

        expect($inconnues)->toBeEmpty("Le profil {$profil} référence des permissions inconnues : ".implode(', ', $inconnues));
    }
});

// ── Circuit OM : une seule étape ──

it('valide un OM en une étape (le contrôleur valide directement)', function () {
    // Le circuit OM par OM ne connaît qu'un rejet depuis IMPORTE puis la validation
    // finale ; le circuit à trois signatures vit sur l'état de paiement.
    expect(Workflow::statutsRejetables())->toBe(['IMPORTE']);
});

// ── Scellé d'intégrité ──

it('produit un scellé stable pour des données inchangées', function () {
    $om = OrdreMission::factory()->create();
    $signataire = User::factory()->daf()->create();
    $horodatage = now()->toISOString();

    expect(Workflow::sceller($om, $signataire, 'DAF', $horodatage))
        ->toBe(Workflow::sceller($om, $signataire, 'DAF', $horodatage));
});

it('invalide le scellé si un montant signé est modifié', function () {
    $om = OrdreMission::factory()->create(['montant_total' => 210000]);
    $signataire = User::factory()->daf()->create();
    $horodatage = now()->toISOString();

    $avant = Workflow::sceller($om, $signataire, 'DAF', $horodatage);

    $om->montant_total = 999000;

    expect(Workflow::sceller($om, $signataire, 'DAF', $horodatage))->not->toBe($avant);
});

it('distingue les scellés des deux étapes de signature', function () {
    $om = OrdreMission::factory()->create();
    $signataire = User::factory()->daf()->create();
    $horodatage = now()->toISOString();

    expect(Workflow::sceller($om, $signataire, 'DAF', $horodatage))
        ->not->toBe(Workflow::sceller($om, $signataire, 'DG', $horodatage));
});

it('formate un code de vérification lisible', function () {
    expect(Workflow::codeVerification(str_repeat('ab', 32)))->toBe('ABAB-ABAB-ABAB')
        ->and(Workflow::codeVerification(null))->toBeNull();
});

// ── Le statut intermédiaire n'ouvre aucun droit au paiement ──

it('exclut un OM visé DAF du périmètre payable', function () {
    $om = OrdreMission::factory()->create(['statut' => 'VISA_DAF']);

    // scopeEligiblesLot n'accepte que les OM VALIDE : le visa DAF seul ne paie pas.
    $eligibles = Versement::eligiblesLot($om->mission_id, 'AVANCE', 'MTN')->get();

    expect($eligibles)->toBeEmpty();
});
