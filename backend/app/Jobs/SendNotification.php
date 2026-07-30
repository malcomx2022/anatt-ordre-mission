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
            'om_valide' => "OM {$this->data['reference_semo']} validé — paiement préparable",
            'om_rejete' => "OM {$this->data['reference_semo']} rejeté",
            'lot_confirme' => "Lot {$this->data['tranche']} {$this->data['operateur']} confirmé",
            // Circuit de l'état de paiement.
            'etat_attente' => "État de paiement {$this->data['numero']} — votre intervention est attendue",
            'etat_rejete' => "État de paiement {$this->data['numero']} rejeté",
            'etat_valide' => "État de paiement {$this->data['numero']} validé — fichiers générables",
            default => 'Notification ANaTT',
        };
    }

    private function getBody(): string
    {
        return match ($this->type) {
            'om_valide' => "L'ordre de mission {$this->data['reference_semo']} ({$this->data['beneficiaire']}, {$this->data['montant']} FCFA) a été validé. Le fichier de paiement peut être généré.",
            'om_rejete' => "L'ordre de mission {$this->data['reference_semo']} a été rejeté. Motif : {$this->data['motif']}",
            'lot_confirme' => "Le lot {$this->data['tranche']} / {$this->data['operateur']} (mission {$this->data['mission']}, {$this->data['montant']} FCFA) a été confirmé : versements soldés et horodatés.",
            'etat_attente' => "L'état de paiement {$this->data['numero']} (mission « {$this->data['mission']} », "
                ."{$this->data['nb_beneficiaires']} bénéficiaire(s), {$this->data['montant']} FCFA) est au statut "
                ."{$this->data['statut']} et attend votre intervention.",
            'etat_rejete' => "L'état de paiement {$this->data['numero']} (mission « {$this->data['mission']} ») a été rejeté. "
                ."Motif : {$this->data['motif']}. Le récapitulatif peut être refait.",
            'etat_valide' => "L'état de paiement {$this->data['numero']} (mission « {$this->data['mission']} », "
                ."{$this->data['nb_beneficiaires']} bénéficiaire(s), {$this->data['montant']} FCFA) est validé par le "
                .'Directeur Général : les fichiers de paiement peuvent être générés.',
            default => 'Notification du système ANaTT Missions.',
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotification: echec notification type '{$this->type}' pour user {$this->userId} - " . $exception->getMessage());
    }
}
