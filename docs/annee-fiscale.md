# Année fiscale / exercice — paramétrage flexible

> Avant : l'année fiscale était un champ **figé** dans l'écran Paramètres (liste
> d'options codée en dur `2024`/`2025`, défaut `2026`), non validée et jamais
> exploitée. Désormais elle est **paramétrable et déterminable**.

## Ce qui change

1. **Sélecteur d'année dynamique** (`SettingsView`) : la liste est générée
   (année suivante → 5 ans en arrière), plus aucune valeur en dur ; défaut =
   année civile courante.
2. **Mois de début d'exercice** (`fiscalYearStartMonth`, 1-12, défaut 1) : permet
   un exercice qui ne coïncide pas avec l'année civile (ex. juillet → juin).
3. **Détermination** : `Parametres::anneeFiscalePour($date)` renvoie l'exercice
   d'une date donnée ; l'exercice est **étiqueté par son année de début**.
4. **Validation** à l'écriture (année 2000-2100 ; mois 1-12).
5. **Lecture publique** de l'exercice courant pour toute l'UI.

## Backend — `App\Support\Parametres`

| Méthode | Rôle |
|---|---|
| `moisDebutExercice(): int` | mois de début (1-12), défaut 1 |
| `anneeFiscale(): int` | année configurée si valide, sinon exercice courant **déterminé** |
| `anneeFiscalePour(Carbon $date): int` | exercice d'une date (`month >= début ? year : year-1`) |
| `libelleAnneeFiscale(?int): string` | « 2026 » (année civile) ou « 2026-2027 » (à cheval) |
| `invalidateAnneeFiscale()` | purge le cache (année + mois), appelée à l'écriture |

Valeurs mises en cache 60 s (comme `partAvance`), invalidées quand `fiscalYear`
ou `fiscalYearStartMonth` change. Lecture tolérante : une année stockée en chaîne
numérique (« 2028 ») est acceptée et normalisée en entier.

## API

| Méthode | Route | Accès | Effet |
|---|---|---|---|
| `PUT` | `/api/admin/settings` | SUPER_ADMIN (`parametres.gerer`) | écrit `fiscalYear` / `fiscalYearStartMonth` (validés) |
| `GET` | `/api/admin/parametres/annee-fiscale` | tout authentifié | `{ annee, mois_debut, libelle }` |

## Convention de détermination

Pour un mois de début `M` :
- une date dont le mois est `≥ M` appartient à l'exercice **de son année civile** ;
- une date dont le mois est `< M` appartient à l'exercice **de l'année précédente**.

Exemple `M = 7` (juillet) : `2026-07-01` → exercice **2026** (« 2026-2027 ») ;
`2026-06-30` → exercice **2025** (« 2025-2026 »). Avec `M = 1` (défaut), exercice =
année civile, libellé « 2026 ».
