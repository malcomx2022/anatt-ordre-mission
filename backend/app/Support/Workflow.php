<?php

namespace App\Support;

use App\Models\OrdreMission;
use App\Models\User;

/**
 * Règles communes aux deux circuits de validation.
 *
 * — Circuit par OM (repli des missions SANS état) : une seule étape
 *   IMPORTE ──valider (CONTROLEUR, om.valider)──▶ VALIDE (verrouillé, payable).
 * — Circuit par ÉTAT (canonique, trois signatures contrôle → DAF → DG) :
 *   piloté par EtatPaiementService, hors de ce support.
 *
 * Ce support porte le vocabulaire partagé (statut VALIDE, identifiants d'étape
 * pour le scellé/audit), la séparation des tâches et le scellé HMAC.
 * Cf. docs/circuits-validation.md.
 */
class Workflow
{
    /** Statut final : l'OM est verrouillé et payable. */
    public const STATUT_VALIDE = 'VALIDE';

    /** Identifiants d'étape (utilisés pour le scellé et l'audit). */
    public const ETAPE_VERIFICATION = 'CONTROLE';

    public const ETAPE_VISA_DAF = 'DAF';

    public const ETAPE_VALIDATION_DG = 'DG';

    /** La séparation des tâches entre signataires est-elle exigée ? */
    public static function separationDesTaches(): bool
    {
        return (bool) config('workflow.separation_des_taches', true);
    }

    /**
     * Statuts depuis lesquels un rejet d'OM reste possible : avant validation.
     *
     * @return array<int,string>
     */
    public static function statutsRejetables(): array
    {
        return ['IMPORTE'];
    }

    /**
     * Scellé d'intégrité d'une signature.
     *
     * HMAC-SHA256 sur la charge financière signée + l'identité du signataire,
     * clé APP_KEY. Permet de détecter une altération des montants postérieure à
     * la signature et d'imprimer un code de vérification sur le document.
     *
     * Ce n'est PAS une signature électronique à valeur légale (aucune PKI,
     * aucun certificat) : c'est un scellé interne, arbitrage retenu avec le client.
     */
    public static function sceller(OrdreMission $ordre, User $signataire, string $etape, string $horodatage): string
    {
        $charge = implode('|', [
            $etape,
            $ordre->reference_semo,
            (int) $ordre->agent_id,
            (int) $ordre->nb_jours,
            (int) $ordre->montant_total,
            (int) $ordre->avance,
            (int) $ordre->reliquat,
            (int) $signataire->id,
            $horodatage,
        ]);

        return hash_hmac('sha256', $charge, (string) config('app.key'));
    }

    /**
     * Code de vérification imprimé sous la signature (12 premiers caractères du
     * scellé, en majuscules et par groupes de 4 pour la lisibilité à l'œil).
     */
    public static function codeVerification(?string $hash): ?string
    {
        if (empty($hash)) {
            return null;
        }

        return implode('-', str_split(strtoupper(substr($hash, 0, 12)), 4));
    }
}
