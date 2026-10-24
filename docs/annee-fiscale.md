# Année fiscale / exercice — paramétrage flexible

> Avant : l'année fiscale était un champ **figé** dans l'écran Paramètres (liste
> d'options codée en dur `2024`/`2025`, défaut `2026`), non validée et jamais
> exploitée. Désormais elle est **paramétrable et déterminable**.
>
> *Relu le 24/10/2026 contre le code* (`Parametres`, `SettingsController`, `SettingsView`,
> `utils/exercice.ts`) : conforme. Précisés : la liste d'années garde une année enregistrée hors
> plage, et le serveur détermine l'exercice en **UTC** (voir *Fuseau horaire*).

## Ce qui change

1. **Sélecteur d'année dynamique** (`SettingsView`) : la liste est générée
   (année suivante → 5 ans en arrière), plus aucune valeur en dur ; une année enregistrée hors de
   cette plage y est ajoutée, pour ne jamais être remplacée en silence à l'enregistrement. Sans année
   enregistrée, le défaut est l'**exercice en cours déterminé** depuis le mois de
   début (`utils/exercice.ts` → `exerciceDe()`, miroir de `anneeFiscalePour()`). Il suit
   le mois de début tant que l'année n'est ni enregistrée ni choisie à la main.
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

## Fuseau horaire

Le serveur détermine l'exercice courant avec l'horloge de l'application, réglée en **UTC**
(`config/app.php`, `timezone`). L'écran Paramètres le détermine avec l'heure du navigateur,
c'est-à-dire l'heure du Bénin (UTC+1). Le premier jour d'un exercice, entre 0 h et 1 h (heure
du Bénin), le serveur renvoie donc encore l'exercice précédent alors que l'écran affiche déjà le
nouveau. Sans effet aujourd'hui, puisque rien ne consomme l'exercice (§ suivant) ; à corriger
avant de brancher un filtre par exercice, en même temps que le choix du fuseau de l'application
(qui porte aussi les horodatages de paiement).

## Où l'exercice est utilisé (état au 24/10/2026)

| Consommateur | Utilisation |
|---|---|
| `views/admin/SettingsView.vue` | Saisie de l'année et du mois de début ; aperçu du libellé (« 2026 » / « 2026-2027 ») ; défaut = exercice en cours déterminé (`exerciceDe`) |
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
| Écran | `frontend/src/views/admin/SettingsView.vue`, `frontend/src/utils/exercice.ts` (`exerciceDe`, détermination côté écran) |
| Tests | `backend/tests/Feature/AnneeFiscaleTest.php` (8 tests : année civile, exercice à cheval, détermination sans réglage, année configurée, bornes, mois invalide, chaîne numérique, lecture publique) ; `RoutesPermissionsTest` (route publique déclarée) ; `frontend/src/__tests__/settings-exercice.spec.ts` (`exerciceDe`, défaut de l'écran avec un début en juillet, année enregistrée jamais écrasée) |

## Exploitation

- Les deux clés vivent dans `storage/app/settings.json`, comme `partAvance` : elles **ne
  sont pas** dans les sauvegardes de la base (`db:backup`) et une restauration ne les modifie
  pas. Conserver `storage/` lors d'une mise à jour (`docs/runbook-mise-a-jour-cron.md`).
- Sans réglage, l'exercice est **déterminé** depuis la date du jour : il bascule seul au
  1er janvier (ou au mois de début configuré). Mais dès que l'écran Paramètres est
  enregistré, il envoie l'année sélectionnée : l'année devient **explicite** et reste figée
  jusqu'à sa prochaine modification (l'API refuse une valeur vide). L'écran propose alors
  l'exercice en cours, déterminé comme le serveur, et non l'année civile.
  *Corrigé le 23/10/2026* : avant, l'écran proposait l'année civile. Avec un début en
  juillet, en mars 2027, le premier enregistrement figeait « 2027-2028 » au lieu de
  l'exercice en cours « 2026-2027 ». Penser à la changer à
  chaque nouvel exercice — sans conséquence aujourd'hui, puisque rien ne la consomme encore.
