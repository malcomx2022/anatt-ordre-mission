<?php

/*
|--------------------------------------------------------------------------
| Circuit de validation des ordres de mission
|--------------------------------------------------------------------------
| Le circuit à trois signatures (contrôle → visa DAF → validation DG) se joue
| sur l'ÉTAT de paiement de la mission (App\Services\EtatPaiementService), pas
| OM par OM. La validation OM par OM est une voie de repli en UNE étape
| (IMPORTE → VALIDE) réservée aux missions sans état (reprise de données).
| Cf. docs/circuits-validation.md.
*/

return [

    /*
    | Interdire au même utilisateur de porter deux signatures d'un même état.
    | Une double signature par une seule personne vide le contrôle de sa substance ;
    | la règle s'applique aussi au SUPER_ADMIN.
    */
    'separation_des_taches' => env('WORKFLOW_SEPARATION_TACHES', true),

];
