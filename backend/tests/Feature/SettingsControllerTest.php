<?php

use App\Models\User;
use App\Support\Parametres;
use Illuminate\Support\Facades\Crypt;

/*
 * Paramètres système (SettingsController) : liste blanche d'écriture, secret SMTP
 * chiffré au repos et jamais renvoyé, fusion partielle avec l'existant, validation
 * du pourcentage d'avance. Le gating par permission est couvert par RoleMiddlewareTest.
 */

beforeEach(function () {
    $this->su = User::factory()->superAdmin()->create();
});

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateCache();
});

/** Écrit directement le fichier de réglages (contourne l'API). */
function ecrireSettings(array $data): void
{
    file_put_contents(storage_path('app/settings.json'), json_encode($data));
}

it('masque le secret SMTP en lecture', function () {
    ecrireSettings(['smtpPassword' => Crypt::encryptString('s3cret'), 'appName' => 'ANaTT']);

    $this->actingAs($this->su)->getJson('/api/admin/settings')
        ->assertOk()
        ->assertJsonPath('data.smtpPassword', '********')
        ->assertJsonPath('data.appName', 'ANaTT');
});

it('n’écrit que les clés de la liste blanche', function () {
    $this->actingAs($this->su)->putJson('/api/admin/settings', [
        'appName' => 'ANaTT Missions',
        'cleInconnue' => 'valeur pirate',
    ])->assertOk();

    $ecrit = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    expect($ecrit)->toHaveKey('appName')
        ->and($ecrit)->not->toHaveKey('cleInconnue');
});

it('refuse un pourcentage d’avance hors bornes', function () {
    $this->actingAs($this->su)->putJson('/api/admin/settings', ['partAvance' => 150])
        ->assertStatus(422)
        ->assertJsonValidationErrors('partAvance');
});

it('refuse un pourcentage d’avance non entier', function () {
    $this->actingAs($this->su)->putJson('/api/admin/settings', ['partAvance' => 'beaucoup'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('partAvance');
});

it('chiffre le secret SMTP au repos et le masque en réponse', function () {
    $this->actingAs($this->su)->putJson('/api/admin/settings', ['smtpPassword' => 'motdepasse'])
        ->assertOk()
        ->assertJsonPath('data.smtpPassword', '********');

    $ecrit = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    expect($ecrit['smtpPassword'])->not->toBe('motdepasse')
        ->and(Crypt::decryptString($ecrit['smtpPassword']))->toBe('motdepasse');
});

it('conserve le secret SMTP existant quand le masque est renvoyé', function () {
    $chiffre = Crypt::encryptString('conserve-moi');
    ecrireSettings(['smtpPassword' => $chiffre]);

    $this->actingAs($this->su)->putJson('/api/admin/settings', [
        'smtpPassword' => '********', 'appName' => 'X',
    ])->assertOk();

    $ecrit = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    expect($ecrit['smtpPassword'])->toBe($chiffre)
        ->and(Crypt::decryptString($ecrit['smtpPassword']))->toBe('conserve-moi');
});

it('fusionne sans effacer les clés existantes', function () {
    ecrireSettings(['smtpHost' => 'mail.example.org', 'appName' => 'Ancien']);

    $this->actingAs($this->su)->putJson('/api/admin/settings', ['appName' => 'Nouveau'])->assertOk();

    $ecrit = json_decode(file_get_contents(storage_path('app/settings.json')), true);
    expect($ecrit['appName'])->toBe('Nouveau')
        ->and($ecrit['smtpHost'])->toBe('mail.example.org');
});

it('invalide le cache de la part d’avance après écriture', function () {
    ecrireSettings(['partAvance' => 70]);
    Parametres::invalidateCache();
    expect(Parametres::partAvance())->toBe(70);

    $this->actingAs($this->su)->putJson('/api/admin/settings', ['partAvance' => 60])->assertOk();

    expect(Parametres::partAvance())->toBe(60);
});
