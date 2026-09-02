# ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money

Application de suivi des **ordres de mission émis par SEMO** et de **génération des fichiers de paiement** Mobile Money (MTN / Moov) pour l'ANaTT (Agence Nationale des Transports Terrestres, Bénin).

> **Règle d'or** — l'application **ne crée pas** les OM (SEMO les émet) et **ne réalise pas** les paiements (faits sur les plateformes MTN/Moov). Elle **retranscrit** les OM et **génère** les fichiers de paiement à déposer manuellement. Aucune API SEMO, aucune API opérateur, aucun réimport de fichier résultat.

---

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel 11 · PHP 8.3+ · Sanctum · MySQL 8 · (Redis facultatif) |
| Frontend | Vue 3 (Composition API, TypeScript) · Vite · Tailwind · Pinia |
| Tests | Pest / PHPUnit (484 tests, 58 fichiers) · Vitest · Playwright (parcours critique e2e) |

---

## Démarrage rapide (Windows)

Prérequis : **WAMP** (MySQL démarré), **PHP 8.3+**, **Node 18+**, **Composer**.

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

- **Catégorie** d'agent = `ORDRE_1 / ORDRE_2 / ORDRE_3`, déduite de l'indice (ou du salaire brut), reprise de SEMO.
- **Per diem** = `taux_journalier(catégorie × zone × type) × nb_jours` selon le **barème officiel ANaTT**
  (4 zones : `INTERIEUR`, `EXT_ZONE_I`, `EXT_ZONE_II`, `EXT_ZONE_III` — II = III ; règle des 12 h).
  Répartition : **avance 70 %** / **reliquat 30 %** (part de l'avance **paramétrable** par le SUPER_ADMIN, 70 % par défaut ; le reliquat absorbe l'arrondi). Exonération si prise en charge par un tiers.
- **Une mission** regroupe **N ordres de mission** (1 OM = 1 bénéficiaire). Le **CAV** (conducteur) est payé selon **sa** catégorie.
- **Paiement** = génération d'un fichier (**1 mission × 1 tranche × 1 opérateur**) : MTN → `.txt`, Moov → `.xlsx`.
  Cycle : `EN_ATTENTE → GENERE → CONFIRME` (confirmation **manuelle** après dépôt). Régénération tant que non confirmé ;
  **idempotence** : un versement confirmé n'est jamais réinclus. Un OM dont tous les versements sont confirmés passe à **SOLDE**.

---

## Sécurité & données sensibles

- Les **fichiers de paiement** générés (`.txt` MTN / `.xlsx` Moov) contiennent des données à
  caractère personnel (nom, téléphone, montant). Ils sont écrits sur le disque **`local`**
  (`storage/app/private/paiements`), **hors racine web**, et ne sont servis que via les endpoints
  de téléchargement **authentifiés** (`permission:paiements.*`). Ils ne sont **pas chiffrés** au repos :
  prévoyez un contrôle d'accès disque et une politique de purge/rétention côté exploitation.
- Accès par **permission** (Sanctum + middleware `permission:{clé}`), `Gate::before` pour le SUPER_ADMIN.
- **Anti-double-paiement** : un versement `CONFIRME` n'est jamais réinclus (idempotence),
  et l'intégrité des états signés est scellée par **HMAC** (montants + tranche).

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
.\vendor\bin\pest                 # 484 tests (58 fichiers)
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

---

## Documentation

- `CLAUDE.md` — modèles de données, endpoints API, règles métier, design system.
- `backend/docs/queues.md` — files Redis/DB, worker, suivi de progression.
