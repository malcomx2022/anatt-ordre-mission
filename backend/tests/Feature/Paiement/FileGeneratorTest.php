<?php

use App\Models\Agent;
use App\Models\Mission;
use App\Models\OrdreMission;
use App\Models\Versement;
use App\Services\Paiement\MoovFileGenerator;
use App\Services\Paiement\MtnFileGenerator;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Construit un versement complet (agent + mission + OM) et renvoie la collection
 * des versements éligibles d'une tranche/opérateur, relations chargées.
 */
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
        'statut' => 'IMPORTE',
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

function versementsEligibles(int $missionId, string $tranche, string $operateur)
{
    return Versement::with('ordreMission.agent')
        ->where('type', $tranche)
        ->where('operateur', $operateur)
        ->where('statut', '!=', 'CONFIRME')
        ->whereHas('ordreMission', fn ($q) => $q->where('mission_id', $missionId))
        ->orderBy('id')
        ->get();
}

beforeEach(function () {
    Storage::fake('local');
    $this->mission = Mission::create([
        'objet' => 'Atelier Régional',
        'destination' => 'Bohicon',
        'zone_code' => 'INTERIEUR',
        'date_depart' => '2026-06-01',
        'date_retour' => '2026-06-03',
        'prise_en_charge_tiers' => false,
    ]);
});

it('génère le fichier MTN .txt au format exact (octet à octet)', function () {
    fabriquerVersement($this->mission, [
        'nom' => 'DADA', 'prenoms' => 'Richard', 'telephone' => '0167267455', 'operateur' => 'MTN',
    ], 'AVANCE', 45000);

    $versements = versementsEligibles($this->mission->id, 'AVANCE', 'MTN');
    $meta = app(MtnFileGenerator::class)->generer($this->mission, 'AVANCE', $versements);

    $contenu = Storage::disk('local')->get($meta['chemin']);

    $attendu = "HDR,SARALY,1,45000\r\n"
        ."1,MSISDN,2290167267455,45000,DADA RICHARD ATELIER REGIONAL\r\n";

    expect($contenu)->toBe($attendu);                      // octet à octet
    expect($meta['nom_fichier'])->toBe('atelier-regional_AVANCE.txt');
    expect($meta['nb_lignes'])->toBe(1);
    expect($meta['montant_total'])->toBe(45000);
    // CRLF strict (aucun \n isolé) + saut de ligne final.
    expect(substr_count($contenu, "\n"))->toBe(substr_count($contenu, "\r\n"));
    expect(str_ends_with($contenu, "\r\n"))->toBeTrue();
});

it('recalcule en-tête NB_LIGNES et MONTANT_TOTAL depuis le détail', function () {
    fabriquerVersement($this->mission, ['nom' => 'A', 'prenoms' => 'Un', 'telephone' => '0190000001', 'operateur' => 'MTN'], 'AVANCE', 10000);
    fabriquerVersement($this->mission, ['nom' => 'B', 'prenoms' => 'Deux', 'telephone' => '0190000002', 'operateur' => 'MTN'], 'AVANCE', 25000);

    $versements = versementsEligibles($this->mission->id, 'AVANCE', 'MTN');
    $meta = app(MtnFileGenerator::class)->generer($this->mission, 'AVANCE', $versements);

    $contenu = Storage::disk('local')->get($meta['chemin']);
    expect($contenu)->toStartWith("HDR,SARALY,2,35000\r\n");
    expect($meta['montant_total'])->toBe(35000);
});

it('génère le fichier Moov .xlsx au format exact', function () {
    fabriquerVersement($this->mission, [
        'nom' => 'AKPACA', 'prenoms' => 'Gérard', 'telephone' => '0140000002', 'operateur' => 'MOOV', 'categorie' => 'ORDRE_3',
    ], 'AVANCE', 30000);

    $versements = versementsEligibles($this->mission->id, 'AVANCE', 'MOOV');
    $meta = app(MoovFileGenerator::class)->generer($this->mission, 'AVANCE', $versements);

    expect($meta['nom_fichier'])->toBe('atelier-regional_AVANCE.xlsx');

    $sheet = IOFactory::load(Storage::disk('local')->path($meta['chemin']))->getActiveSheet();

    expect($sheet->getTitle())->toBe('Sheet1');

    // En-tête 9 colonnes, ordre exact.
    $entete = ['Receiver Identifier Type', 'IdentifierValue', 'Validation KYC', 'Validation KYC value',
        'PayerAccountName', 'AccountName', 'Amount', 'Comment', 'VoucherSecretCode'];
    foreach ($entete as $i => $titre) {
        expect($sheet->getCell([$i + 1, 1])->getValue())->toBe($titre);
    }

    // Ligne bénéficiaire.
    expect($sheet->getCell('A2')->getValue())->toBe('MSISDN');
    expect($sheet->getCell('B2')->getValue())->toBe('2290140000002');
    expect($sheet->getCell('B2')->getDataType())->toBe(DataType::TYPE_STRING);   // numéro en TEXTE
    expect($sheet->getCell('C2')->getValue())->toBeNull();
    expect($sheet->getCell('F2')->getValue())->toBeNull();
    expect($sheet->getCell('G2')->getValue())->toBe(30000);                       // montant numérique entier
    expect($sheet->getCell('H2')->getValue())->toBe('AKPACA GERARD ATELIER REGIONAL');
    expect($sheet->getCell('I2')->getValue())->toBeNull();
});
