<?php

use App\Models\User;
use App\Support\Parametres;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

/*
 * Paramétrage flexible de l'année fiscale / exercice.
 *
 * Vérifie la DÉTERMINATION de l'exercice (mois de début configurable), la valeur
 * par défaut (année civile courante), la validation à l'écriture et l'endpoint
 * de lecture publique.
 */

beforeEach(function () {
    // Table rase : pas de settings.json résiduel, caches vidés.
    File::delete(storage_path('app/settings.json'));
    Parametres::invalidateAnneeFiscale();
});

afterEach(function () {
    File::delete(storage_path('app/settings.json'));
    Parametres::invalidateAnneeFiscale();
});

// ── Détermination ──

it('détermine l\'année fiscale sur l\'année civile par défaut (début janvier)', function () {
    expect(Parametres::moisDebutExercice())->toBe(1)
        ->and(Parametres::anneeFiscalePour(Carbon::parse('2026-03-15')))->toBe(2026)
        ->and(Parametres::anneeFiscalePour(Carbon::parse('2026-12-31')))->toBe(2026);
});

it('détermine l\'exercice à cheval quand le mois de début n\'est pas janvier', function () {
    $sa = User::factory()->superAdmin()->create();
    $this->actingAs($sa)->putJson('/api/admin/settings', ['fiscalYearStartMonth' => 7])->assertOk();
    Parametres::invalidateAnneeFiscale();

    expect(Parametres::moisDebutExercice())->toBe(7)
        // Juillet 2026 → exercice 2026 (2026-2027) ; juin 2026 → exercice 2025.
        ->and(Parametres::anneeFiscalePour(Carbon::parse('2026-07-01')))->toBe(2026)
        ->and(Parametres::anneeFiscalePour(Carbon::parse('2026-06-30')))->toBe(2025)
        ->and(Parametres::libelleAnneeFiscale(2026))->toBe('2026-2027');
});

it('retombe sur l\'année déterminée quand aucune année n\'est configurée', function () {
    Carbon::setTestNow(Carbon::parse('2027-04-10'));

    expect(Parametres::anneeFiscale())->toBe(2027);

    Carbon::setTestNow();
});

it('utilise l\'année fiscale explicitement configurée', function () {
    $sa = User::factory()->superAdmin()->create();
    $this->actingAs($sa)->putJson('/api/admin/settings', ['fiscalYear' => 2030])->assertOk();
    Parametres::invalidateAnneeFiscale();

    expect(Parametres::anneeFiscale())->toBe(2030);
});

// ── Validation ──

it('refuse une année fiscale hors bornes', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/settings', ['fiscalYear' => 1980])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('fiscalYear');
});

it('refuse un mois de début d\'exercice invalide', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/settings', ['fiscalYearStartMonth' => 13])
        ->assertStatus(422)
        ->assertJsonValidationErrorFor('fiscalYearStartMonth');
});

it('accepte une année transmise en chaîne numérique et la stocke en entier', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->putJson('/api/admin/settings', ['fiscalYear' => '2028'])
        ->assertOk();
    Parametres::invalidateAnneeFiscale();

    expect(Parametres::anneeFiscale())->toBe(2028);
});

// ── Lecture publique ──

it('expose l\'exercice courant à tout utilisateur authentifié', function () {
    $sa = User::factory()->superAdmin()->create();
    $this->actingAs($sa)->putJson('/api/admin/settings', ['fiscalYear' => 2029, 'fiscalYearStartMonth' => 1])->assertOk();

    $this->actingAs(User::factory()->lecteur()->create())
        ->getJson('/api/admin/parametres/annee-fiscale')
        ->assertOk()
        ->assertJsonPath('data.annee', 2029)
        ->assertJsonPath('data.mois_debut', 1)
        ->assertJsonPath('data.libelle', '2029');
});
