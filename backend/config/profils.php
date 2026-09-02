<?php

/*
|--------------------------------------------------------------------------
| Profils, permissions et matrice d'accès ANaTT (RBAC natif, sans package)
|--------------------------------------------------------------------------
| Source de vérité de la matrice d'attribution (section B du cahier des
| charges). Lue par App\Support\Permissions::pour() et User::hasPermission().
|
| Le SUPER_ADMIN n'apparaît PAS dans la matrice : il a accès implicite à TOUT
| via Gate::before (cf. App\Providers\AuthServiceProvider).
| superadmins.gerer et parametres.gerer ne sont attribués à aucun profil :
| ils restent exclusifs au SUPER_ADMIN.
*/

return [

    // ── Profils ──
    'profils' => [
        'SUPER_ADMIN',
        'ADMIN',
        'GESTIONNAIRE_OM',
        'AGENT_FINANCIER',
        'DAF',
        'DG',
        'CONTROLEUR',
        'LECTEUR',
    ],

    'libelles' => [
        'SUPER_ADMIN' => 'Super administrateur',
        'ADMIN' => 'Administrateur',
        'GESTIONNAIRE_OM' => 'Gestionnaire OM',
        'AGENT_FINANCIER' => 'Agent financier',
        'DAF' => "Directeur de l'Administration et des Finances",
        'DG' => 'Directeur Général',
        'CONTROLEUR' => 'Contrôleur',
        'LECTEUR' => 'Lecteur',
    ],

    // ── Toutes les clés de permission existantes ──
    'permissions' => [
        'dashboard.consulter',
        'personnel.consulter', 'personnel.gerer',
        'om.consulter', 'om.retranscrire', 'om.valider', 'om.numeroter',
        'om.viser', 'om.approuver', 'om.rejeter',
        'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
        'baremes.consulter', 'baremes.modifier',
        'rapports.consulter',
        'audit.consulter',
        'utilisateurs.gerer',
        'superadmins.gerer',
        'parametres.gerer',
        'roles.gerer',
    ],

    // ── Matrice d'attribution par profil (hors SUPER_ADMIN) ──
    'matrice' => [
        'ADMIN' => [
            'dashboard.consulter',
            'personnel.consulter', 'personnel.gerer',
            'om.consulter',
            'paiements.consulter',
            'baremes.consulter', 'baremes.modifier',
            'rapports.consulter',
            'audit.consulter',
            'utilisateurs.gerer',
        ],
        'GESTIONNAIRE_OM' => [
            'dashboard.consulter',
            'personnel.consulter', 'personnel.gerer',
            'om.consulter', 'om.retranscrire',
            'paiements.consulter',
            'baremes.consulter',
            'rapports.consulter',
        ],
        'AGENT_FINANCIER' => [
            'dashboard.consulter',
            'personnel.consulter',
            'om.consulter',
            'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
            'baremes.consulter',
            'rapports.consulter',
        ],
        // 1re signature du circuit double : « Vu » du DAF.
        'DAF' => [
            'dashboard.consulter',
            'personnel.consulter',
            'om.consulter', 'om.numeroter', 'om.viser', 'om.rejeter',
            'paiements.consulter',
            'baremes.consulter',
            'rapports.consulter',
            'audit.consulter',
        ],
        // 2e et dernière signature : le DG arrête l'état, l'OM devient payable.
        // Ni le DAF ni le DG ne reçoivent paiements.generer/confirmer : ils
        // autorisent, l'AGENT_FINANCIER exécute (séparation ordonnateur/payeur).
        'DG' => [
            'dashboard.consulter',
            'personnel.consulter',
            'om.consulter', 'om.numeroter', 'om.approuver', 'om.rejeter',
            'paiements.consulter',
            'baremes.consulter',
            'rapports.consulter',
            'audit.consulter',
        ],
        'CONTROLEUR' => [
            'dashboard.consulter',
            'personnel.consulter',
            'om.consulter', 'om.valider', 'om.numeroter', 'om.rejeter',
            'paiements.consulter',
            'baremes.consulter',
            'rapports.consulter',
            'audit.consulter',
        ],
        'LECTEUR' => [
            'dashboard.consulter',
            'om.consulter',
            'rapports.consulter',
        ],
    ],

    /*
    | Comptes nominatifs du circuit de validation (CONTROLEUR -> DAF -> DG),
    | créés par ComptesCircuitSeeder au déploiement.
    |
    | Aucun mot de passe n'est stocké ici : le seeder en génère un aléatoire et
    | pose must_change_password, l'intéressé le change à sa première connexion.
    | Un compte laissé sans email n'est pas créé.
    |
    | La `fonction` est le libellé imprimé sous la signature sur les documents ;
    | elle est figée en instantané au moment où la personne signe.
    */
    'circuit' => [
        'controleur' => [
            'name' => env('CONTROLEUR_NAME'),
            'email' => env('CONTROLEUR_EMAIL'),
            'fonction' => env('CONTROLEUR_FONCTION', 'Contrôleur'),
        ],
        'daf' => [
            'name' => env('DAF_NAME'),
            'email' => env('DAF_EMAIL'),
            'fonction' => env('DAF_FONCTION', "Directeur de l'Administration et des Finances"),
        ],
        'dg' => [
            'name' => env('DG_NAME'),
            'email' => env('DG_EMAIL'),
            'fonction' => env('DG_FONCTION', 'Directeur Général'),
        ],
    ],

    // Compte SUPER_ADMIN initial (créé par le seeder à l'installation).
    // SÉCURITÉ : aucun mot de passe de repli. En production, SUPERADMIN_PASSWORD
    // DOIT être défini dans le .env ; à défaut le seeder lève une exception.
    // En local/testing, le seeder retombe sur un mot de passe de développement.
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Administrateur'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@anatt.bj'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],
];
