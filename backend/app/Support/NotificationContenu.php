<?php

namespace App\Support;

/**
 * Sujet et corps d'une notification, partagés par l'e-mail (job SendNotification)
 * et le feed in-app (persistance NotificationService). Source unique de vérité
 * pour que la cloche et l'e-mail disent exactement la même chose.
 *
 * @phpstan-type Donnees array<string,mixed>
 */
final class NotificationContenu
{
    /** Types émis par NotificationService (bascules de Paramètres › Notifications). */
    public const TYPES = [
        'etat_attend_signature', 'etat_valide', 'etat_rejete',
        'om_valide', 'om_rejete', 'lot_confirme', 'trop_percu_cree',
    ];

    /** @param  array<string,mixed>  $data */
    public static function sujet(string $type, array $data): string
    {
        return match ($type) {
            'om_valide' => "OM {$data['reference_semo']} validé — paiement préparable",
            'om_rejete' => "OM {$data['reference_semo']} rejeté",
            'lot_confirme' => "Lot {$data['tranche']} {$data['operateur']} confirmé",
            'etat_attend_signature' => "État de paiement {$data['numero']} — signature attendue",
            'etat_valide' => "État de paiement {$data['numero']} validé — fichiers générables",
            'etat_rejete' => "État de paiement {$data['numero']} rejeté",
            'trop_percu_cree' => "Trop-perçu sur OM {$data['reference_semo']} — régularisation à traiter",
            default => 'Notification ANaTT',
        };
    }

    /** @param  array<string,mixed>  $data */
    public static function corps(string $type, array $data): string
    {
        return match ($type) {
            'om_valide' => "L'ordre de mission {$data['reference_semo']} ({$data['beneficiaire']}, {$data['montant']} FCFA) a été validé. Le fichier de paiement peut être généré.",
            'om_rejete' => "L'ordre de mission {$data['reference_semo']} a été rejeté. Motif : {$data['motif']}",
            'lot_confirme' => "Le lot {$data['tranche']} / {$data['operateur']} (mission {$data['mission']}, {$data['montant']} FCFA) a été confirmé : versements soldés et horodatés.",
            'etat_attend_signature' => "L'état de paiement {$data['tranche']} {$data['numero']} de la mission « {$data['mission']} » ({$data['montant']} FCFA) attend votre intervention dans le circuit de validation.",
            'etat_valide' => "L'état de paiement {$data['tranche']} {$data['numero']} de la mission « {$data['mission']} » ({$data['montant']} FCFA) a été validé par le Directeur Général : les fichiers de paiement de cette tranche peuvent être générés.",
            'etat_rejete' => "L'état de paiement {$data['tranche']} {$data['numero']} de la mission « {$data['mission']} » a été rejeté. Motif : {$data['motif']}",
            'trop_percu_cree' => "L'amendement de l'OM {$data['reference_semo']} ({$data['beneficiaire']}) a produit un trop-perçu de {$data['montant']} FCFA. À récupérer par retenue sur salaire depuis l'écran Régularisations (le versement ne part pas en Mobile Money).",
            default => 'Notification du système ANaTT Missions.',
        };
    }
}
