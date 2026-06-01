# ANaTT — Gestion des Ordres de Mission & Paiements Mobile Money

Application de suivi des **ordres de mission émis par SEMO** et de **génération des fichiers de paiement** Mobile Money (MTN / Moov) pour l'ANaTT (Agence Nationale des Transports Terrestres, Bénin).

> **Règle d'or** — l'application **ne crée pas** les OM (SEMO les émet) et **ne réalise pas** les paiements (faits sur les plateformes MTN/Moov). Elle **retranscrit** les OM et **génère** les fichiers de paiement à déposer manuellement. Aucune API SEMO, aucune API opérateur, aucun réimport de fichier résultat.

---

## Stack

| Couche | Technologies |
|---|---|
| Backend | Laravel 11 · PHP 8.3+ · Sanctum · MySQL 8 · (Redis facultatif) |
| Frontend | Vue 3 (Composition API, TypeScript) · Vite · Tailwind · Pinia |
| Tests | Pest / PHPUnit (137 tests) · Vitest |

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

| Email | Rôle | Peut |
|---|---|---|
| `admin@anatt.bj` | admin | tout |
| `gestion@anatt.bj` | gestionnaire_om | agents, retranscription/validation des OM |
| `finance@anatt.bj` | agent_financier | génération & confirmation des paiements |
| `lecteur@anatt.bj` | lecteur | lecture seule |

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
  Répartition : **avance ≈ 1/3**, **reliquat ≈ 2/3**. Exonération si prise en charge par un tiers.
- **Une mission** regroupe **N ordres de mission** (1 OM = 1 bénéficiaire). Le **CAV** (conducteur) est payé selon **sa** catégorie.
- **Paiement** = génération d'un fichier (**1 mission × 1 tranche × 1 opérateur**) : MTN → `.txt`, Moov → `.xlsx`.
  Cycle : `EN_ATTENTE → GENERE → CONFIRME` (confirmation **manuelle** après dépôt). Régénération tant que non confirmé ;
  **idempotence** : un versement confirmé n'est jamais réinclus. Un OM dont tous les versements sont confirmés passe à **SOLDE**.

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
.\vendor\bin\pest                 # 137 tests, 300 assertions

cd ..\frontend
npm run type-check                # vérification TypeScript
npm run build                     # build de production
```

---

## Documentation

- `CLAUDE.md` — modèles de données, endpoints API, règles métier, design system.
- `backend/docs/queues.md` — files Redis/DB, worker, suivi de progression.
