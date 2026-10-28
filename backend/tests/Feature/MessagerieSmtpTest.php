<?php

use App\Jobs\SendNotification;
use App\Models\User;
use App\Support\Messagerie;
use App\Support\Parametres;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/*
 * Configuration SMTP de l'écran Messagerie (/admin/email), appliquée à l'envoi.
 *
 * Jusqu'au 28/10/2026 elle était « indicative » (l'envoi suivait le .env seul).
 * Serveur renseigné : il remplace le transport MAIL_* ; expéditeur renseigné : il
 * remplace MAIL_FROM_*. Rien de réglé : le .env s'applique, comme avant.
 */

function reglagesMail(array $valeurs): void
{
    file_put_contents(storage_path('app/settings.json'), json_encode($valeurs));
    Parametres::invalidateReglages();
}

function dernierMessage(): Email
{
    return app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

beforeEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateReglages();
    $this->superAdmin = User::factory()->superAdmin()->create(['email' => 'sa.mail@anatt.bj']);
});

afterEach(function () {
    @unlink(storage_path('app/settings.json'));
    Parametres::invalidateReglages();
    Messagerie::appliquer();
});

it('garde le transport et l’expéditeur du .env tant que rien n’est réglé', function () {
    Messagerie::appliquer();

    expect(config('mail.default'))->toBe('array')
        ->and(Parametres::smtp())->toBeNull()
        ->and(Parametres::expediteur())->toBe([]);
});

it('remplace le transport du .env par le serveur SMTP enregistré', function () {
    $this->actingAs($this->superAdmin)->putJson('/api/admin/settings', [
        'smtpHost' => 'mail.anatt.bj', 'smtpPort' => 587, 'smtpUser' => 'noreply@anatt.bj',
        'smtpPassword' => 's3cret!', 'smtpEncryption' => 'TLS',
    ])->assertOk()->assertJsonPath('data.smtpPassword', '********');

    Messagerie::appliquer();

    expect(config('mail.default'))->toBe('smtp');
    $transport = Mail::mailer()->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and((string) $transport)->toBe('smtp://mail.anatt.bj:587')
        ->and($transport->getUsername())->toBe('noreply@anatt.bj')
        ->and($transport->getPassword())->toBe('s3cret!') // déchiffré à l'envoi seulement
        ->and($transport->isTlsRequired())->toBeTrue();
});

it('traduit le chiffrement choisi : SSL implicite, ou aucun', function () {
    reglagesMail(['smtpHost' => 'mail.anatt.bj', 'smtpPort' => 465, 'smtpEncryption' => 'SSL']);
    Messagerie::appliquer();
    $ssl = Mail::mailer()->getSymfonyTransport();
    expect($ssl->getStream()->isTLS())->toBeTrue();

    reglagesMail(['smtpHost' => 'relais.local', 'smtpPort' => 2525, 'smtpEncryption' => 'Aucun']);
    Messagerie::appliquer();
    $clair = Mail::mailer()->getSymfonyTransport();
    expect((string) $clair)->toBe('smtp://relais.local:2525')
        ->and($clair->getStream()->isTLS())->toBeFalse()
        ->and($clair->isAutoTls())->toBeFalse()
        ->and($clair->isTlsRequired())->toBeFalse();
});

it('revient au .env quand le serveur est effacé (worker de longue durée)', function () {
    reglagesMail(['smtpHost' => 'mail.anatt.bj']);
    Messagerie::appliquer();
    expect(config('mail.default'))->toBe('smtp');

    reglagesMail(['smtpHost' => '']);
    Messagerie::appliquer();
    expect(config('mail.default'))->toBe('array')
        ->and(Mail::mailer()->getSymfonyTransport())->not->toBeInstanceOf(EsmtpTransport::class);
});

it('envoie les notifications sous l’expéditeur réglé', function () {
    reglagesMail(['fromAddress' => 'missions@anatt.bj', 'fromName' => 'ANaTT Missions']);
    $user = User::factory()->agentFinancier()->create();

    (new SendNotification($user->id, 'om_rejete', ['reference_semo' => 'SEMO-1', 'motif' => 'x']))->handle();

    $from = dernierMessage()->getFrom()[0];
    expect($from->getAddress())->toBe('missions@anatt.bj')
        ->and($from->getName())->toBe('ANaTT Missions');
});

it('envoie le lien de réinitialisation sous l’expéditeur réglé', function () {
    reglagesMail(['fromAddress' => 'missions@anatt.bj']);

    $this->postJson('/api/auth/mot-de-passe/email', ['email' => 'sa.mail@anatt.bj'])->assertOk();

    expect(dernierMessage()->getFrom()[0]->getAddress())->toBe('missions@anatt.bj')
        ->and(dernierMessage()->getTo()[0]->getAddress())->toBe('sa.mail@anatt.bj');
});

it('refuse une configuration de messagerie invalide', function (string $cle, mixed $valeur) {
    $this->actingAs($this->superAdmin)->putJson('/api/admin/settings', [$cle => $valeur])
        ->assertStatus(422)
        ->assertJsonValidationErrors($cle);
})->with([
    'serveur avec espace' => ['smtpHost', 'mail anatt.bj'],
    'serveur non textuel' => ['smtpHost', 12],
    'port nul' => ['smtpPort', 0],
    'port trop grand' => ['smtpPort', 65536],
    'port non numérique' => ['smtpPort', 'abc'],
    'chiffrement inconnu' => ['smtpEncryption', 'STARTTLS'],
    'adresse invalide' => ['fromAddress', 'pas-une-adresse'],
    'nom trop long' => ['fromName', str_repeat('a', 101)],
]);

it('accepte un serveur vide (retour au .env) et normalise le port', function () {
    $this->actingAs($this->superAdmin)->putJson('/api/admin/settings', [
        'smtpHost' => '  ', 'smtpPort' => '2525', 'fromAddress' => '',
    ])->assertOk()
        ->assertJsonPath('data.smtpHost', '')
        ->assertJsonPath('data.smtpPort', 2525);

    expect(Parametres::smtp())->toBeNull();
});

it('envoie un e-mail de test au super administrateur avec la configuration enregistrée', function () {
    reglagesMail(['appName' => 'ANaTT Missions', 'fromAddress' => 'missions@anatt.bj']);

    $this->actingAs($this->superAdmin)->postJson('/api/admin/settings/test-email')
        ->assertOk()
        ->assertJsonPath('data.destinataire', 'sa.mail@anatt.bj')
        ->assertJsonPath('data.transport', 'array');

    expect(dernierMessage()->getSubject())->toBe('[ANaTT Missions] Test de la configuration e-mail')
        ->and(dernierMessage()->getFrom()[0]->getAddress())->toBe('missions@anatt.bj');
});

it('rend compte d’un échec d’envoi au lieu de le taire', function () {
    reglagesMail(['smtpHost' => '127.0.0.1', 'smtpPort' => 1, 'smtpEncryption' => 'Aucun']);

    $this->actingAs($this->superAdmin)->postJson('/api/admin/settings/test-email')
        ->assertStatus(422)
        ->assertJsonValidationErrors('smtpHost');
});

it('réserve l’e-mail de test au super administrateur', function () {
    $this->actingAs(User::factory()->admin()->create())->postJson('/api/admin/settings/test-email')
        ->assertForbidden();
});
