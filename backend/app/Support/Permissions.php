<?php

namespace App\Support;

/**
 * Source de vérité du RBAC ANaTT : profils, clés de permission et matrice d'attribution.
 *
 * Le SUPER_ADMIN n'apparaît pas dans la matrice : il a accès implicite à TOUT
 * via Gate::before (cf. AuthServiceProvider).
 */
class Permissions
{
    // ── Profils ──
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN = 'ADMIN';
    public const GESTIONNAIRE_OM = 'GESTIONNAIRE_OM';
    public const AGENT_FINANCIER = 'AGENT_FINANCIER';
    public const DAF = 'DAF';
    public const DG = 'DG';
    public const CONTROLEUR = 'CONTROLEUR';
    public const LECTEUR = 'LECTEUR';

    public const PROFILS = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::GESTIONNAIRE_OM,
        self::AGENT_FINANCIER,
        self::DAF,
        self::DG,
        self::CONTROLEUR,
        self::LECTEUR,
    ];

    public const LIBELLES = [
        self::SUPER_ADMIN => 'Super administrateur',
        self::ADMIN => 'Administrateur',
        self::GESTIONNAIRE_OM => 'Gestionnaire OM',
        self::AGENT_FINANCIER => 'Agent financier',
        self::DAF => "Directeur de l'Administration et des Finances",
        self::DG => 'Directeur Général',
        self::CONTROLEUR => 'Contrôleur',
        self::LECTEUR => 'Lecteur',
    ];

    // ── Clés de permission ──
    public const DASHBOARD_CONSULTER = 'dashboard.consulter';
    public const PERSONNEL_CONSULTER = 'personnel.consulter';
    public const PERSONNEL_GERER = 'personnel.gerer';
    public const OM_CONSULTER = 'om.consulter';
    public const OM_RETRANSCRIRE = 'om.retranscrire';
    public const OM_VALIDER = 'om.valider';
    /** 1re signature du circuit double : IMPORTE -> VISA_DAF. */
    public const OM_VISER = 'om.viser';
    /** 2e et dernière signature : VISA_DAF -> VALIDE (rend l'OM payable). */
    public const OM_APPROUVER = 'om.approuver';
    public const PAIEMENTS_CONSULTER = 'paiements.consulter';
    public const PAIEMENTS_GENERER = 'paiements.generer';
    public const PAIEMENTS_CONFIRMER = 'paiements.confirmer';
    public const BAREMES_CONSULTER = 'baremes.consulter';
    public const BAREMES_MODIFIER = 'baremes.modifier';
    public const RAPPORTS_CONSULTER = 'rapports.consulter';
    public const AUDIT_CONSULTER = 'audit.consulter';
    public const UTILISATEURS_GERER = 'utilisateurs.gerer';
    public const SUPERADMINS_GERER = 'superadmins.gerer';
    public const PARAMETRES_GERER = 'parametres.gerer';

    /** Toutes les clés de permission existantes. */
    public const TOUTES = [
        self::DASHBOARD_CONSULTER,
        self::PERSONNEL_CONSULTER, self::PERSONNEL_GERER,
        self::OM_CONSULTER, self::OM_RETRANSCRIRE, self::OM_VALIDER,
        self::OM_VISER, self::OM_APPROUVER,
        self::PAIEMENTS_CONSULTER, self::PAIEMENTS_GENERER, self::PAIEMENTS_CONFIRMER,
        self::BAREMES_CONSULTER, self::BAREMES_MODIFIER,
        self::RAPPORTS_CONSULTER,
        self::AUDIT_CONSULTER,
        self::UTILISATEURS_GERER,
        self::SUPERADMINS_GERER,
        self::PARAMETRES_GERER,
    ];

    /**
     * Matrice d'attribution par profil (hors SUPER_ADMIN, qui a tout via Gate::before).
     * superadmins.gerer et parametres.gerer ne sont attribués à personne :
     * ils restent exclusifs au SUPER_ADMIN.
     */
    public const MATRICE = [
        self::ADMIN => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER, self::PERSONNEL_GERER,
            self::OM_CONSULTER,
            self::PAIEMENTS_CONSULTER,
            self::BAREMES_CONSULTER, self::BAREMES_MODIFIER,
            self::RAPPORTS_CONSULTER,
            self::AUDIT_CONSULTER,
            self::UTILISATEURS_GERER,
        ],
        self::GESTIONNAIRE_OM => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER, self::PERSONNEL_GERER,
            self::OM_CONSULTER, self::OM_RETRANSCRIRE,
            self::PAIEMENTS_CONSULTER,
            self::BAREMES_CONSULTER,
            self::RAPPORTS_CONSULTER,
        ],
        self::AGENT_FINANCIER => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER,
            self::OM_CONSULTER,
            self::PAIEMENTS_CONSULTER, self::PAIEMENTS_GENERER, self::PAIEMENTS_CONFIRMER,
            self::BAREMES_CONSULTER,
            self::RAPPORTS_CONSULTER,
        ],
        self::DAF => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER,
            self::OM_CONSULTER, self::OM_VISER,
            self::PAIEMENTS_CONSULTER,
            self::BAREMES_CONSULTER,
            self::RAPPORTS_CONSULTER,
            self::AUDIT_CONSULTER,
        ],
        self::DG => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER,
            self::OM_CONSULTER, self::OM_APPROUVER,
            self::PAIEMENTS_CONSULTER,
            self::BAREMES_CONSULTER,
            self::RAPPORTS_CONSULTER,
            self::AUDIT_CONSULTER,
        ],
        self::CONTROLEUR => [
            self::DASHBOARD_CONSULTER,
            self::PERSONNEL_CONSULTER,
            self::OM_CONSULTER, self::OM_VALIDER,
            self::PAIEMENTS_CONSULTER,
            self::BAREMES_CONSULTER,
            self::RAPPORTS_CONSULTER,
            self::AUDIT_CONSULTER,
        ],
        self::LECTEUR => [
            self::DASHBOARD_CONSULTER,
            self::OM_CONSULTER,
            self::RAPPORTS_CONSULTER,
        ],
    ];

    /**
     * Permissions effectives d'un profil (SUPER_ADMIN -> toutes).
     * La matrice est lue depuis config/profils.php (source de vérité éditable),
     * avec repli sur la constante MATRICE si la config est indisponible.
     */
    public static function pour(string $profil): array
    {
        if ($profil === self::SUPER_ADMIN) {
            return self::TOUTES;
        }

        $matrice = config('profils.matrice', self::MATRICE);

        return $matrice[$profil] ?? self::MATRICE[$profil] ?? [];
    }
}
