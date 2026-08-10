<?php

use App\Models\EtatPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Services\EtatPaiementPdf;

/*
 * ÉTAT GÉNÉRAL de paiement — le document de clôture de la mission.
 *
 * Consolidation des deux tranches (avances + reliquats), servie en flux
 * UNIQUEMENT lorsque les deux états ont bouclé leur circuit (VALIDE).
 * Aucune entité propre, aucun troisième circuit : chaque colonne provient
 * de l'instantané figé de l'état de sa tranche.
 */

beforeEach(function () {

    $this->gestionnaire = User::factory()->gestionnaireOm()->create();
    $this->controleur = User::factory()->controleur()->create(['name' => 'Paul AGBO']);
    $this->daf = User::factory()->daf()->create(['name' => 'Euloge D. ATOHOUN']);
    $this->dg = User::factory()->dg()->create(['name' => 'Richard DADA']);

    $this->mission = Mission::factory()->create(['objet' => 'Vérification des hangars']);

    // Deux bénéficiaires : 210 000 et 135 000, comme sur l'état de référence.
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'IMPORTE',
        'montant_total' => 210000, 'avance' => 147000, 'reliquat' => 63000,
    ]);
    OrdreMission::factory()->create([
        'mission_id' => $this->mission->id,
        'statut' => 'IMPORTE',
        'montant_total' => 135000, 'avance' => 94500, 'reliquat' => 40500,
    ]);
});

/** Conduit UNE tranche jusqu'à la validation finale, par le vrai circuit. */
function validerTranche(object $t, string $tranche, string $numero): EtatPaiement
{
    $t->actingAs($t->gestionnaire)
        ->postJson("/api/missions/{$t->mission->id}/etat/recapituler", ['tranche' => $tranche])
        ->assertOk();
    $etat = EtatPaiement::where('mission_id', $t->mission->id)->where('tranche', $tranche)->firstOrFail();

    $t->actingAs($t->controleur)->postJson("/api/etats/{$etat->id}/controler")->assertOk();
    $t->actingAs($t->daf)->postJson("/api/etats/{$etat->id}/viser")->assertOk();
    $t->actingAs($t->daf)->putJson("/api/etats/{$etat->id}/numero", ['numero' => $numero])->assertOk();
    $t->actingAs($t->dg)->postJson("/api/etats/{$etat->id}/approuver")->assertOk();

    return $etat->fresh();
}

/** Circuit complet sur les deux tranches. */
function toutValider(object $t): void
{
    validerTranche($t, 'AVANCE', '0069/ANaTT/DAF/C-SFC');
    validerTranche($t, 'RELIQUAT', '0070/ANaTT/DAF/C-SFC');
}

// ── Garde-fou : pas d'état général avant le bout du bout ──

it('refuse l’état général tant qu’aucune tranche n’existe', function () {
    $this->actingAs($this->gestionnaire)
        ->getJson("/api/missions/{$this->mission->id}/etat/final")
        ->assertStatus(422)
        ->assertJsonValidationErrors('etat');
});

it('refuse l’état général avec seules les avances validées', function () {
    validerTranche($this, 'AVANCE', '0069/ANaTT/DAF/C-SFC');

    $this->actingAs($this->gestionnaire)
        ->getJson("/api/missions/{$this->mission->id}/etat/final")
        ->assertStatus(422);
});

it('refuse l’état général tant que les reliquats sont encore en circuit', function () {
    validerTranche($this, 'AVANCE', '0069/ANaTT/DAF/C-SFC');
    $this->actingAs($this->gestionnaire)
        ->postJson("/api/missions/{$this->mission->id}/etat/recapituler", ['tranche' => 'RELIQUAT'])
        ->assertOk();   // reliquats en BROUILLON seulement

    $this->actingAs($this->gestionnaire)
        ->getJson("/api/missions/{$this->mission->id}/etat/final")
        ->assertStatus(422);
});

// ── Document servi ──

it('sert l’état général en PDF une fois les deux tranches validées', function () {
    toutValider($this);

    $reponse = $this->actingAs($this->gestionnaire)
        ->get("/api/missions/{$this->mission->id}/etat/final")
        ->assertOk();

    expect($reponse->headers->get('content-type'))->toStartWith('application/pdf')
        ->and($reponse->headers->get('content-disposition'))->toContain('ETAT-GENERAL-0069anattdafc-sfc.pdf');
});

it('exige un utilisateur authentifié', function () {
    // Le garde-fou Sanctum frappe avant le contrôleur : aucun état requis.
    $this->getJson("/api/missions/{$this->mission->id}/etat/final")->assertUnauthorized();
});

// ── Contenu consolidé : chaque colonne vient de SA tranche scellée ──

it('consolide les montants des deux instantanés sans les recalculer', function () {
    toutValider($this);

    $avance = EtatPaiement::where('mission_id', $this->mission->id)->where('tranche', 'AVANCE')->firstOrFail();
    $reliquat = EtatPaiement::where('mission_id', $this->mission->id)->where('tranche', 'RELIQUAT')->firstOrFail();

    $donnees = app(EtatPaiementPdf::class)->donneesFinal($avance, $reliquat);

    expect($donnees['nb_beneficiaires'])->toBe(2)
        ->and($donnees['total_avance'])->toBe('241 500')      // 147 000 + 94 500
        ->and($donnees['total_reliquat'])->toBe('103 500')    // 63 000 + 40 500
        ->and($donnees['total'])->toBe('345 000')
        ->and($donnees['mention_arret'])->toBe('Trois cent quarante-cinq mille (345 000) FRANCS CFA');

    // Chaque ligne porte les DEUX tranches ; son total en est la somme exacte.
    foreach ($donnees['lignes'] as $ligne) {
        expect($ligne['montant_total_brut'])->toBe($ligne['avance_brut'] + $ligne['reliquat_brut']);
        expect($ligne['avance'])->not->toBe('-')
            ->and($ligne['reliquat'])->not->toBe('-');
    }

    // La traçabilité cite les deux registres et leurs validations.
    expect($donnees['references'][0]['numero'])->toBe('0069/ANaTT/DAF/C-SFC')
        ->and($donnees['references'][1]['numero'])->toBe('0070/ANaTT/DAF/C-SFC')
        ->and($donnees['references'][0]['signataire'])->toBe('Richard DADA');
});
