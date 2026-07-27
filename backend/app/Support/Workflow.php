<?php

namespace App\Support;

use App\Models\OrdreMission;
use App\Models\User;

/**
 * Règles du circuit de validation des ordres de mission.
 *
 * Centralise la lecture du mode (simple/double), les transitions autorisées et le
 * scellé d'intégrité apposé à chaque signature.
 */
class Workflow
{
    /** Statut intermédiaire : visa DAF obtenu, approbation DG en attente. */
    public const STATUT_VISA_DAF = 'VISA_DAF';

    /** Le circuit double (DAF puis DG) est-il actif ? */
    public static function doubleValidationActive(): bool
    {
        return (bool) config('workflow.double_validation', false);
    }

    /** La séparation des tâches entre les deux signataires est-elle exigée ? */
    public static function separationDesTaches(): bool
    {
        return (bool) config('workflow.separation_des_taches', true);
    }

    /**
     * Statut requis en entrée de l'étape finale (celle qui rend l'OM payable).
     * Circuit simple : on approuve un OM tout juste retranscrit.
     * Circuit double : on n'approuve qu'un OM déjà visé par le DAF.
     */
    public static function statutAvantApprobation(): string
    {
        return self::doubleValidationActive() ? self::STATUT_VISA_DAF : 'IMPORTE';
    }

    /**
     * Statuts depuis lesquels un rejet reste possible.
     * Le DG doit pouvoir rejeter un OM que le DAF a déjà visé.
     */
    public static function statutsRejetables(): array
    {
        return self::doubleValidationActive()
            ? ['IMPORTE', self::STATUT_VISA_DAF]
            : ['IMPORTE'];
    }

    /**
     * Scellé d'intégrité d'une signature.
     *
     * HMAC-SHA256 sur la charge financière signée + l'identité du signataire, clé
     * APP_KEY. Permet de détecter une altération des montants postérieure à la
     * signature et d'imprimer un code de vérification sur le document.
     *
     * Ce n'est PAS une signature électronique à valeur légale (aucune PKI, aucun
     * certificat) : c'est un scellé interne, arbitrage retenu avec le client.
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
