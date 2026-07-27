<?php

namespace App\Support;

use App\Models\OrdreMission;
use App\Models\User;

/**
 * Règles du circuit de validation des ordres de mission.
 *
 * Circuit complet (flag workflow.double_validation actif) — trois étapes :
 *
 *   IMPORTE ──vérifier (CONTROLEUR, om.valider)──▶ VERIFIE
 *           ──viser    (DAF,        om.viser)   ──▶ VISA_DAF
 *           ──approuver(DG,         om.approuver)▶ VALIDE   ← verrouillage final
 *
 * Circuit historique (flag inactif) — une seule étape :
 *
 *   IMPORTE ──valider (CONTROLEUR, om.valider)──▶ VALIDE
 *
 * Dans les deux cas VALIDE a le même sens : l'OM est verrouillé et payable.
 * Versement::scopeEligiblesLot() n'est donc jamais à modifier.
 */
class Workflow
{
    /** Étape 1 : conformité vérifiée par le CONTROLEUR. */
    public const STATUT_VERIFIE = 'VERIFIE';

    /** Étape 2 : visa du DAF obtenu, validation finale du DG en attente. */
    public const STATUT_VISA_DAF = 'VISA_DAF';

    /** Étape 3 : validation finale du DG — l'OM est verrouillé. */
    public const STATUT_VALIDE = 'VALIDE';

    /** Identifiants d'étape (utilisés pour le scellé et l'audit). */
    public const ETAPE_VERIFICATION = 'CONTROLE';

    public const ETAPE_VISA_DAF = 'DAF';

    public const ETAPE_VALIDATION_DG = 'DG';

    /** Le circuit complet (CONTROLEUR → DAF → DG) est-il actif ? */
    public static function doubleValidationActive(): bool
    {
        return (bool) config('workflow.double_validation', false);
    }

    /** La séparation des tâches entre signataires est-elle exigée ? */
    public static function separationDesTaches(): bool
    {
        return (bool) config('workflow.separation_des_taches', true);
    }

    /**
     * Statut atteint par l'action du CONTROLEUR.
     *
     * Circuit complet : première vérification, l'OM attend ensuite le DAF.
     * Circuit historique : le contrôleur valide directement (comportement d'origine).
     */
    public static function statutApresControle(): string
    {
        return self::doubleValidationActive() ? self::STATUT_VERIFIE : self::STATUT_VALIDE;
    }

    /** Statut requis pour que le DAF puisse apposer son visa. */
    public static function statutAvantVisaDaf(): string
    {
        return self::STATUT_VERIFIE;
    }

    /** Statut requis pour que le DG puisse valider définitivement. */
    public static function statutAvantValidationDg(): string
    {
        return self::STATUT_VISA_DAF;
    }

    /**
     * Statuts depuis lesquels un rejet reste possible.
     * Chacun des trois acteurs doit pouvoir rejeter à son tour.
     *
     * @return array<int,string>
     */
    public static function statutsRejetables(): array
    {
        return self::doubleValidationActive()
            ? ['IMPORTE', self::STATUT_VERIFIE, self::STATUT_VISA_DAF]
            : ['IMPORTE'];
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
