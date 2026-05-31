<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lecture brute du fichier personnel (CSV/XLSX).
 * La validation et l'insertion (transactionnelles) sont pilotées par AgentService::importer().
 * En-têtes attendus : nom, prenoms, matricule, ifu, grade, indice, salaire_brut, categorie, telephone, operateur.
 */
class AgentsImport implements WithHeadingRow
{
    use Importable;
}
