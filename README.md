# ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money

Application de suivi des **ordres de mission émis par SEMO** et de **génération des fichiers de paiement** Mobile Money (MTN / Moov) pour l'ANaTT (Agence Nationale des Transports Terrestres, Bénin).

> **Règle d'or** — l'application **ne crée pas** les OM (SEMO les émet) et **ne réalise pas** les paiements (faits sur les plateformes MTN/Moov). Elle **retranscrit** les OM et **génère** les fichiers de paiement à déposer manuellement. Aucune API SEMO, aucune API opérateur, aucun réimport de fichier résultat.

---

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel 12 · **PHP 8.4+** · Sanctum · MySQL 8 · (Redis facultatif) |
| Frontend | Vue 3 (Composition API, TypeScript) · Vite · Tailwind · Pinia |
| Tests | Pest / PHPUnit (565 tests, 67 fichiers) · Vitest (113 tests) · Playwright (parcours critique e2e) |

---

## Démarrage rapide (Windows)

Prérequis : **WAMP** (MySQL démarré), **PHP 8.4+**, **Node 18+**, **Composer**.

> PHP 8.4 est un **minimum** : `composer.lock` épingle Symfony 8 et `spatie/laravel-activitylog`,
> qui l'exigent. Sous 8.3, Composer refuse de charger l'autoloader. Même contrainte en production
> (cf. [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) §4).

```powershell
cd C:\wamp64\www\anatt-ordre-mission

# Première fois (installe vendor + node_modules, crée le .env/clé, seed la démo)
.\start-dev.ps1 -Install -Fresh

# Les fois suivantes
.\start-dev.ps1

# Pour tout arrêter
.\stop-dev.ps1
```

Le script lance **3 fenêtres** : API (`http://localhost:8000`), worker de file, frontend (`http://localhost:5173`).
Si l'exécution est bloquée : `powershell -ExecutionPolicy Bypass -File .\start-dev.ps1`.

Application : **http://localhost:5173**

### Comptes de démonstration (mot de passe : `password`)

| Email | Profil | Peut |
|---|---|---|
| `superadmin@anatt.bj` | SUPER_ADMIN | tout (dont super admins & paramètres) |
| `admin@anatt.bj` | ADMIN | utilisateurs, référentiels, barème, audit |
| `gestion@anatt.bj` | GESTIONNAIRE_OM | agents, retranscription des missions/OM |
| `finance@anatt.bj` | AGENT_FINANCIER | génération & confirmation des paiements |
| `controle@anatt.bj` | CONTROLEUR | étape 1 du circuit : contrôle de conformité de l'état |
| `daf@anatt.bj` | DAF | étape 2 du circuit : visa « Vu » sur l'état de paiement |
| `dg@anatt.bj` | DG | étape 3 du circuit : validation finale de l'état (ouvre le paiement) |
| `lecteur@anatt.bj` | LECTEUR | lecture seule |

---

## Démarrage manuel (sans script)

```powershell
# 1) Backend
cd backend
copy .env.example .env          # si absent
php artisan key:generate        # si APP_KEY vide
php artisan migrate --seed      # ou migrate:fresh --seed (DESTRUCTIF)

php artisan serve               # http://localhost:8000  (terminal A)
php artisan queue:work --queue=paiements,etats,default --tries=3   # (terminal B)

# 2) Frontend
cd ..\frontend
npm install                     # première fois
npm run dev                     # http://localhost:5173  (terminal C)
```

---

## Concepts métier clés

- **Catégorie** d'agent = `INDICE_SUP_800 / INDICE_400_799 / INDICE_INF_400`, déduite de l'indice (ou du salaire brut), reprise de SEMO.
- **Per diem** = `taux_journalier(catégorie × zone × type) × nb_jours` selon le **barème officiel ANaTT**
  (4 zones : `INTERIEUR`, `EXT_ZONE_I`, `EXT_ZONE_II`, `EXT_ZONE_III` — II = III ; règle des 12 h).
  Répartition : **avance 70 %** / **reliquat 30 %** (part de l'avance **paramétrable** par le SUPER_ADMIN, 70 % par défaut ; le reliquat absorbe l'arrondi). Exonération si prise en charge par un tiers.
- **Une mission** regroupe **N ordres de mission** (1 OM = 1 bénéficiaire). Le **CAV** (conducteur) est payé selon **sa** catégorie.
- **Validation** : chaque mission porte **deux états de paiement** — l'état des **avances**, puis celui des
  **reliquats** — qui passent chacun le circuit **contrôleur → visa DAF → validation DG**. Un versement n'est
  payable que si l'état **de sa tranche** est validé. L'état des reliquats est signé au montant **payable**
  (somme des reliquats positifs). Les trois signataires doivent être trois personnes distinctes. Le PDF de
  chaque OM reprend les signatures de l'état des avances qui le couvre (même code de vérification) ; le
  document de référence reste l'état. Les missions sans état (reprise de données) se valident OM par OM.
- **Paiement** = génération d'un fichier (**1 mission × 1 tranche × 1 opérateur**) : MTN → `.txt`, Moov → `.xlsx`.
  Cycle : `EN_ATTENTE → GENERE → CONFIRME` (confirmation **manuelle** après dépôt). Régénération tant que non confirmé ;
  un lot passé en **erreur** remet ses versements en attente avec le numéro **actuel** de l'agent.
  **Idempotence** : un versement confirmé n'est jamais réinclus ; un lot confirmé n'est jamais réécrit — un
  versement devenu payable après coup part dans un **lot complémentaire** (`…_COMPL2`). Un OM dont tous les
  versements sont confirmés passe à **SOLDE** ; un OM **rejeté** voit ses versements **annulés**.
- **Amendement (avenant)** : après paiement de l'avance, les jours facturés d'un OM peuvent changer ; l'avance
  est figée et seul le **reliquat** absorbe l'écart. Un reliquat négatif est un **trop-perçu**, récupéré par
  retenue sur salaire (écran *Régularisations*, document de retenue PDF) — jamais par Mobile Money.

---

## Sécurité & données sensibles

- Les **fichiers de paiement** générés (`.txt` MTN / `.xlsx` Moov) contiennent des données à
  caractère personnel (nom, téléphone, montant). Ils sont écrits sur le disque **`local`**
  (`storage/app/private/paiements/{mission_id}/`, un dossier par mission), **hors racine web**, et ne sont servis que via les endpoints
  de téléchargement **authentifiés** (`permission:paiements.*`). Ils ne sont **pas chiffrés** au repos :
  prévoyez un contrôle d'accès disque et une politique de purge/rétention côté exploitation.
- Accès par **permission** (Sanctum + middleware `permission:{clé}`), `Gate::before` pour le SUPER_ADMIN.
- **Anti-double-paiement** : un versement `CONFIRME` — ou porté par un lot confirmé — n'est jamais
  réinclus (idempotence), et l'intégrité des états signés est scellée par **HMAC** (montants + tranche).
- **Séparation ordonnateur / payeur** : un profil ou un utilisateur qui valide l'état (`om.approuver`) ne
  peut pas générer ni confirmer les paiements ; la règle est aussi vérifiée pour chaque membre d'un profil
  modifié dans la matrice des permissions.

---

## Exploitation

- **Sauvegardes** : `php artisan db:backup --compress` (planifiée chaque jour à 02:00 via
  `php artisan schedule:run`, rétention 30 jours, dans `storage/app/backups/`).
- **Restauration** : écran *Administration › Sauvegardes* (SUPER_ADMIN, confirmation par recopie du nom du
  fichier) ou `php artisan db:restore <fichier> --force`. Une **sauvegarde de sécurité** de l'état courant
  est prise avant d'écraser la base. Requiert `mysqldump` et `mysql` (`DB_DUMP_BINARY` /
  `DB_RESTORE_BINARY` dans le `.env`). À tester sur une base de recette avant d'en avoir besoin.
  La matrice des permissions de la sauvegarde s'applique dès la requête suivante (cache purgé).
- **Hors sauvegarde de la base** : `storage/app/settings.json` (part d'avance, exercice, SMTP…), les paraphes
  des signataires et les fichiers produits (PDF, fichiers de paiement) vivent sur le disque. Les conserver
  en sauvegardant `storage/` ; ne jamais écraser ce dossier lors d'une mise à jour.
- **Mise à jour d'une instance** : voir [`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md) (§ *Mise à jour*, *Notes de
  version 1.3*) et le runbook [`docs/runbook-mise-a-jour-cron.md`](docs/runbook-mise-a-jour-cron.md).

---

## Structure

```
anatt-ordre-mission/
├── CLAUDE.md            # contexte/spec détaillée du projet
├── start-dev.ps1        # démarre l'env de dev (Windows)
├── stop-dev.ps1         # arrête l'env de dev
├── backend/             # API Laravel
│   ├── app/{Http,Models,Services,Jobs}
│   ├── database/{migrations,seeders,factories}
│   ├── tests/{Feature,Unit}
│   └── docs/queues.md   # documentation des files (worker)
└── frontend/            # SPA Vue 3
    └── src/{views,components,stores,services,types}
```

---

## Tests

```powershell
cd backend
.\vendor\bin\pest                 # 565 tests (67 fichiers)
.\vendor\bin\pint --test          # style de code (vérification ; sans --test : corrige)

cd ..\frontend
npm run type-check                # vérification TypeScript
npm run test:unit -- --run        # Vitest
npm run lint:check                # ESLint + oxlint (vérification ; `npm run lint` corrige)
npm run build                     # build de production
npm run test:e2e                  # Playwright — parcours critique (API mockée, aucun backend requis)
```

> Le CI exécute ces mêmes vérifications à chaque PR : un job **Lint** (Pint + ESLint/oxlint, en mode
> vérification), les suites Pest et Vitest, le build et le parcours Playwright.
>
> La suite Pest s'exécute **sans `.env`** (`phpunit.xml` fournit une `APP_KEY` de test). Pour Playwright
> avec un Chromium déjà installé d'une autre version : `PLAYWRIGHT_CHROMIUM_EXECUTABLE=/chemin/chromium`.

---

## Documentation

| Document | Contenu |
|---|---|
| `CLAUDE.md` | Modèles de données, endpoints API, règles métier, design system |
| `CHANGELOG.md` | Historique des modifications |
| `docs/Guide-Utilisateur-ANaTT.docx` | Guide utilisateur (v1.4) — généré par `docs/generer_guide_utilisateur.py` |
| `docs/ANaTT_Documentation_Technique_Fonctionnelle.docx` | Documentation technique et fonctionnelle (v1.3) — générée par `docs/generer_documentation_technique.py` |
| `docs/SCENARIO-DEMO.md` | Scénario de démonstration (fichiers réels MTN / Moov, variantes) |
| `DEPLOY-CPANEL.md`, `UPLOAD-VENDOR.md` | Déploiement sur cPanel, mise à jour, restauration |
| `docs/checklist-post-deploiement.md`, `docs/runbook-mise-a-jour-cron.md` | Vérifications et déroulé du jour J |
| `docs/circuits-validation.md`, `docs/amendement-om.md` | Circuits de validation, amendement et trop-perçus |
| `docs/rbac-flexible.md`, `docs/workflows-par-profil.md` | Droits d'accès et parcours par profil |
| `docs/parametrage-part-avance.md`, `docs/annee-fiscale.md`, `docs/categories-indice.md` | Paramétrages métier |
| `docs/revue-projet-2026-10.md` | Revue complète d'octobre 2026 (couverture par module, correctifs, suites à donner) |
| `backend/docs/queues.md`, `backend/docs/openapi.yaml` | Files et worker ; description OpenAPI 3.0 de toutes les routes (verrouillée par `OpenApiTest`) |
