<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lecture brute du fichier personnel (CSV/XLSX).
 * La validation et l'insertion (transactionnelles) sont pilotées par AgentService::importer().
 * En-têtes attendus : nom, prenoms, matricule, ifu, grade, indice, salaire_brut, categorie, telephone, operateur.
 * Colonne `categorie` : INDICE_SUP_800 | INDICE_400_799 | INDICE_INF_400 — vide, elle se
 * déduit de l'indice (ou du salaire brut). Les anciens codes ORDRE_1/2/3 restent acceptés
 * et sont traduits à la lecture (cf. AgentService::normaliserLigne).
 */
class AgentsImport implements WithHeadingRow
{
    use Importable;
}
