<?php

namespace App\Jobs;

use App\Models\User;
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

        $subject = $this->getSubject();
        $body = $this->getBody();

        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email)
                    ->subject($subject);
            });

            Log::info("SendNotification: email '{$this->type}' envoye a {$user->email}");
        } catch (\Exception $e) {
            Log::error("SendNotification: echec envoi email a {$user->email} - " . $e->getMessage());
            throw $e;
        }
    }

    private function getSubject(): string
    {
        return match ($this->type) {
            'mission_soumise' => "Nouvel ordre de mission {$this->data['numero']} - En attente de validation",
            'mission_validee' => "Ordre de mission {$this->data['numero']} valide",
            'mission_approuvee' => "Ordre de mission {$this->data['numero']} approuve",
            'mission_rejetee' => "Ordre de mission {$this->data['numero']} rejete",
            'paiement_effectue' => "Paiement de {$this->data['montant']} FCFA effectue",
            'paiement_echec' => "Echec paiement - Ordre {$this->data['numero']}",
            default => "Notification ANaTT",
        };
    }

    private function getBody(): string
    {
        return match ($this->type) {
            'mission_soumise' => "Un nouvel ordre de mission ({$this->data['numero']}) a ete soumis et attend votre validation.",
            'mission_validee' => "L'ordre de mission {$this->data['numero']} a ete valide par le chef de service.",
            'mission_approuvee' => "L'ordre de mission {$this->data['numero']} a ete approuve par la Direction Generale. Le paiement peut etre lance.",
            'mission_rejetee' => "L'ordre de mission {$this->data['numero']} a ete rejete. Motif: {$this->data['commentaire']}",
            'paiement_effectue' => "Le paiement de {$this->data['montant']} FCFA pour l'ordre {$this->data['numero']} a ete effectue avec succes via {$this->data['operateur']}.",
            'paiement_echec' => "Le paiement pour l'ordre {$this->data['numero']} a echoue. Veuillez verifier et relancer.",
            default => "Notification du systeme ANaTT Missions.",
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotification: echec notification type '{$this->type}' pour user {$this->userId} - " . $exception->getMessage());
    }
}
