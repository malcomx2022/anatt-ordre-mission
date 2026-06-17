<?php

use App\Models\Agent;
use App\Models\LotPaiement;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Versement;
use App\Services\Paiement\MoovFileGenerator;
use App\Services\Paiement\MtnFileGenerator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Construit un versement complet (agent + OM rattaché à la mission). */
function fabriquerVersement(Mission $mission, array $agentAttrs, string $tranche, int $montant): void
{
    $agent = Agent::create(array_merge([
        'nom' => 'NOM', 'prenoms' => 'Prenom', 'matricule' => 'M-'.uniqid(),
        'categorie' => 'ORDRE_1', 'telephone' => '0190000000', 'operateur' => 'MTN', 'actif' => true,
    ], $agentAttrs));

    $om = OrdreMission::create([
        'reference_semo' => 'REF-'.uniqid(),
        'mission_id' => $mission->id,
        'agent_id' => $agent->id,
        'role' => 'PARTICIPANT',
        'categorie' => $agent->categorie,
        'zone_code' => $mission->zone_code,
        'type_prise_en_charge' => 'JOURNEE_COMPLETE',
        'nb_jours' => 3,
        'montant_total' => $montant * 3,
        'avance' => $montant,
        'reliquat' => $montant * 2,
        'statut' => 'VALIDE', // prérequis : seul un OM validé est payable
    ]);

    Versement::create([
        'ordre_mission_id' => $om->id,
        'type' => $tranche,
        'montant' => $montant,
        'operateur' => $agent->operateur,
        'telephone' => $agent->telephone,
        'statut' => 'EN_ATTENTE',
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->agentFinancier()->create();
    $this->mission = Mission::create([
        'objet' => 'Atelier Régional',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01',
        'date_retour' => '2026-06-03',
        'prise_en_charge_tiers' => false,
    ]);
});

it('génère le fichier MTN .txt au format exact (octet à octet) et pilote le lot', function () {
    fabriquerVersement($this->mission, [
        'nom' => 'DADA', 'prenoms' => 'Richard', 'telephone' => '0167267455', 'operateur' => 'MTN',
    ], 'AVANCE', 45000);

    $resultat = app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);

    $contenu = Storage::disk('local')->get($resultat['chemin']);
    $attendu = "HDR,SARALY,1,45000\r\n"
        ."1,MSISDN,2290167267455,45000,DADA RICHARD ATELIER REGIONAL\r\n";

    expect($contenu)->toBe($attendu);                      // octet à octet
    expect(basename($resultat['chemin']))->toBe('atelier-regional_AVANCE.txt');
    expect(substr_count($contenu, "\n"))->toBe(substr_count($contenu, "\r\n")); // CRLF strict
    expect(str_ends_with($contenu, "\r\n"))->toBeTrue();

    // Le générateur pilote lui-même le lot et le statut des versements.
    $lot = LotPaiement::first();
    expect($lot->statut)->toBe('GENERE')
        ->and($lot->nb_lignes)->toBe(1)
        ->and($lot->montant_total)->toBe(45000)
        ->and($lot->genere_par)->toBe($this->user->id);
    expect(Versement::first()->statut)->toBe('GENERE');
});

it('recalcule en-tête NB_LIGNES et MONTANT_TOTAL depuis le détail', function () {
    fabriquerVersement($this->mission, ['nom' => 'A', 'prenoms' => 'Un', 'telephone' => '0190000001', 'operateur' => 'MTN'], 'AVANCE', 10000);
    fabriquerVersement($this->mission, ['nom' => 'B', 'prenoms' => 'Deux', 'telephone' => '0190000002', 'operateur' => 'MTN'], 'AVANCE', 25000);

    $resultat = app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);

    expect(Storage::disk('local')->get($resultat['chemin']))->toStartWith("HDR,SARALY,2,35000\r\n");
    expect($resultat['lot']->montant_total)->toBe(35000);
});

it('n’inclut que les versements MTN (périmètre auto-appliqué)', function () {
    fabriquerVersement($this->mission, ['nom' => 'MTNAG', 'prenoms' => 'X', 'telephone' => '0190000003', 'operateur' => 'MTN'], 'AVANCE', 45000);
    fabriquerVersement($this->mission, ['nom' => 'MOOVAG', 'prenoms' => 'Y', 'telephone' => '0140000004', 'operateur' => 'MOOV'], 'AVANCE', 30000);

    $resultat = app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);

    expect($resultat['lot']->nb_lignes)->toBe(1)        // seul le versement MTN
        ->and($resultat['lot']->montant_total)->toBe(45000);
});

it('bloque la régénération d’un lot confirmé', function () {
    fabriquerVersement($this->mission, ['nom' => 'C', 'prenoms' => 'Trois', 'telephone' => '0190000005', 'operateur' => 'MTN'], 'AVANCE', 45000);
    $resultat = app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);
    $resultat['lot']->update(['statut' => 'CONFIRME']);

    app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);
})->throws(\Illuminate\Validation\ValidationException::class);

it('génère le fichier Moov .xlsx au format exact', function () {
    fabriquerVersement($this->mission, [
        'nom' => 'AKPACA', 'prenoms' => 'Gérard', 'telephone' => '0140000002', 'operateur' => 'MOOV', 'categorie' => 'ORDRE_3',
    ], 'AVANCE', 30000);

    $resultat = app(MoovFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user);

    expect(basename($resultat['chemin']))->toBe('atelier-regional_AVANCE.xlsx');
    expect($resultat['lot']->statut)->toBe('GENERE');

    $sheet = IOFactory::load(Storage::disk('local')->path($resultat['chemin']))->getActiveSheet();

    expect($sheet->getTitle())->toBe('Sheet1');

    $entete = ['Receiver Identifier Type', 'IdentifierValue', 'Validation KYC', 'Validation KYC value',
        'PayerAccountName', 'AccountName', 'Amount', 'Comment', 'VoucherSecretCode'];
    foreach ($entete as $i => $titre) {
        expect($sheet->getCell([$i + 1, 1])->getValue())->toBe($titre);
    }

    expect($sheet->getCell('A2')->getValue())->toBe('MSISDN');
    expect($sheet->getCell('B2')->getValue())->toBe('2290140000002');
    expect($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);
    expect($sheet->getCell('C2')->getValue())->toBeNull();
    expect($sheet->getCell('G2')->getValue())->toBe(30000);
    expect($sheet->getCell('H2')->getValue())->toBe('AKPACA GERARD ATELIER REGIONAL');
    expect($sheet->getCell('I2')->getValue())->toBeNull();
});

it('neutralise l’injection de formule dans le libellé (MTN .txt)', function () {
    // Un nom d'agent débutant par "=" serait réinterprété comme formule à l'ouverture
    // du fichier dans un tableur. Le libellé doit être préfixé d'une apostrophe.
    fabriquerVersement($this->mission, [
        'nom' => '=HYPERLINK("http://evil")', 'prenoms' => 'X', 'telephone' => '0167000099', 'operateur' => 'MTN',
    ], 'AVANCE', 45000);

    $contenu = Storage::disk('local')->get(
        app(MtnFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user)['chemin']
    );

    // Le libellé (dernier champ) ne doit jamais commencer par = + - @.
    $detail = explode("\r\n", $contenu)[1];
    $libelle = substr($detail, strrpos($detail, ',') + 1);
    expect($libelle[0])->toBe("'");
});

it('neutralise l’injection de formule dans le libellé (Moov .xlsx)', function () {
    fabriquerVersement($this->mission, [
        'nom' => '@SUM(A1)', 'prenoms' => 'Y', 'telephone' => '0140000099', 'operateur' => 'MOOV',
    ], 'AVANCE', 30000);

    $sheet = IOFactory::load(Storage::disk('local')->path(
        app(MoovFileGenerator::class)->generer($this->mission->id, 'AVANCE', $this->user)['chemin']
    ))->getActiveSheet();

    expect($sheet->getCell('H2')->getValue())->toStartWith("'@SUM");
});
