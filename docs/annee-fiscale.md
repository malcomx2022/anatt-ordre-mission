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
5. **Lecture publique** de l'exercice courant (route dédiée, tout profil authentifié),
   prête à être consommée par l'UI — voir § *Où l'exercice est utilisé* pour l'état réel.

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
| `PUT` | `/api/admin/settings` | SUPER_ADMIN (`parametres.gerer`) | écrit `fiscalYear` / `fiscalYearStartMonth` (validés par `SettingsController::update`, 422 sinon ; chaîne numérique acceptée et stockée en entier) |
| `GET` | `/api/admin/parametres/annee-fiscale` | tout authentifié | `{ annee, mois_debut, libelle }` |

## Convention de détermination

Pour un mois de début `M` :
- une date dont le mois est `≥ M` appartient à l'exercice **de son année civile** ;
- une date dont le mois est `< M` appartient à l'exercice **de l'année précédente**.

Exemple `M = 7` (juillet) : `2026-07-01` → exercice **2026** (« 2026-2027 ») ;
`2026-06-30` → exercice **2025** (« 2025-2026 »). Avec `M = 1` (défaut), exercice =
année civile, libellé « 2026 ».

## Où l'exercice est utilisé (état au 22/10/2026)

| Consommateur | Utilisation |
|---|---|
| `views/admin/SettingsView.vue` | Saisie de l'année et du mois de début ; aperçu du libellé (« 2026 » / « 2026-2027 ») |
| `GET /api/admin/parametres/annee-fiscale` (`SettingsController::anneeFiscale`) | Exposition de l'exercice courant |

À ce jour, **aucun autre écran ni traitement ne lit l'exercice** : la route publique n'est
appelée par aucun service du frontend, et les rapports, exports, états de paiement et PDF
filtrent par **dates** (mois, période `date_debut` / `date_fin`), pas par exercice. Le
réglage est donc stocké, validé et exposé, mais n'a encore **aucun effet** sur les montants,
les filtres ou les documents. Le brancher sur un écran (par exemple un filtre « exercice »
des rapports, via `Parametres::anneeFiscalePour()`) est une évolution, pas un correctif.

## Fichiers et tests

| Élément | Emplacement |
|---|---|
| Lecture, cache, détermination | `backend/app/Support/Parametres.php` |
| Validation et route publique | `backend/app/Http/Controllers/Api/SettingsController.php`, `backend/routes/api.php` |
| Écran | `frontend/src/views/admin/SettingsView.vue` |
| Tests | `backend/tests/Feature/AnneeFiscaleTest.php` (année civile, exercice à cheval, détermination sans réglage, année configurée, bornes, mois invalide, chaîne numérique, lecture publique) ; `RoutesPermissionsTest` (route publique déclarée) |

## Exploitation

- Les deux clés vivent dans `storage/app/settings.json`, comme `partAvance` : elles **ne
  sont pas** dans les sauvegardes de la base (`db:backup`) et une restauration ne les modifie
  pas. Conserver `storage/` lors d'une mise à jour (`docs/runbook-mise-a-jour-cron.md`).
- Sans réglage, l'exercice est **déterminé** depuis la date du jour : il bascule seul au
  1er janvier (ou au mois de début configuré). Mais dès que l'écran Paramètres est
  enregistré, il envoie l'année sélectionnée : l'année devient **explicite** et reste figée
  jusqu'à sa prochaine modification (l'API refuse une valeur vide). Penser à la changer à
  chaque nouvel exercice — sans conséquence aujourd'hui, puisque rien ne la consomme encore.
