<?php

use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Les modèles d'import distribués aux utilisateurs restent importables.
 *
 * `docs/modele-import-agents.csv` et son jumeau `.xlsx` sont les fichiers que
 * l'ANaTT télécharge depuis l'écran PERS-06 puis remplit. Le CSV utilise le
 * point-virgule — ce que produit et attend Excel en configuration française,
 * celle des postes de l'agence.
 *
 * Que ce fichier soit lisible ne tient à aucun réglage du projet : c'est le
 * défaut `excel.imports.csv.delimiter = null` du paquet maatwebsite/excel qui
 * laisse PhpSpreadsheet INFÉRER le séparateur. Publier `config/excel.php` et
 * « harmoniser » ce délimiteur sur celui des exports (`,`) suffirait à rendre
 * le modèle illisible — sans erreur bruyante : une seule colonne serait
 * reconnue et l'import échouerait sur un rapport d'erreurs incompréhensible.
 *
 * Ces tests transforment cette dépendance implicite en garantie explicite.
 * S'ils tombent, la question à se poser est : qui a figé le délimiteur d'import ?
 */
beforeEach(function () {
    $this->gestion = User::factory()->gestionnaireOm()->create();
});

/** Copie de travail du modèle : l'import ne doit jamais consommer le fichier du dépôt. */
function modeleImport(string $extension): UploadedFile
{
    $source = dirname(base_path())."/docs/modele-import-agents.{$extension}";

    expect(file_exists($source))->toBeTrue(
        "Le modèle d'import distribué est introuvable : {$source}"
    );

    $copie = tempnam(sys_get_temp_dir(), 'modele').".{$extension}";
    copy($source, $copie);

    return new UploadedFile($copie, "modele-import-agents.{$extension}", null, null, true);
}

it('importe le modèle CSV distribué, séparateur « ; » et BOM compris', function () {
    $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => modeleImport('csv')])
        ->assertOk()
        ->assertJsonPath('data.importes', 5)
        ->assertJsonPath('data.rollback', false)
        ->assertJsonPath('data.erreurs', []);

    // Les colonnes sont non seulement lues, mais alignées : un séparateur mal
    // interprété donnerait une colonne unique et ferait échouer la validation
    // bien avant d'arriver ici.
    $agent = Agent::where('matricule', 'ANT-2001')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->nom)->toBe('SOUMANOU DJARA')
        ->and($agent->prenoms)->toBe('Sabirou')
        ->and($agent->grade)->toBe('Cadre A1')
        ->and($agent->indice)->toBe(850)
        ->and($agent->salaire_brut)->toBe(520000)
        ->and($agent->categorie)->toBe('INDICE_SUP_800')
        ->and($agent->telephone)->toBe('0197984877')
        ->and($agent->operateur)->toBe('MTN');
});

it('couvre les trois catégories d’indice et les deux opérateurs', function () {
    $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => modeleImport('csv')])
        ->assertOk();

    // Un modèle qui n'exercerait qu'une catégorie ou qu'un opérateur laisserait
    // l'utilisateur sans exemple pour les autres.
    expect(Agent::distinct()->pluck('categorie')->sort()->values()->all())
        ->toBe(['INDICE_400_799', 'INDICE_INF_400', 'INDICE_SUP_800'])
        ->and(Agent::distinct()->pluck('operateur')->sort()->values()->all())
        ->toBe(['MOOV', 'MTN']);
});

it('importe le modèle XLSX distribué', function () {
    $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => modeleImport('xlsx')])
        ->assertOk()
        ->assertJsonPath('data.importes', 5)
        ->assertJsonPath('data.rollback', false);

    // Le téléphone doit survivre en TEXTE : stocké en numérique, le « 0 » de tête
    // sauterait et le numéro deviendrait injoignable en Mobile Money.
    expect(Agent::where('matricule', 'ANT-2001')->value('telephone'))->toBe('0197984877');
});

it('lit aussi un fichier à séparateur « , », produit par Excel en configuration anglaise', function () {
    $csv = implode("\n", [
        'nom,prenoms,matricule,ifu,grade,indice,salaire_brut,categorie,telephone,operateur',
        'TOSSOU,Marc,ANT-3001,,Cadre,820,480000,INDICE_SUP_800,0197000001,MTN',
    ]);

    $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => UploadedFile::fake()->createWithContent('agents.csv', $csv)])
        ->assertOk()
        ->assertJsonPath('data.importes', 1);

    expect(Agent::where('matricule', 'ANT-3001')->value('categorie'))->toBe('INDICE_SUP_800');
});

it('motive une ligne sans indice ni salaire brut par l’indice seul, comme la création', function () {
    $csv = implode("\n", [
        'nom;prenoms;matricule;ifu;grade;indice;salaire_brut;categorie;telephone;operateur',
        'TOSSOU;Marc;ANT-4001;;Cadre;;;;0197000002;MTN',
    ]);

    $reponse = $this->actingAs($this->gestion)
        ->post('/api/agents/import', ['fichier' => UploadedFile::fake()->createWithContent('agents.csv', $csv)])
        ->assertStatus(422);

    $motifs = collect($reponse->json('errors'))->pluck('erreurs')->flatten()->all();
    expect($motifs)->toContain("L'indice ou le salaire brut est requis pour déterminer la catégorie.")
        ->and($motifs)->not->toContain('Le champ categorie est obligatoire.')
        ->and(Agent::where('matricule', 'ANT-4001')->exists())->toBeFalse();
});
