<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\NotificationContenu;
use App\Support\Parametres;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly string $type,
        private readonly array $data = [],
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning("SendNotification: utilisateur {$this->userId} introuvable");

            return;
        }

        // Compte désactivé entre la notification et l'envoi (file d'attente) : pas d'e-mail.
        if (! $user->actif) {
            return;
        }

        $subject = $this->getSubject();
        $body = $this->getBody();

        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)
                    ->subject($subject);
            });

            Log::info("SendNotification: email '{$this->type}' envoye a {$user->email}");
        } catch (\Exception $e) {
            Log::error("SendNotification: echec envoi email a {$user->email} - ".$e->getMessage());
            throw $e;
        }
    }

    private function getSubject(): string
    {
        // Préfixé du nom de l'application (Paramètres › Général) : l'e-mail dit d'où il vient.
        return '['.Parametres::nomApplication().'] '.NotificationContenu::sujet($this->type, $this->data);
    }

    private function getBody(): string
    {
        return NotificationContenu::corps($this->type, $this->data);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotification: echec notification type '{$this->type}' pour user {$this->userId} - ".$exception->getMessage());
    }
}
