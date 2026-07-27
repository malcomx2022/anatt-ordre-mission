<?php

/*
|--------------------------------------------------------------------------
| Circuit de validation des ordres de mission
|--------------------------------------------------------------------------
| Pilote le passage entre le circuit SIMPLE (historique) et le circuit
| DOUBLE (visa DAF puis approbation DG, conforme à l'état de paiement ANaTT).
|
|   simple : IMPORTE ──valider (om.valider)──▶ VALIDE
|   double : IMPORTE ──viser (om.viser)──▶ VISA_DAF ──approuver (om.approuver)──▶ VALIDE
|
| Dans les deux cas VALIDE garde le même sens : l'OM est payable. Le périmètre
| d'éligibilité des versements (Versement::scopeEligiblesLot) est donc inchangé.
*/

return [

    /*
    | Circuit double actif ?
    |
    | Valeur par défaut : FALSE — le comportement reste strictement celui d'avant
    | l'introduction du circuit double. La bascule est une décision d'exploitation
    | explicite (variable d'environnement), à prendre une fois les comptes DAF et DG
    | créés et leurs signatures téléversées.
    |
    | ATTENTION avant de brancher le toggle « doubleValidation » de l'écran
    | Paramètres sur cette valeur : l'écran SettingsView initialise ce toggle à TRUE
    | par défaut et le persiste dans storage/app/settings.json. Le brancher sans
    | précaution basculerait le circuit en production sans action délibérée. Il faut
    | d'abord forcer la clé à false dans settings.json.
    */
    'double_validation' => env('WORKFLOW_DOUBLE_VALIDATION', false),

    /*
    | Interdire au même utilisateur de porter les deux signatures d'un même OM.
    | Une double signature par une seule personne vide le contrôle de sa substance ;
    | la règle s'applique aussi au SUPER_ADMIN.
    */
    'separation_des_taches' => env('WORKFLOW_SEPARATION_TACHES', true),

];
