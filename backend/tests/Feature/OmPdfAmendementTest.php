<?php

use App\Models\Bareme;
use App\Models\Mission;
use App\Models\OmAmendement;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\PdfGenerator;
use App\Support\Parametres;
use Illuminate\Support\Facades\Storage;

/*
 * PDF de l'OM après un amendement (avenant). L'amendement change les montants
 * sans toucher aux statuts : le PDF ne doit ni être servi depuis le cache
 * d'avant l'amendement, ni présenter un reliquat ajusté (voire négatif) comme
 * « 30 % ». Cf. docs/amendement-om.md §4.6.
 * Base : 6 j × 45 000 = 270 000, avance 189 000 (70 %), reliquat 81 000.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->gestionnaire = User::factory()->gestionnaireOm()->create();

    Bareme::factory()->create([
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR',
        'type_prise_en_charge' => 'JOURNEE_COMPLETE', 'taux_journalier' => 45000,
    ]);
    $mission = Mission::factory()->create(['zone_code' => 'INTERIEUR', 'prise_en_charge_tiers' => false]);
    $this->om = OrdreMission::factory()->create([
        'mission_id' => $mission->id, 'statut' => 'IMPORTE',
        'categorie' => 'INDICE_SUP_800', 'zone_code' => 'INTERIEUR', 'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 6, 'montant_total' => 270000, 'avance' => 189000, 'reliquat' => 81000,
        'montant_transport' => 0, 'lignes_per_diem' => null,
    ]);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'AVANCE', 'montant' => 189000, 'statut' => 'EN_ATTENTE']);
    Versement::factory()->create(['ordre_mission_id' => $this->om->id, 'type' => 'RELIQUAT', 'montant' => 81000, 'statut' => 'EN_ATTENTE']);

    $this->pdf = app(PdfGenerator::class);
});

function amenderPourPdf(object $t, int $jours, string $motif = 'Réduction de la durée constatée par avenant.'): void
{
    $t->actingAs($t->gestionnaire)->postJson("/api/ordres/{$t->om->id}/amender", [
        'nb_jours' => $jours, 'motif' => $motif,
    ])->assertOk();
}

it('ne sert pas le PDF d’avant l’amendement depuis le cache', function () {
    $this->actingAs($this->gestionnaire)->get("/api/ordres/{$this->om->id}/pdf/download")->assertOk();
    $avant = Storage::disk('local')->files('ordres');

    $this->om->update(['avance_engagee' => 189000]);
    amenderPourPdf($this, 3);

    $this->actingAs($this->gestionnaire)->get("/api/ordres/{$this->om->id}/pdf/download")->assertOk();
    $nouveaux = array_values(array_diff(Storage::disk('local')->files('ordres'), $avant));

    // Nouvelle clé de cache, donc PDF régénéré sur les montants amendés.
    expect($nouveaux)->toHaveCount(1)
        ->and($nouveaux[0])->toEndWith('-AMD1.pdf');
});

it('présente un trop-perçu comme tel, et non comme un reliquat de 30 %', function () {
    $this->om->update(['avance_engagee' => 189000]);
    amenderPourPdf($this, 3);

    $d = $this->pdf->donnees($this->om->fresh());

    expect($d['reliquat'])->toBe('-54 000')
        ->and($d['reliquat_libelle'])->toBe('Trop-perçu (à recouvrer par retenue sur salaire)')
        ->and($d['avance_libelle'])->toBe('Avance engagée')
        ->and($d['trop_percu'])->toBeTrue()
        ->and($d['amendement']['nb_jours_avant'])->toBe(6)
        ->and($d['amendement']['nb_jours_apres'])->toBe(3);
});

it('signale un reliquat ajusté après une rallonge', function () {
    $this->om->update(['avance_engagee' => 189000]);
    amenderPourPdf($this, 8, 'Rallonge de deux jours après paiement de l’avance.');

    $d = $this->pdf->donnees($this->om->fresh());

    expect($d['reliquat'])->toBe('171 000')
        ->and($d['reliquat_libelle'])->toBe('Reliquat (ajusté par amendement)')
        ->and($d['trop_percu'])->toBeFalse();
});

it('tire le pourcentage des montants de l’OM, pas du paramètre courant', function () {
    // La part d'avance a été portée à 60 % après la retranscription à 70 %.
    file_put_contents(storage_path('app/settings.json'), json_encode(['partAvance' => 60]));
    Parametres::invalidateCache();

    $d = $this->pdf->donnees($this->om->fresh());

    expect($d['avance_libelle'])->toBe('Avance (70 %)')
        ->and($d['reliquat_libelle'])->toBe('Reliquat (30 %)')
        ->and($d['amendement'])->toBeNull();

    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

it('affiche le taux journalier hors transport', function () {
    $this->om->update(['montant_transport' => 30000, 'montant_total' => 300000]);

    expect($this->pdf->donnees($this->om->fresh())['taux_journalier'])->toBe('45 000');
});

it('retient le dernier amendement même quand deux tombent dans la même seconde', function () {
    $this->om->update(['avance_engagee' => 189000]);
    amenderPourPdf($this, 8, 'Rallonge de deux jours après paiement de l’avance.');
    amenderPourPdf($this, 3, 'Mission écourtée, retour anticipé constaté.');

    // amende_le est précis à la seconde : on force l'égalité pour rendre le cas certain.
    $instant = now()->startOfSecond();
    OmAmendement::where('ordre_mission_id', $this->om->id)->update(['amende_le' => $instant]);

    $om = $this->om->fresh();
    $d = $this->pdf->donnees($om);
    expect($d['amendement']['nb_jours_avant'])->toBe(8)
        ->and($d['amendement']['nb_jours_apres'])->toBe(3)
        ->and($d['amendement']['nombre'])->toBe(2);

    // Document de retenue (trop-perçu) : motif du dernier amendement.
    $versement = Versement::where('ordre_mission_id', $om->id)->where('type', 'RELIQUAT')->firstOrFail();
    expect($versement->statut)->toBe('A_RECOUVRER')
        ->and($this->pdf->donneesRetenue($versement)['motif'])->toBe('Mission écourtée, retour anticipé constaté.');

    // Journal de la fiche OM : le dernier en tête.
    $this->actingAs($this->gestionnaire)->getJson("/api/ordres/{$om->id}")
        ->assertOk()
        ->assertJsonPath('data.amendements.0.nb_jours_apres', 3)
        ->assertJsonPath('data.amendements.1.nb_jours_apres', 8);
});
