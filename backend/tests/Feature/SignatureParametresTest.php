<?php

use App\Models\User;
use App\Services\SignatureParametres;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Signatures des signataires du circuit, configurées dans les Paramètres.
 * Une image de signature est une donnée sensible : elle ne doit jamais devenir
 * accessible par une URL publique, et son type doit être établi d'après le
 * contenu réel du fichier, jamais d'après son extension.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->superAdmin = User::factory()->superAdmin()->create();
});

/** Vraie image PNG minimale (1×1), pour que getimagesize la reconnaisse. */
function pngReel(string $nom = 'signature.png'): UploadedFile
{
    $binaire = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
    );

    return UploadedFile::fake()->createWithContent($nom, $binaire);
}

it('expose les trois postes du circuit', function () {
    $this->actingAs($this->superAdmin)->getJson('/api/admin/signatures')
        ->assertOk()
        ->assertJsonStructure(['data' => ['controleur', 'daf', 'dg']]);
});

it('enregistre l’image de signature d’un poste', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => pngReel()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.daf.signature_enregistree', true);

    expect(Storage::disk('local')->exists('signatures/daf.png'))->toBeTrue();
});

it('range les signatures sur le disque privé, hors de toute URL publique', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/dg', ['signature' => pngReel()], ['Accept' => 'application/json'])->assertOk();

    // Le disque 'local' n'est pas servi par le serveur web : rien dans public/.
    expect(Storage::disk('local')->exists('signatures/dg.png'))->toBeTrue()
        ->and(Storage::disk('local')->path('signatures/dg.png'))->not->toContain('/public/');
});

it('ne renvoie jamais l’image elle-même dans l’API', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => pngReel()], ['Accept' => 'application/json'])->assertOk();

    $reponse = $this->actingAs($this->superAdmin)->getJson('/api/admin/signatures')->json('data');

    foreach ($reponse as $poste) {
        expect($poste)->not->toHaveKey('image');
    }
});

it('refuse un fichier qui n’est pas une image malgré son extension', function () {
    // Extension .png mais contenu texte : le contrôle porte sur le contenu réel.
    $faux = UploadedFile::fake()->createWithContent('signature.png', 'ceci nest pas une image');

    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => $faux], ['Accept' => 'application/json'])
        ->assertStatus(422);

    expect(Storage::disk('local')->exists('signatures/daf.png'))->toBeFalse();
});

it('rejette un poste inconnu', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/tresorier', ['signature' => pngReel()], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('supprime une signature enregistrée', function () {
    $this->actingAs($this->superAdmin)
        ->post('/api/admin/signatures/daf', ['signature' => pngReel()], ['Accept' => 'application/json'])->assertOk();

    $this->actingAs($this->superAdmin)->deleteJson('/api/admin/signatures/daf')
        ->assertOk()
        ->assertJsonPath('data.daf.signature_enregistree', false);

    expect(Storage::disk('local')->exists('signatures/daf.png'))->toBeFalse();
});

it('réserve la configuration des signatures au super administrateur', function () {
    foreach ([User::factory()->admin()->create(), User::factory()->daf()->create()] as $utilisateur) {
        $this->actingAs($utilisateur)->getJson('/api/admin/signatures')->assertForbidden();
    }
});

it('compose le nom imprimé en « Prénoms NOM »', function () {
    file_put_contents(storage_path('app/settings.json'), json_encode([
        'dafNom' => 'ATOHOUN', 'dafPrenoms' => 'Euloge D.',
    ]));

    expect(SignatureParametres::pour('daf')['nom_complet'])->toBe('Euloge D. ATOHOUN');

    @unlink(storage_path('app/settings.json'));
});

it('retombe sur le libellé de fonction par défaut si rien n’est configuré', function () {
    expect(SignatureParametres::pour('daf')['fonction'])
        ->toBe("Le Directeur de l'Administration et des Finances")
        ->and(SignatureParametres::pour('dg')['fonction'])->toBe('Le Directeur Général');
});
