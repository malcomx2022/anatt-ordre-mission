# CLAUDE.md — ANaTT Système de Gestion des Ordres de Mission

> Ce fichier est lu automatiquement par Claude Code à chaque session.
> Il contient tout le contexte nécessaire pour développer l'application sans instructions répétées.

---

## 1. CONTEXTE PROJET

**Application** : Suivi des ordres de mission émis par **SEMO** et **génération des fichiers de paiement** Mobile Money (MTN, Moov).  
**Client** : ANaTT — Agence Nationale des Transports Terrestres, République du Bénin  
**Version** : 1.0.0  
**Statut** : En développement

### ⚠️ RÈGLE D'OR — périmètre de l'application

L'application **NE crée PAS** les ordres de mission (c'est **SEMO** qui les émet) et **NE réalise PAS** les paiements (faits sur les plateformes MTN / Moov).
Elle **RETRANSCRIT** les OM, puis **GÉNÈRE** les fichiers de paiement à déposer sur les plateformes des opérateurs.

> Aucune API SEMO. Aucune API de décaissement. Aucun réimport de fichier résultat opérateur.

### Workflow métier principal

```
SEMO émet l'OM → retranscription dans l'app → regroupement par mission
→ calcul per diem (barème ANaTT) → génération fichier de paiement (.txt MTN / .xlsx Moov)
→ dépôt manuel sur la plateforme opérateur → confirmation manuelle dans l'app
```

### Règles métier (référence pour toutes les tâches)

- **1 OM SEMO = 1 enregistrement = 1 bénéficiaire** (mono-bénéficiaire).
- Une **mission** (événement) regroupe **N OM individuels** (participants + chauffeurs).
- Le **CAV** (conducteur de véhicule administratif) est un **bénéficiaire à part entière**, payé selon **SA** catégorie. Le champ « conducteur à bord » sur l'OM d'un participant n'est qu'une **mention logistique**.
- **Catégorie d'indice** reprise telle quelle de SEMO — le code porte le seuil, jamais affiché brut :
  - `INDICE_SUP_800` → « Indice ≥ 800 » — salaire brut ≥ 372 000
  - `INDICE_400_799` → « Indice 400-799 » — salaire brut 186 000-372 000
  - `INDICE_INF_400` → « Indice < 400 » — salaire brut < 186 000
  - Formule de secours : `indice = salaire_base × 12 / 3097`.
  - Source de vérité des seuils et libellés : `App\Support\CategorieIndice` (miroir TS :
    `types/agent.ts` → `CATEGORIE_LABELS` / `categorieLabel()`, seuils dans `utils/benin.ts`).
  - Les anciens codes `ORDRE_1/2/3` ne sont **plus produits** ; ils restent **acceptés en entrée**
    (filtres API, import CSV/XLSX) et traduits par `CategorieIndice::normaliser()`. Le journal
    d'audit conserve le vocabulaire en vigueur au moment de l'action — il ne se réécrit pas.
- **Per diem** = taux journalier (catégorie × **ZONE** × **type de prise en charge**) × nb jours.
  - 4 zones : `INTERIEUR`, `EXT_ZONE_I`, `EXT_ZONE_II`, `EXT_ZONE_III` (**II = III**, taux identiques).
  - Types `INTERIEUR` : `UN_REPAS` / `DEUX_REPAS` / `JOURNEE_COMPLETE`.
  - Types `EXTERIEUR` : `JOURNEE_COMPLETE` / `LOGE_NOURRI` / `LOGE_SEUL` / `NOURRI_SEUL`.
  - **Règle des 12 h** pour le nb de jours (période ≥ 12 h = jour entier ; retour 0-12 h = rien).
  - Répartition : **AVANCE 70 %** / **RELIQUAT 30 %** par défaut — part de l'avance paramétrable (`partAvance`, cf. §7).
  - **Exonération** si prise en charge par un organisme tiers (aucun frais alloué).
- **Téléphone et opérateur** (MTN / Moov) viennent du **référentiel agents**, pas de l'OM.
- **Référence SEMO** = clé naturelle **UNIQUE** (anti-doublon).
- **Paiements** : GÉNÉRATION de fichiers — un fichier = **1 mission × 1 tranche [`AVANCE` | `RELIQUAT`] × 1 opérateur**.
  - MTN → `.txt` ; Moov → `.xlsx`.
  - Statuts versement : `EN_ATTENTE` / `GENERE` / `CONFIRME` / `ANNULE` (versements d'un OM **rejeté**, exclus des fichiers).
  - Confirmation **manuelle** après dépôt réussi. Régénération possible tant que **non confirmé**.
    Un lot passé en **`ERREUR`** (dépôt échoué) remet ses versements `EN_ATTENTE` avec les coordonnées **courantes** de l'agent.
  - **Idempotence** : un versement `CONFIRME` (ou porté par un lot `CONFIRME`) n'est **jamais** réinclus dans un nouveau fichier (anti-double-paiement).
  - **Lot complémentaire** : un lot `CONFIRME` n'est jamais réécrit ; un versement devenu payable après coup part dans
    un lot de **séquence** suivante (`sequence` 2, 3… ; fichier `…_COMPL{n}`). Fichiers rangés par mission : `paiements/{mission_id}/`.
    L'écran de génération le propose de lui-même (« Générer le lot complémentaire ») quand l'aperçu d'un lot confirmé
    compte encore des bénéficiaires ; la liste des lots est triée par date puis par `id` (le plus récent d'abord).
  - **Solde d'un OM** : un OM dont tous les versements **non nuls** sont réglés — `CONFIRME`, ou `RECOUVRE` pour un
    trop-perçu — passe à `SOLDE`, à la confirmation d'un lot comme au constat d'un recouvrement
    (`OrdreMission::solderSiRegle`, aussi après un amendement qui ramène le reliquat à 0). Une tranche nulle — part d'avance à 0 ou 100 % — n'entre dans aucun fichier et
    ne le retient pas. Un OM exonéré (montant nul) est soldé dès sa validation, dans les deux circuits.
- **États de paiement PAR TRANCHE** — une mission porte **DEUX états**, chacun avec son circuit complet
  (récapitulatif → `BROUILLON` → `CONTROLE` → `VISA_DAF` → `VALIDE` ; rejet motivé possible à chaque étape) :
  1. **État des AVANCES** (Σ avances des OM) : sa validation finale par le DG fait passer les OM à `VALIDE`
     et ouvre les fichiers de paiement **avance**.
  2. **État des RELIQUATS** (Σ reliquats) : établi **uniquement après** validation de l'état des avances
     (422 sinon) ; même circuit ; ouvre les fichiers **reliquat**.
  - Chaque état a **son propre numéro** de registre (saisi manuellement, exigé avant sa validation finale).
  - Verrou d'éligibilité : un versement n'est payable que si l'état **de sa tranche** est `VALIDE`
    (missions sans aucun état = reprise de données, périmètre par OM `VALIDE` comme avant).
  - Intégrité : tableau `lignes` figé au récapitulatif, paraphe archivé à la signature (purgé au rejet),
    scellé HMAC (inclut la tranche) sur les montants signés.
  - **Séparation des tâches** (`workflow.separation_des_taches`, vaut aussi pour le SUPER_ADMIN) : le DAF ne vise pas
    un état qu'il a contrôlé ; le DG ne valide pas un état qu'il a contrôlé **ou** visé.
  - **PDF de l'OM** : il reprend les signatures de l'**état des avances** qui couvre l'OM (OM présent dans `lignes`) —
    même instantané, paraphe archivé et code de vérification (`PdfGenerator::etatSignataire`). Une signature portée
    par l'OM lui-même (circuit par OM, données héritées) reste prioritaire ; OM `REJETE`/`ANNULE`, état `REJETE` et
    brouillon n'en montrent aucune. Clé de cache `ordres/OM-{ref}-{statut}[-ETAT-{statut_etat}][-AMD{n}].pdf` (n = nombre d'amendements).
    Après un amendement, les libellés suivent la répartition réelle (avance engagée, reliquat ajusté, trop-perçu).
  - **Deux circuits de validation** (par état = canonique ; par OM = repli des missions sans état) :
    ils sont **exclusifs par mission** (verrou `MissionService::exigerAbsenceDEtat`) et unifiés à la porte
    de paiement (`Versement::scopeEligiblesLot`). Référence unique : `docs/circuits-validation.md`.
- **Amendement d'un OM (avenant) après paiement de l'avance** — un OM peut voir ses **jours facturés**
  changer (rallonge/réduction) une fois l'avance payée. L'avance est alors **figée** (`avance_engagee`,
  posée à la validation de l'état des avances) et **seul le reliquat** absorbe l'écart :
  `reliquat = montant_total − avance_engagee` (peut être **négatif**). Endpoint
  `POST /api/ordres/:id/amender` (permission `om.retranscrire`, **motif ≥ 10 car.**, `MissionService::amender`) ;
  chaque amendement est tracé dans la table **`om_amendements`** + audit `AMEND_OM`.
  - **Gardes** : refus si OM `REJETE`/`ANNULE`, si un état de tranche est en cours de signature
    (`CONTROLE`/`VISA_DAF` → rejet guidé), si l'état des reliquats est **validé**, ou si un trop-perçu de l'OM
    a déjà été **`RECOUVRE`** (retenue soldée → avenant formel, hors périmètre), ou si le **reliquat** de l'OM est
    déjà `CONFIRME` (payé : avenant formel — garde utile surtout au circuit par OM, sans état des reliquats).
    Un OM `SOLDE` reste amendable hors reliquat payé / trop-perçu recouvré (avance à 100 % payée,
    reliquat ramené à 0) : il **redevient `VALIDE`** si l'amendement recrée un montant à régler.
    Un trop-perçu encore `A_RECOUVRER` reste amendable (rien n'a été retenu). Le « dernier amendement » se départage
    par `id` quand deux portent le même horodatage.
  - **Garde supplémentaire** : amendement refusé tant qu'un versement de l'OM est `GENERE` (fichier en attente de dépôt :
    confirmer le lot ou le passer en `ERREUR`). Sur le circuit par OM (mission sans état), une avance déjà `CONFIRME`
    est engagée à l'amendement (`avance_engagee`), comme si l'état des avances l'avait figée.
  - **État des reliquats = montant PAYABLE** : Σ des reliquats **positifs**. Les trop-perçus ne diminuent pas le total signé ;
    le PDF les mentionne à part (« trop-perçus hors total, à recouvrer par retenue sur salaire »).
  - **Trop-perçu** (reliquat négatif) : le versement bascule en **`A_RECOUVRER`**, sort du périmètre payable
    (`scopeEligiblesLot` exclut les montants ≤ 0 et les statuts `A_RECOUVRER`/`RECOUVRE`) et rejoint l'écran
    **Régularisations**. La récupération est une **retenue sur salaire hors application** : l'app produit un
    **document de retenue** (PDF, `PdfGenerator::genererDocumentRetenue`) à remettre à la RH, puis constate le
    recouvrement (**`RECOUVRE`**). Référence : `docs/amendement-om.md`.

### Profils utilisateurs (RBAC Sanctum par PERMISSION — middleware `can:`)

8 profils, autorisation par **clé de permission** (Gates définies dans `AuthServiceProvider`, source : `App\Support\Permissions`).

| Profil | Rôle |
|--------|------|
| `SUPER_ADMIN` | Accès **total** (Gate::before). Seul à gérer les super admins et les paramètres système. Créé au seed. |
| `ADMIN` | Utilisateurs (sauf super admins), référentiels, barème, audit ; supervise sans exécuter les paiements |
| `GESTIONNAIRE_OM` | Retranscrit/gère missions, OM et agents (`om.retranscrire`, `personnel.gerer`) |
| `AGENT_FINANCIER` | Génère les fichiers MTN/Moov et confirme les paiements (`paiements.generer/confirmer`) |
| `CONTROLEUR` | Étape 1 du circuit : contrôle de conformité de l'état (`om.valider`) ; consulte paiements et audit |
| `DAF` | Étape 2 du circuit : appose le visa « Vu » sur l'état de paiement (`om.viser`) ; consulte paiements et audit |
| `DG` | Étape 3 et dernière : validation finale de l'état, ouvre le paiement (`om.approuver`) ; consulte paiements et audit |
| `LECTEUR` | Lecture seule (dashboard, OM, rapports) |

Clés de permission : `dashboard.consulter` · `personnel.consulter|gerer` · `om.consulter|retranscrire|valider|numeroter|viser|approuver|rejeter` · `paiements.consulter|generer|confirmer` · `baremes.consulter|modifier` · `rapports.consulter` · `audit.consulter` · `utilisateurs.gerer` · `superadmins.gerer` · `parametres.gerer` · `roles.gerer`.

> **RBAC flexible — matrice éditable à chaud.** La matrice rôle → permissions est
> personnalisable depuis l'écran **Matrice des permissions** (`/admin/roles`) sans
> redéploiement. Source de vérité vivante : table **`role_permissions`** (via
> `App\Support\PermissionRepository`, mis en cache, purgé à chaque écriture) ;
> `config/profils.php` reste le **défaut / repli** (installation neuve, tests,
> profils jamais personnalisés — le repository retombe seul sur la config si sa
> table n'existe pas). Un profil sans ligne hérite du défaut ; dès qu'on l'édite,
> ses lignes font foi (réinitialisable au défaut). `Permissions::pour()` délègue au
> repository ; les droits de l'utilisateur courant (login + `/auth/me/permissions`)
> reflètent la matrice à jour dès la connexion/rafraîchissement suivant. Le cache de la matrice
> est **sans expiration** : purgé à chaque écriture et par `db:restore` ; toute modification de
> `role_permissions` hors application (import SQL) impose une purge du cache.
> **Lecture** de la matrice : `utilisateurs.gerer` (l'ADMIN consulte). **Écriture** :
> `roles.gerer`, **exclusive au SUPER_ADMIN** (comme `superadmins.gerer` /
> `parametres.gerer`, jamais attribuables). **Garde-fous serveur** : SUPER_ADMIN non
> éditable, clés réservées refusées, et **séparation ordonnateur/payeur** (un profil
> avec `om.approuver` ne peut cumuler `paiements.generer`/`confirmer` — contrôlé aussi sur
> l'effectif de chaque membre du profil, droits individuels compris). Chaque édition
> est auditée (`ROLE_PERM_UPDATE` / `ROLE_PERM_RESET`). Référence : `docs/rbac-flexible.md`.

> **Cohérence menu ↔ routes ↔ API.** Chaque entrée du menu latéral
> (`components/shared/navigation.ts`) porte la **même** clé que le `meta.permission`
> de sa route (`router/index.ts`), qui porte elle-même la clé exigée par l'API.
> Les trois niveaux sont verrouillés par des tests (et `backend/docs/openapi.yaml` par `tests/Feature/OpenApiTest.php` : chaque route y est décrite avec sa permission) : `tests/Feature/RoutesPermissionsTest.php`
> (aucune route API sans permission) et `src/__tests__/navigation.spec.ts`
> (aucun lien mort, aucun écran hors menu, aucun 403 après un clic).

> **Notifications** : adressées aux comptes **actifs** qui détiennent, dans leurs droits **effectifs**, la permission
> de l'étape suivante (`NotificationService::notifier`) — rôles personnalisés et droits individuels compris, retraits
> respectés, SUPER_ADMIN exclu, une seule notification par compte (table dans `docs/rbac-flexible.md`).

> **Super admin** : accès implicite à tout ; il existe toujours ≥ 1 SUPER_ADMIN actif (le dernier ne peut être supprimé, désactivé ni rétrogradé). Comptes de démo (mdp `password`) : `superadmin@`, `admin@`, `gestion@`, `finance@`, `controle@`, `daf@`, `dg@`, `lecteur@anatt.bj`.

---

## 2. STACK TECHNIQUE

### Frontend
- **Framework** : Vue.js **3.6 bêta** (Composition API) + TypeScript strict — `package.json` suit le dist-tag `beta`
  (+ `overrides` des paquets `@vue/*`) ; la version effective est figée par `package-lock.json` (`3.6.0-beta.9`) :
  installer avec `npm ci` pour ne pas changer de bêta
- **Build** : Vite 8 (TypeScript 6, `vue-tsc`)
- **CSS** : Tailwind CSS 3.4+ (config personnalisée ANaTT)
- **State** : Pinia 3
- **Router** : Vue Router 5 (guards d'authentification + permissions)
- **HTTP** : Axios avec intercepteurs (token Bearer)
- **Graphiques** : Chart.js 4
- **PDF** : générés par le serveur (DomPDF) ; l'écran OM-07 affiche un aperçu HTML et télécharge le PDF.
  Aucune visionneuse PDF n'est utilisée (`vue-pdf-embed`, `lucide-vue-next`, `@vueuse/core` retirés le 26/10/2026)
- **Node** : `^20.19.0 || >=22.12.0` (`engines`, Vite 8)
- **Tests** : Vitest 4 + Vue Test Utils (124 tests) ; Playwright (parcours critique e2e, API mockée)

### Backend
- **Framework** : Laravel 12
- **PHP** : **8.4.x 64 bits** (minimum imposé par `composer.lock` : Symfony 8 + spatie/laravel-activitylog ;
  **8.5 non pris en charge** : `phpoffice/phpspreadsheet` 1.30 déclare `<8.5.0`, que `platform_check` ne contrôle pas)
- **Auth** : Laravel Sanctum 4 (SPA tokens)
- **BDD** : MySQL 8 (production) / SQLite (tests)
- **Fuseau** : `Africa/Porto-Novo` (UTC+1, `APP_TIMEZONE`) ; l'API sérialise toujours les dates en UTC (`…Z`).
  Horodatages antérieurs repris d'une heure par la migration `2026_10_26_090000_passer_horodatages_au_fuseau_applicatif`
- **Cache/Queue** : Redis **facultatif** — en mutualisé `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` (ou `database`)
- **PDF** : DomPDF (barryvdh/laravel-dompdf 3.1)
- **Excel** : Maatwebsite/Laravel-Excel 3.1
- **Tests** : Pest 3 + PHPUnit 11 (598 tests, 69 fichiers ; s'exécute sans `.env`, `APP_KEY` de test dans `phpunit.xml`)
- **Debug** : Laravel Telescope (require-dev), enregistré **uniquement** en `local` et s'il est installé (`AppServiceProvider`)

### DevOps
- Développement : WAMP + `start-dev.ps1` / `stop-dev.ps1` (API, worker, Vite)
- Production : hébergement **cPanel mutualisé** (FTP + phpMyAdmin + tâches Cron, AutoSSL) — `DEPLOY-CPANEL.md`
- GitHub Actions (`.github/workflows/ci.yml`) : Lint, Pest, Vitest + type-check, Playwright, build `frontend-dist`
- Pas de Docker dans le dépôt (aucun `docker-compose.yml`)

---

## 3. STRUCTURE DU PROJET

```
anatt-ordre-mission/
├── CLAUDE.md                    ← CE FICHIER
├── AGENTS.md · README.md · CHANGELOG.md
├── DEPLOY-CPANEL.md · UPLOAD-VENDOR.md   ← déploiement / mise à jour (cPanel)
├── start-dev.ps1 · stop-dev.ps1          ← environnement de dev (Windows)
├── .github/workflows/ci.yml
├── frontend/                    ← Application Vue 3
│   ├── src/
│   │   ├── assets/
│   │   │   ├── logo-anatt.png        ← Logo officiel couleur (fonds clairs)
│   │   │   ├── logo-anatt-white.png  ← Logo blanc, barre tricolore conservée (fonds sombres)
│   │   │   └── icon-anatt.png         ← Marque carrée (vert ANaTT + ANaTT blanc) — base favicon
│   │   ├── components/
│   │   │   ├── shared/         ← AppLayout, AppSidebar, AppTopBar, AppButton, AppCard, AppBadge,
│   │   │   │                     AppTable, AppModal, AppAlert, AppPagination… + navigation.ts (menu)
│   │   │   ├── agents/  audit/  dashboard/  etats/  missions/
│   │   │   └── paiements/      ← PaiementGeneration.vue (génération MTN/Moov, suivi des gros lots)
│   │   ├── composables/        ← useApi, useAuth, usePartAvance, useSearch, useToast
│   │   ├── services/           ← un client par domaine (agents, ordres, missions, etats, paiements,
│   │   │                         rapports, roles, users, audit, baremes, notifications, auth) + http.ts
│   │   ├── stores/             ← auth, notifications, ordres-mission (Pinia)
│   │   ├── router/index.ts     ← routes + meta.permission
│   │   ├── types/              ← agent, bareme, etat, mission, paiement (+ index)
│   │   ├── utils/              ← benin (téléphone, catégorie, moisLocal), permissions, roles, etats,
│   │   │                         exercice (exerciceDe : miroir de Parametres::anneeFiscalePour)
│   │   ├── directives/         ← permission (v-permission)
│   │   ├── views/              ← Pages : les 42 écrans du registre (§5) + écrans ajoutés depuis
│   │   │                         (états, régularisations, signatures, permissions par utilisateur…)
│   │   └── __tests__/          ← Vitest
│   ├── e2e/                    ← Playwright (parcours-critique.spec.ts)
│   ├── public/runtime-config.js ← URL de l'API modifiable après build
│   ├── tailwind.config.js
│   └── vite.config.ts
│
├── backend/                     ← API Laravel 12
│   ├── app/
│   │   ├── Console/Commands/   ← DatabaseBackup (db:backup), DatabaseRestore (db:restore)
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php · UserController.php · RoleController.php
│   │   │   ├── AgentController.php
│   │   │   ├── MissionController.php      ← missions + OM (index/show/storeMission/amender/pdf)
│   │   │   ├── ValidationController.php   ← circuit par OM : valider / rejeter (missions sans état)
│   │   │   ├── EtatPaiementController.php ← états de paiement (récapitulatif, circuit, PDF)
│   │   │   ├── PaiementController.php     ← lots (génération/confirmation/erreur), régularisations
│   │   │   ├── BaremeController.php · RapportController.php · AuditController.php
│   │   │   └── SettingsController.php · SignatureController.php · BackupController.php · NotificationController.php
│   │   ├── Models/             ← User, Agent, Mission, OrdreMission, OmAmendement, EtatPaiement,
│   │   │                         Versement, LotPaiement, Bareme, ZoneGeographique, AuditLog,
│   │   │                         Role, RolePermission, UserPermission, Notification
│   │   ├── Services/
│   │   │   ├── MissionService.php          ← retranscription, validation par OM, amendement
│   │   │   ├── EtatPaiementService.php     ← circuit des états + propagation (+ EtatPaiementPdf, LignesEtatPaiement)
│   │   │   ├── CalculIndemniteService.php
│   │   │   ├── LotPaiementService.php · AgentService.php · BaremeService.php · RapportService.php
│   │   │   ├── AuditService.php · NotificationService.php · UserPermissionService.php
│   │   │   ├── PdfGenerator.php · SignatureParametres.php
│   │   │   └── Paiement/
│   │   │       ├── FileGenerator.php      ← lot, séquence (lot complémentaire), garde état validé
│   │   │       ├── MtnFileGenerator.php   ← .txt
│   │   │       └── MoovFileGenerator.php  ← .xlsx (PhpSpreadsheet)
│   │   ├── Support/            ← Permissions, PermissionRepository, CategorieIndice, Parametres,
│   │   │                         Workflow, MontantEnLettres, Telephone, ZoneResolver…
│   │   ├── Observers/  Exports/  Imports/  Providers/
│   │   └── Jobs/
│   │       ├── GenererLotPaiementJob.php  ← file « paiements »
│   │       ├── GenererEtatJob.php         ← file « etats »
│   │       └── SendNotification.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── docs/                   ← queues.md, openapi.yaml
│   ├── tests/{Feature,Unit}    ← Pest
│   └── routes/
│       ├── api.php
│       └── console.php         ← planification (db:backup 02:00, purge des tokens)
│
└── docs/                        ← Documentation
    ├── Guide-Utilisateur-ANaTT.docx              ← généré par generer_guide_utilisateur.py
    ├── ANaTT_Documentation_Technique_Fonctionnelle.docx ← généré par generer_documentation_technique.py
    ├── SCENARIO-DEMO.md · revue-projet-2026-10.md
    ├── circuits-validation.md · amendement-om.md · rbac-flexible.md · workflows-par-profil.md
    ├── parametrage-part-avance.md · annee-fiscale.md · categories-indice.md
    ├── checklist-post-deploiement.md · runbook-mise-a-jour-cron.md
    ├── 00_galerie_index.html … 53_moov.html  ← pages d'écran tirées des CAPTURES de l'app réelle (ecrans/*.jpg),
    │                                           générées par generer_maquettes.py (refonte du 26/10/2026)
    └── capturer_ecrans.cjs · ecrans/        ← captures Playwright (API + SPA sur les données de démonstration)
```

---

## 4. DESIGN SYSTEM ANaTT

### ⚠️ RÈGLES ABSOLUES — ne jamais déroger

1. Le logo ANaTT (`src/assets/logo-anatt*.png`) est embarqué/bundlé en local — jamais de lien externe. Composant `AppLogo` (`dark` → variante blanche). Source du logo : `docs/logoannat.png` ; favicons régénérables via `docs/make_favicons.php`
2. `Sora` pour TOUS les titres, boutons, labels
3. `DM Sans` pour TOUT le corps de texte, paragraphes, inputs
4. La `flag-bar` (barre tricolore) avant CHAQUE footer
5. `card-hover` sur TOUTES les cartes cliquables
6. Thème **vert** (#1D9E4F) pour l'interface publique / Thème **bleu** (#2563EB) pour l'app de gestion
7. Icônes SVG inline uniquement (style Lucide) — jamais d'emoji, jamais de font-icon

### Couleurs — Tailwind config

```typescript
// tailwind.config.ts
extend: {
  colors: {
    anatt: {
      50:  '#EDFAF2',
      100: '#C5F0D5',
      200: '#86DFA6',
      300: '#4ACD7A',
      400: '#27B35D',
      500: '#1D9E4F',  // PRIMARY — boutons CTA, liens, badges actifs
      600: '#1A8C48',  // Hover sur primary
      700: '#14713A',
      800: '#0F5C2E',
      900: '#0A3D1F',  // Sidebar, hero background
      950: '#072E17',
    },
    gold: {
      50:  '#FBF3E0',
      100: '#F5E4BA',
      200: '#EBCF8A',
      300: '#E0BD6A',
      400: '#D4A843',  // SECONDARY — stats, avertissements
      500: '#B8891A',
      600: '#8B6914',
      700: '#6B5010',
    },
    benin: {
      red:    '#CE1126',
      green:  '#1D9E4F',
      yellow: '#D4A843',
    },
    // Couleurs interface app gestion (différent du site public)
    app: {
      primary: '#2563EB',   // Bleu application
      sidebar: '#0F172A',   // Sidebar dark
    }
  },
  fontFamily: {
    display: ['Sora', 'sans-serif'],
    body:    ['DM Sans', 'sans-serif'],
  }
}
```

### CSS animations globales

```css
/* globals.css */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-up { animation: fadeUp .6s ease-out both; }
.delay-100 { animation-delay: .1s; }
.delay-200 { animation-delay: .2s; }
.delay-300 { animation-delay: .3s; }

.card-hover {
  transition: all .3s cubic-bezier(.4, 0, .2, 1);
}
.card-hover:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px -10px rgba(0, 0, 0, 0.08);
}

.hero-pattern {
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.05) 1px, transparent 0);
  background-size: 40px 40px;
}

.flag-bar {
  height: 3px;
  background: linear-gradient(90deg,
    #1D9E4F 0%, #1D9E4F 33%,
    #D4A843 33%, #D4A843 66%,
    #CE1126 66%, #CE1126 100%
  );
}
```

### Composants — patterns HTML

```html
<!-- Bouton Primary -->
<button class="px-6 py-3 rounded-xl bg-anatt-500 text-white font-display font-bold text-sm
               hover:bg-anatt-600 shadow-[0_4px_12px_rgba(29,158,79,0.25)]
               transition-all duration-200 active:scale-95">
  Créer l'ordre
</button>

<!-- Bouton Danger -->
<button class="px-6 py-3 rounded-xl bg-red-500 text-white font-display font-bold text-sm
               hover:bg-red-600 transition-all duration-200">
  Rejeter
</button>

<!-- Bouton Outline -->
<button class="px-5 py-2.5 rounded-xl border-2 border-anatt-500 text-anatt-600
               font-display font-semibold text-sm hover:bg-anatt-500 hover:text-white
               transition-all duration-200">
  Voir détail
</button>

<!-- Card standard -->
<div class="group bg-white rounded-2xl p-6 border border-gray-100 card-hover
            overflow-hidden relative">
  <div class="absolute top-0 left-0 right-0 h-1 bg-anatt-500
              scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left">
  </div>
  <!-- contenu -->
</div>

<!-- Badge statut -->
<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-anatt-50 text-anatt-600">Validé</span>
<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-gold-50 text-gold-600">En attente</span>
<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-600">Rejeté</span>
<span class="px-3 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-600">Brouillon</span>

<!-- Stat counter -->
<div class="font-display text-4xl font-extrabold"
     style="background:linear-gradient(135deg,#1D9E4F,#D4A843);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent">
  1 240
</div>

<!-- Table row -->
<tr class="hover:bg-anatt-50/30 transition-colors cursor-pointer">
  <td class="px-6 py-4 text-sm text-gray-900">...</td>
</tr>

<!-- Input -->
<input type="text"
       class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white
              text-sm font-body text-gray-900 placeholder-gray-400
              focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500
              transition-all duration-200">
```

### Layout application (tous les écrans connectés)

```
┌────────────────────────────────────────────────────────────┐
│  SIDEBAR (fixed, 260px, bg-app-sidebar)                    │
│  ┌─ Logo ANaTT base64 (h-10, brightness-0 invert)          │
│  ├─ Profil utilisateur connecté                             │
│  ├─ Navigation par module (icône + label)                   │
│  │   Dashboard · Personnel · Ordres · Paiements            │
│  │   Barème · Rapports · Audit · Administration            │
│  └─ Déconnexion                                            │
├────────────────────────────────────────────────────────────│
│  TOPBAR (fixed, left-offset 260px, h-16, bg-white)        │
│  ├─ Fil d'Ariane + Titre de la page                        │
│  ├─ Notifications (cloche + badge)                          │
│  └─ Menu profil (avatar initiales + rôle)                  │
├────────────────────────────────────────────────────────────│
│  CONTENT (ml-[260px], pt-16, p-8, bg-gray-50)             │
│  └─ Contenu de la page                                     │
└────────────────────────────────────────────────────────────┘
```

---

## 5. REGISTRE DES 42 ÉCRANS

### Module AUTH (3 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| AUTH-01 | `views/auth/LoginView.vue` | `/login` | Formulaire email + mot de passe, logo ANaTT centré |
| AUTH-02 | `views/auth/ForgotPasswordView.vue` | `/mot-de-passe-oublie` | Saisie email, envoi lien reset |
| AUTH-03 | `views/auth/ResetPasswordView.vue` | `/reinitialiser/:token` | Nouveau mot de passe + confirmation |

### Module DASHBOARD (1 écran)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| DASH-01 | `views/dashboard/DashboardView.vue` | `/` | KPIs (OM en cours, validés, paiements mois), graphiques, actions rapides |

### Module PERSONNEL (6 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| PERS-01 | `views/agents/AgentListView.vue` | `/agents` | Table paginée avec filtres, recherche, export |
| PERS-02 | `views/agents/AgentDetailView.vue` | `/agents/:id` | Fiche complète + historique missions |
| PERS-03 | `views/agents/AgentCreateView.vue` | `/agents/nouveau` | Formulaire multi-étapes création |
| PERS-04 | `views/agents/AgentEditView.vue` | `/agents/:id/modifier` | Modification informations agent |
| PERS-05 | `views/agents/AgentProfileView.vue` | `/mon-profil` | Profil de l'agent connecté |
| PERS-06 | `views/agents/AgentImportView.vue` | `/agents/import` | Upload CSV, prévisualisation, import |

### Module ORDRES DE MISSION (8 écrans) — CŒUR DU PROJET

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| OM-01 | `views/missions/MissionListView.vue` | `/ordres` | Table OM (réf. SEMO, bénéficiaire, statut) filtres statut/zone/opérateur |
| OM-02 | `views/missions/MissionCreateView.vue` | `/ordres/nouveau` | Wizard retranscription : mission + N bénéficiaires (participants + CAV), per diem temps réel |
| OM-03 | `views/missions/MissionDetailView.vue` | `/ordres/:id` | Détail complet + valider/rejeter + versements |
| OM-04 | `views/missions/MissionEditView.vue` | `/ordres/:id/modifier` | Redirige vers la fiche (OM non éditables individuellement) |
| OM-05 | `views/missions/MissionValidation1View.vue` | `/ordres/:id/valider-n1` | Redirection (validation unifiée sur la fiche) |
| OM-06 | `views/missions/MissionValidation2View.vue` | `/ordres/:id/valider-dg` | Redirection (plus de double validation N1/DG) |
| OM-07 | `views/missions/MissionPdfView.vue` | `/ordres/:id/pdf` | Aperçu imprimable + téléchargement du PDF serveur |
| OM-08 | `views/missions/MissionHistoryView.vue` | `/ordres/:id/historique` | Journal d'audit de l'OM |

### Module PAIEMENTS (6 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| PAY-01 | `views/paiements/PaiementListView.vue` | `/paiements` | Liste des lots (statut GENERE/CONFIRME/ERREUR) + téléchargement |
| PAY-02 | `views/paiements/PaiementMoovView.vue` | `/paiements/moov` | Génération Moov : mission + tranche → aperçu → `.xlsx` → confirmer |
| PAY-03 | `views/paiements/PaiementMtnView.vue` | `/paiements/mtn` | Génération MTN : mission + tranche → aperçu → `.txt` → confirmer |
| PAY-04 | `views/paiements/PaiementStatutView.vue` | `/paiements/statuts` | Synthèse des versements par opérateur/statut |
| PAY-05 | `views/paiements/PaiementReconciliationView.vue` | `/paiements/reconciliation` | Info : modèle manuel, aucun réimport (cycle de confirmation) |
| PAY-06 | `views/paiements/PaiementHistoriqueView.vue` | `/paiements/historique` | Historique des versements confirmés par période |

### Module TAUX PER DIEM (2 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| TAUX-01 | `views/taux/TauxListView.vue` | `/taux` | Tableau des taux par catégorie/destination |
| TAUX-02 | `views/taux/TauxEditView.vue` | `/taux/configurer` | Formulaire mise à jour taux |

### Module RAPPORTS (4 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| RAP-01 | `views/rapports/RapportDashboardView.vue` | `/rapports` | Tableau de bord analytique général |
| RAP-02 | `views/rapports/RapportMensuelView.vue` | `/rapports/mensuel` | Rapport missions + paiements par mois |
| RAP-03 | `views/rapports/RapportAgentView.vue` | `/rapports/agents` | Rapport par agent : missions, coût per diem |
| RAP-04 | `views/rapports/RapportExportView.vue` | `/rapports/export` | Export Excel/PDF personnalisé |

### Module AUDIT (2 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| AUD-01 | `views/audit/AuditListView.vue` | `/audit` | Journal d'activité système filtrable |
| AUD-02 | `views/audit/AuditDetailView.vue` | `/audit/:id` | Détail d'une action : qui, quoi, quand, IP |

### Module ADMINISTRATION (6 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| ADM-01 | `views/admin/UserManagementView.vue` | `/admin/utilisateurs` | CRUD complet utilisateurs + rôles (liste, filtres, statut, reset mot de passe, **édition du rôle** en modale — révoque les sessions) |
| ADM-02 | `views/admin/UserCreateView.vue` | `/admin/utilisateurs/nouveau` | Formulaire de création d'un utilisateur (nom, email, rôle, téléphone) |
| ADM-03 | `views/admin/RolesView.vue` | `/admin/roles` | Matrice permissions par rôle |
| ADM-04 | `views/admin/SettingsView.vue` | `/admin/parametres` | Config système, nom app, logo, email |
| ADM-05 | `views/admin/EmailConfigView.vue` | `/admin/email` | Config SMTP, templates notifications |
| ADM-06 | `views/admin/BackupView.vue` | `/admin/sauvegardes` | Sauvegardes BDD, téléchargement, restauration (confirmation par recopie du nom ; sauvegarde de sécurité préalable) |

### Pages SYSTÈME (4 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| SYS-01 | `views/system/NotFoundView.vue` | `/:pathMatch(.*)` | 404 avec lien retour accueil |
| SYS-02 | `views/system/ServerErrorView.vue` | `/500` | 500 avec détails techniques (dev) |
| SYS-03 | `views/system/MaintenanceView.vue` | `/maintenance` | Page maintenance avec countdown |
| SYS-04 | `views/system/ForbiddenView.vue` | `/403` | Accès refusé avec rôle requis |

---

## 6. MODÈLES DE DONNÉES

### Mission (événement regroupant N OM)

```typescript
interface Mission {
  id: number
  objet: string                 // thème de la mission
  destination: string
  zone_code: 'INTERIEUR' | 'EXT_ZONE_I' | 'EXT_ZONE_II' | 'EXT_ZONE_III' // déduite de la destination
  date_depart: string           // ISO 8601 (date)
  date_retour: string
  imputation_budgetaire?: string
  prise_en_charge_tiers: boolean // true => exonération (aucun frais)
  observations?: string
  ordres_mission?: OrdreMission[]
}
```

### OrdreMission (1 enregistrement = 1 bénéficiaire)

```typescript
interface OrdreMission {
  id: number
  reference_semo: string        // clé naturelle UNIQUE (anti-doublon)
  mission_id: number
  mission?: Mission
  agent_id: number
  agent?: Agent
  role: 'PARTICIPANT' | 'CAV'    // le CAV est payé selon SA catégorie
  categorie: 'INDICE_SUP_800' | 'INDICE_400_799' | 'INDICE_INF_400'  // reprise de SEMO
  zone_code: 'INTERIEUR' | 'EXT_ZONE_I' | 'EXT_ZONE_II' | 'EXT_ZONE_III'
  type_prise_en_charge: 'UN_REPAS' | 'DEUX_REPAS' | 'JOURNEE_COMPLETE'
                       | 'LOGE_NOURRI' | 'LOGE_SEUL' | 'NOURRI_SEUL'
  nb_jours: number
  montant_total: number         // taux_journalier × nb_jours (FCFA)
  avance: number                // round(montant_total × 0,70)  (70 %)
  avance_engagee?: number | null // figée à la validation de l'état des avances (amendement : reliquat = total − avance_engagee)
  reliquat: number              // montant_total - avance (30 %) ; peut être NÉGATIF après amendement (trop-perçu)
  statut: 'IMPORTE' | 'VALIDE' | 'REJETE' | 'SOLDE' | 'ANNULE' // VERIFIE/VISA_DAF : valeurs héritées, plus produites (circuit OM en une étape ; le 3-signatures vit sur l'état). EN_ATTENTE = statut de versement, pas d'OM.
  conducteur_a_bord?: string    // mention logistique uniquement (≠ bénéficiaire)
  reference_autorisation?: string
  moyen_transport?: string
  commentaire_rejet?: string
  versements?: Versement[]
  amendements?: OmAmendement[]  // journal des rallonges/réductions (chargé sur la fiche détail)
  created_at: string
  updated_at: string
}
```

### Agent (référentiel des bénéficiaires)

```typescript
interface Agent {
  id: number
  nom: string
  prenoms: string
  matricule: string
  ifu?: string
  grade?: string
  indice?: number              // ≥ 800 ; 400-799 ; < 400 → détermine la catégorie
  salaire_brut?: number        // ≥ 372000 / 186000-372000 / < 186000
  categorie: 'INDICE_SUP_800' | 'INDICE_400_799' | 'INDICE_INF_400'  // déduite de l'indice OU du salaire
  telephone: string            // national 10 chiffres, débute par 01
  operateur: 'MTN' | 'MOOV'    // cohérent avec le préfixe du numéro
  actif: boolean
}
```

### Bareme (barème officiel ANaTT — remplace TauxPerDiem)

```typescript
interface Bareme {
  id: number
  categorie: 'INDICE_SUP_800' | 'INDICE_400_799' | 'INDICE_INF_400'
  zone_code: 'INTERIEUR' | 'EXT_ZONE_I' | 'EXT_ZONE_II' | 'EXT_ZONE_III' // II = III
  type_prise_en_charge: 'UN_REPAS' | 'DEUX_REPAS' | 'JOURNEE_COMPLETE'
                       | 'LOGE_NOURRI' | 'LOGE_SEUL' | 'NOURRI_SEUL'
  taux_journalier: number      // FCFA. Unicité (categorie, zone_code, type)
}
```

### Versement (une tranche d'un OM)

```typescript
interface Versement {
  id: number
  ordre_mission_id: number
  lot_id?: number              // null tant que non généré
  type: 'AVANCE' | 'RELIQUAT'
  montant: number              // entier SIGNÉ : un reliquat de trop-perçu peut être négatif
  operateur: 'MTN' | 'MOOV'    // figé depuis le référentiel agent
  telephone: string
  // A_RECOUVRER / RECOUVRE : régularisation d'un trop-perçu (reliquat négatif après amendement),
  // jamais un fichier Mobile Money — récupéré par retenue sur salaire (RH).
  statut: 'EN_ATTENTE' | 'GENERE' | 'CONFIRME' | 'ANNULE' | 'A_RECOUVRER' | 'RECOUVRE'
  date_paiement?: string       // rempli à la confirmation manuelle (ou au constat de recouvrement)
}
```

### OmAmendement (journal d'un amendement des jours facturés d'un OM)

```typescript
interface OmAmendement {
  id: number
  ordre_mission_id: number
  nb_jours_avant: number
  nb_jours_apres: number
  lignes_avant?: LignePerDiem[] // répartition figée avant l'amendement
  montant_total_avant: number
  montant_total_apres: number
  delta_montant: number         // signé (+ rallonge / − réduction)
  motif: string                 // obligatoire, ≥ 10 caractères
  amende_par: number
  amende_par_nom: string        // instantané de l'auteur
  amende_le: string
}
```

### LotPaiement (1 mission × 1 tranche × 1 opérateur × 1 séquence)

```typescript
interface LotPaiement {
  id: number
  mission_id: number
  tranche: 'AVANCE' | 'RELIQUAT'
  operateur: 'MTN' | 'MOOV'
  sequence: number             // 1 = lot initial ; 2.. = lot complémentaire (après confirmation). Unicité (mission, tranche, opérateur, sequence)
  statut: 'GENERE' | 'CONFIRME' | 'ERREUR'
  nom_fichier?: string         // .txt (MTN) / .xlsx (Moov)
  nb_lignes: number
  montant_total: number
  genere_le?: string
  genere_par?: number
  confirme_le?: string
  confirme_par?: number
}
```

### EtatPaiement (1 mission × 1 tranche — le document que le circuit valide)

```typescript
interface EtatPaiement {
  id: number
  mission_id: number
  tranche: 'AVANCE' | 'RELIQUAT'  // unicité (mission_id, tranche)
  numero?: string                 // registre papier, unique, exigé avant la validation finale
  statut: 'BROUILLON' | 'CONTROLE' | 'VISA_DAF' | 'VALIDE' | 'REJETE'
  nb_beneficiaires: number
  montant_total: number           // total DE LA TRANCHE (Σ avances OU Σ reliquats)
  montant_avance: number          // rempli si tranche = AVANCE, sinon 0
  montant_reliquat: number        // rempli si tranche = RELIQUAT, sinon 0
  lignes?: LigneEtat[]            // tableau des bénéficiaires, figé au récapitulatif
  controle_* / visa_daf_* / valide_dg_*  // instantanés des signataires + scellés HMAC (incluent la tranche)
  signature_controle_image?: string      // paraphe archivé à la signature (purgé au rejet)
  signature_daf_image?: string
  signature_dg_image?: string
  commentaire_rejet?: string
}
```

---

## 7. API ENDPOINTS LARAVEL

### Authentification
```
POST   /api/auth/login              → { data: { token, user } }
POST   /api/auth/logout
POST   /api/auth/mot-de-passe/email
POST   /api/auth/mot-de-passe/reset
GET    /api/auth/me                 → User connecté (UserResource)
GET    /api/auth/me/permissions     → Liste des permissions effectives (pilote l'UI)
POST   /api/auth/mot-de-passe/changer → Changement de mot de passe (lève le verrou 423 « mot de passe à changer »)
GET    /api/notifications           → Notifications de l'utilisateur connecté
POST   /api/notifications/lues      → Tout marquer lu
POST   /api/notifications/:id/lu    → Marquer une notification lue
```
> Le login renvoie `{ token, user, permissions[] }` et met à jour `derniere_connexion`.
> Toute route `/api/*` non authentifiée répond **401 en JSON**, même sans en-tête `Accept` (aucune redirection vers une page de login) ;
> il en va de même de ses autres erreurs (405, 404, 403, 422, 500 — correctif n° 29, `bootstrap/app.php`).

### Utilisateurs (administration — `utilisateurs.gerer`)
```
GET    /api/admin/users                       → Liste (filtres: search, role, actif)
POST   /api/admin/users                       → Créer (role, telephone ; cree_par auto)
PUT    /api/admin/users/:id                    → Modifier (role, actif, telephone…)
DELETE /api/admin/users/:id                    → Supprimer
POST   /api/admin/users/:id/statut            → Activer/désactiver { actif }
POST   /api/admin/users/:id/reset-password    → Réinitialise → { password } temporaire
```
> Toute action touchant un SUPER_ADMIN exige `superadmins.gerer`. Le dernier SUPER_ADMIN
> actif ne peut être supprimé/désactivé/rétrogradé ; on ne se désactive/supprime pas soi-même.
> Autorisation par middleware `permission:{cle}` (Gates + `Gate::before` super admin).
> Matrice par défaut : `config/profils.php` ; matrice EFFECTIVE éditable à chaud (cf. ci-dessous).
> SUPER_ADMIN initial paramétrable via `.env` (`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`).

### Matrice des permissions — RBAC flexible (éditable à chaud)
```
GET    /api/admin/roles                        → Matrice effective + catalogue des clés (utilisateurs.gerer)
PUT    /api/admin/roles/:role/permissions      → Remplace les permissions d'un profil (roles.gerer)
DELETE /api/admin/roles/:role/permissions      → Réinitialise le profil au défaut config (roles.gerer)
GET    /api/admin/users/:id/permissions        → Overrides d'un utilisateur : base, accords, retraits, effectif (utilisateurs.gerer)
PUT    /api/admin/users/:id/permissions        → Remplace les overrides d'un utilisateur (roles.gerer)
POST   /api/admin/roles                        → Crée un rôle personnalisé (code, libelle, permissions?) (roles.gerer)
PUT    /api/admin/roles/:role                  → Renomme un rôle personnalisé (roles.gerer)
DELETE /api/admin/roles/:role                  → Supprime un rôle personnalisé (roles.gerer)
```
> **Niveau 1 (par profil)** : source de vérité vivante table `role_permissions` (via
> `App\Support\PermissionRepository`, en cache, purgée à l'écriture), repli sur `config/profils.php`.
> **Niveau 2 (par utilisateur)** : table `user_permissions` — un utilisateur peut se voir ACCORDER
> une permission en plus de son profil ou en RETIRER une (`App\Services\UserPermissionService`).
> `permissions_effectives = base_du_profil ∪ accordées − retirées` (`User::permissions()`, mémoïsé).
> **Niveau 3 (rôles personnalisés)** : table `roles` — création de profils au-delà des 8 système
> (`systeme=false`) ; leurs permissions vivent dans `role_permissions`. Les 8 profils système restent
> définis dans le code (sémantique du circuit) et ne sont ni renommables ni supprimables. `users.role`
> est une chaîne libre ; les codes valides viennent de `PermissionRepository::codes()`.
> Écriture réservée au SUPER_ADMIN (`roles.gerer`, non attribuable) ; lecture `utilisateurs.gerer`.
> Garde-fous serveur (tous niveaux) : SUPER_ADMIN non éditable, clés réservées refusées,
> séparation ordonnateur/payeur (`om.approuver` ≠ `paiements.generer/confirmer`, contrôlée aussi sur
> l'effectif de chaque membre d'un profil édité), code de rôle
> unique et non-système, suppression bloquée si des utilisateurs y sont rattachés. Audité
> (`ROLE_PERM_UPDATE` / `ROLE_PERM_RESET` / `USER_PERM_UPDATE` / `ROLE_CREATE` / `ROLE_RENAME` /
> `ROLE_DELETE`). Détail : `docs/rbac-flexible.md`.

### Paramètres système
```
GET    /api/admin/settings                    → Réglages (SUPER_ADMIN : parametres.gerer ; secret SMTP masqué)
PUT    /api/admin/settings                    → Écrit une liste blanche de clés (dont partAvance, entier 0-100)
GET    /api/admin/parametres/part-avance      → { data: <int> } Part de l'avance (LECTURE PUBLIQUE, tout authentifié)
GET    /api/admin/parametres/annee-fiscale    → { data: { annee, mois_debut, libelle } } Exercice courant (LECTURE PUBLIQUE)
GET    /api/admin/signatures                  → Signataires du circuit (nom, fonction ; l'image n'est jamais renvoyée) (parametres.gerer)
POST   /api/admin/signatures/:poste           → Téléverse le paraphe d'un poste (controleur|daf|dg), multipart (parametres.gerer)
DELETE /api/admin/signatures/:poste           → Retire le paraphe (parametres.gerer)
GET    /api/admin/backups                     → Sauvegardes .sql/.sql.gz (parametres.gerer)
POST   /api/admin/backups                     → Crée une sauvegarde (artisan db:backup)
GET    /api/admin/backups/:name/download      → Télécharge une sauvegarde
POST   /api/admin/backups/:name/restaurer     → Restaure (artisan db:restore) — body { confirmation: <nom du fichier> } ;
                                                sauvegarde de sécurité de l'état courant prise juste avant ; purge la matrice
                                                des permissions en cache ; audit BACKUP_RESTORE
DELETE /api/admin/backups/:name               → Supprime une sauvegarde
```
> `partAvance` (défaut 70) définit la répartition avance/reliquat : `avance = round(montant × partAvance/100)`,
> le reliquat absorbe l'arrondi. Écriture réservée au SUPER_ADMIN via `/settings` ; **lecture par la route
> publique dédiée** `parametres/part-avance` (l'UI la lit pour tous les profils — ne pas utiliser `/settings`,
> qui exige `parametres.gerer`). Appliqué au calcul à la retranscription des OM (`CalculIndemniteService`).
> Détail : `docs/parametrage-part-avance.md`.
>
> **Année fiscale / exercice** (flexible, `App\Support\Parametres`) : `fiscalYear` (année, 2000-2100) et
> `fiscalYearStartMonth` (mois de début, 1-12 ; défaut 1 = année civile). L'exercice d'une date se
> **détermine** via `Parametres::anneeFiscalePour($date)` (étiqueté par l'année de début) ; `anneeFiscale()`
> renvoie la valeur configurée ou, à défaut, l'exercice courant calculé ; `libelleAnneeFiscale()` produit
> « 2026 » ou « 2026-2027 » (exercice à cheval). Écriture SUPER_ADMIN via `/settings` (validées, mises en
> cache 60 s, invalidées à l'écriture) ; **lecture publique** via `parametres/annee-fiscale`. Le sélecteur
> d'année de l'UI est dynamique (plus de liste figée) ; tant qu'aucune année n'est choisie explicitement, changer le
> mois de début recalcule l'exercice courant (`utils/exercice.ts` → `exerciceDe`). **À ce jour, aucun écran ni traitement ne consomme
> l'exercice** (rapports et exports filtrent par dates). Détail : `docs/annee-fiscale.md`.
>
> **Hors sauvegarde de la base** : `storage/app/settings.json` (réglages ci-dessus, signataires), les paraphes
> (`storage/app/private/signatures/`) et les fichiers produits (PDF, paiements) vivent sur le disque ; `db:backup`
> ne les inclut pas et `db:restore` ne les modifie pas — ils se sauvegardent avec `storage/`.

### Agents
```
GET    /api/agents                  → { data: Agent[], meta } (filtres: search, categorie, operateur, actif)
GET    /api/agents/recherche        → ?q=...  autocomplétion (nom/matricule/IFU)
GET    /api/agents/stats            → compteurs globaux (mêmes filtres que la liste) pour les cartes de l'écran Personnel
POST   /api/agents                  → Créer (catégorie déduite, cohérence numéro/opérateur)
GET    /api/agents/:id              → Agent + ordres de mission
PUT    /api/agents/:id              → Modifier agent
DELETE /api/agents/:id              → Désactiver agent
POST   /api/agents/import           → Import Excel/CSV (rapport d'erreurs ligne à ligne, rollback)
```
> Écriture/import : rôle `gestionnaire_om` (`personnel.gerer`). Lecture : `personnel.consulter` (le profil `LECTEUR` ne l'a pas).
> Un même agent ne figure qu'une fois par mission (`ordres.*.agent_id` distinct).

### Missions & Ordres de Mission
```
POST   /api/missions                → Retranscription : crée la mission + N OM (rôle gestionnaire_om)
                                       Body: { objet, destination, zone_code?, dates, prise_en_charge_tiers,
                                               ordres:[{ reference_semo, agent_id, role, type_prise_en_charge,
                                                         categorie?, nb_jours? }] }
GET    /api/ordres                  → Liste filtrée (statut, zone, agent_id, operateur, dates, search) — operateur = celui des VERSEMENTS (repli : agent si aucun versement)
GET    /api/ordres/:id              → Détail OM (mission, agent, versements)
POST   /api/ordres/:id/valider      → IMPORTE → VALIDE (rôle controleur : om.valider)
POST   /api/ordres/:id/rejeter      → IMPORTE → REJETE (motif ≥ 10 chars ; om.rejeter) — versements EN_ATTENTE → ANNULE
POST   /api/ordres/:id/amender      → Amende les jours facturés (avenant). Body: { nb_jours? , lignes? , motif }
                                       Avance figée → seul le reliquat bouge (peut devenir négatif). Rôle gestionnaire_om : om.retranscrire
GET    /api/ordres/:id/pdf          → Génère le PDF de l'OM → { url, fichier } (signatures reprises de l'état des avances)
GET    /api/ordres/:id/pdf/download → Télécharge le PDF
GET    /api/ordres/:id/pdf/brouillon → PDF filigrané « brouillon » de l'OM
GET    /api/ordres/:id/historique   → Journal d'audit de l'OM
```
> Les OM ne sont ni créés ni modifiés individuellement (retranscrits via /missions). Pas de PUT/DELETE.
> Seul l'**amendement** (avenant) ajuste un OM après retranscription — cf. règle « Amendement d'un OM » ci-dessus.

### États de paiement (circuit par tranche : avances puis reliquats)
```
GET    /api/missions                      → Missions + avancement des deux états (avances / reliquats) — per_page 5-100, défaut 20
GET    /api/missions/:id/etat             → { data: { avance: EtatPaiement|null, reliquat: EtatPaiement|null } }
                                            (data: null si aucun récapitulatif établi)
POST   /api/missions/:id/etat/recapituler → Établit/rafraîchit l'état d'une tranche en BROUILLON
                                            Body: { tranche?: 'AVANCE'|'RELIQUAT' } (défaut AVANCE)
                                            RELIQUAT : 422 tant que l'état des avances n'est pas VALIDE
GET    /api/missions/:id/etat/brouillon   → PDF filigrané de la tranche (?tranche=, défaut AVANCE)
GET    /api/missions/:id/etat/telecharger → PDF de la tranche (?tranche=) — filigrané tant que non validé
GET    /api/missions/:id/etat/final       → État général consolidé (avances + reliquats) — 422 tant que les DEUX tranches ne sont pas VALIDE
GET    /api/etats                         → File de travail (filtres: statut, tranche, search) — per_page 5-100, défaut 20
PUT    /api/etats/:id/numero              → Numéro de registre (unique ; figé à la validation finale)
POST   /api/etats/:id/controler           → BROUILLON → CONTROLE (om.valider)
POST   /api/etats/:id/viser               → CONTROLE → VISA_DAF (om.viser)
POST   /api/etats/:id/approuver           → VISA_DAF → VALIDE (om.approuver ; exige numero)
POST   /api/etats/:id/rejeter             → → REJETE (motif ≥ 10 chars ; efface les signatures amont)
```
> La validation de l'état des AVANCES propage `VALIDE` aux OM et ouvre les fichiers avance ;
> celle de l'état des RELIQUATS ouvre les fichiers reliquat. Un versement n'est éligible que
> si l'état DE SA TRANCHE est validé (missions sans état : périmètre par OM, reprise de données).

### Paiements (mode GÉNÉRATION — rôle agent_financier)
```
GET    /api/paiements/lots                  → Liste des lots (filtres mission/tranche/operateur/statut) ; chaque lot expose `sequence`
GET    /api/paiements/lots/previsualiser    → ?mission_id&tranche&operateur → bénéficiaires éligibles
POST   /api/paiements/lots/generer          → Génère le fichier (.txt MTN / .xlsx Moov), lot → GENERE
                                              petit lot : téléchargement direct ; gros lot : 202 + tracking_id (file Redis)
GET    /api/paiements/lots/:lot/download    → Re-télécharge le fichier du lot
POST   /api/paiements/lots/:lot/confirmer   → Lot + versements → CONFIRME, horodatés
POST   /api/paiements/lots/:lot/erreur      → Lot → ERREUR (autorise régénération) ; versements → EN_ATTENTE, coordonnées agent rafraîchies
GET    /api/paiements/lots/job/:trackingId  → Suivi d'une génération asynchrone
GET    /api/paiements/statuts               → Synthèse versements par opérateur/statut
GET    /api/paiements/historique            → Versements CONFIRMÉS filtrables (date_paiement)
GET    /api/paiements/regularisations       → Trop-perçus (A_RECOUVRER/RECOUVRE), PAGINÉ ; filtres statut, mission_id, search ; + total_a_recouvrer global ; montant = |reliquat| ; chaque ligne porte ordre_mission_id (lien vers la fiche OM pour amender) (paiements.consulter)
GET    /api/paiements/regularisations/:versement/document → PDF « document de retenue sur salaire » (RH) (paiements.consulter)
POST   /api/paiements/regularisations/:versement/recouvrer → A_RECOUVRER → RECOUVRE (constat manuel de la retenue RH ; paiements.confirmer)
```
> Idempotence : un versement CONFIRME n'est jamais réinclus. Aucun réimport, aucune API opérateur.
> **Régularisations** : un trop-perçu (reliquat négatif après amendement) ne part jamais en Mobile Money ;
> il est récupéré par retenue sur salaire (hors application). Cf. règle « Amendement d'un OM » et `docs/amendement-om.md`.

### Barème (per diem)
```
GET    /api/baremes                 → Liste du barème (baremes.consulter — le profil LECTEUR ne l'a pas)
GET    /api/baremes/:id             → Détail
POST   /api/baremes                 → Créer (rôle admin ; contrainte type↔zone + unicité)
PUT    /api/baremes/:id             → Modifier le taux journalier
DELETE /api/baremes/:id             → Supprimer
GET    /api/taux                    → Alias rétro-compatible (= /api/baremes en lecture)
```

### Rapports (états alimentés par les versements confirmés)
```
GET    /api/rapports/mensuel        → ?mois=2026-01  → synthèse + par opérateur/tranche/statut
GET    /api/rapports/agents         → état par agent (filtres période)
GET    /api/rapports/operateurs     → état par opérateur
GET    /api/rapports/zones          → état par zone
GET    /api/rapports/imputations    → état par imputation budgétaire
GET    /api/rapports/trop-percus    → synthèse des trop-perçus (régularisations) : à recouvrer vs recouvrés (montants absolus)
GET    /api/rapports/export         → ?dimension&format=xlsx|pdf&date_debut&date_fin → { fichier, url }
GET    /api/rapports/export/download → ?fichier=... → téléchargement
POST   /api/rapports/export-async   → Production en file Redis → { tracking_id }
GET    /api/rapports/export/job/:id  → Suivi d'un export asynchrone
```

### Audit
```
GET    /api/audit                   → Journal activité (filtres: action, user, date)
GET    /api/audit/:id               → Détail action
```

---

## 8. RÈGLES MÉTIER

### Calcul de l'indemnité (CalculIndemniteService)

```php
// nb_jours : règle des 12 h (période ≥ 12 h = jour entier). Dates seules => décompte inclusif.
$nbJours = abs(Carbon::parse($depart)->diffInDays(Carbon::parse($retour))) + 1;

// Taux depuis le barème officiel (catégorie × zone × type) :
$taux = Bareme::taux($categorie, $zoneCode, $type);   // null => erreur de validation

// Répartition détaillée possible : Σ (taux(type) × jours) sur plusieurs types + transport.
$montantTotal = $taux * $nbJours + $montantTransport;
$avance       = (int) round($montantTotal * Parametres::partAvance() / 100); // 70 % par défaut
$reliquat     = $montantTotal - $avance;               // le reliquat absorbe l'arrondi

// Exonération : si mission.prise_en_charge_tiers => montants à 0 (transport compris), aucun versement.
// Sans aucun jour facturé (hors exonération) : per diem nul, mais le transport reste dû.
// Après amendement d'un OM dont l'avance est engagée : reliquat = total − avance_engagee (peut être < 0).
```

### Format fichier virement MTN (.txt) — exact, validé

```
HDR,SARALY,1,45000
1,MSISDN,2290167267455,45000,DADA RICHARD ATELIER REGIONAL
```
- Séparateur **virgule**, fins de ligne **CRLF**, saut de ligne final, ASCII.
- En-tête `HDR,{COMPTE},{NB_LIGNES},{MONTANT_TOTAL}` (compte en config). Détail `{seq},MSISDN,{229+tel},{montant},{libellé MAJUSCULES}`.

### Format fichier virement Moov (.xlsx) — exact, validé

- Feuille `Sheet1`, en-tête 9 colonnes : `Receiver Identifier Type | IdentifierValue | Validation KYC | Validation KYC value | PayerAccountName | AccountName | Amount | Comment | VoucherSecretCode`.
- Par bénéficiaire : A=`MSISDN`, B=`229`+téléphone **en TEXTE**, C-F vides, G=montant **numérique**, H=libellé MAJUSCULES, I vide.

### Règles de validation

- Les OM sont **retranscrits** depuis SEMO (statut initial `IMPORTE`) — jamais créés/modifiés à la main.
- `reference_semo` est **UNIQUE** (anti-doublon) ; rejet clair en cas de collision.
- Validation : `IMPORTE → VALIDE` (rôle `controleur`, permission `om.valider`). Rejet : `IMPORTE → REJETE` avec motif ≥ 10 chars.
- Contrainte type↔zone : repas (`UN_REPAS`/`DEUX_REPAS`) ⇒ INTERIEUR ; hébergement (`LOGE_*`/`NOURRI_SEUL`) ⇒ EXTÉRIEUR.
- Téléphone/opérateur viennent du **référentiel agent** ; cohérence numéro↔opérateur validée.
- Paiement : cycle `EN_ATTENTE → GENERE → CONFIRME`. Confirmation **manuelle** après dépôt. Régénération tant que non confirmé ; **idempotence** (un versement CONFIRME, ou porté par un lot CONFIRME, n'est jamais réinclus ; un lot CONFIRME n'est jamais réécrit → lot complémentaire).
- Un même agent ne figure qu'**une fois** par mission (`ordres.*.agent_id` distinct).
- Rejet d'un OM : ses versements `EN_ATTENTE` passent `ANNULE` ; la validation d'un état ne repropage jamais `VALIDE` à un OM `REJETE`.
- Solde : un OM passe `SOLDE` quand tous ses versements non nuls sont `CONFIRME` ou `RECOUVRE` (confirmation d'un lot,
  constat d'un recouvrement) ; un OM exonéré est soldé à sa validation (état des avances ou validation par OM).
- Amendement : refusé si le reliquat de l'OM est déjà `CONFIRME` (avenant formel), en plus des gardes du §1 ;
  le statut suit ce qui reste à régler — reliquat ramené à 0 (avance payée) → `SOLDE` ; OM soldé à qui
  l'amendement recrée un montant à régler → `VALIDE`.

---

## 9. CONVENTIONS DE CODE

### Vue 3 — structure SFC

```vue
<script setup lang="ts">
// 1. Imports
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import type { OrdreMission } from '@/types/mission'
import { useApi } from '@/composables/useApi'

// 2. Props & Emits
const props = defineProps<{ id?: number }>()
const emit = defineEmits<{ (e: 'saved', id: number): void }>()

// 3. State réactif
const missions = ref<OrdreMission[]>([])
const loading = ref(false)

// 4. Computed
const missionsFiltrees = computed(() => missions.value.filter(m => m.statut !== 'REJETE'))

// 5. Méthodes
async function chargerMissions() {
  loading.value = true
  try {
    const { data } = await useApi().get('/api/ordres')
    missions.value = data.data
  } finally {
    loading.value = false
  }
}

// 6. Lifecycle
onMounted(chargerMissions)
</script>

<template>
  <!-- Template ici -->
</template>
```

### Laravel — Controller structure

```php
<?php
// Toujours: Form Requests pour la validation
// Toujours: Resources pour la transformation
// Toujours: Services pour la logique métier

public function store(CreateOrdreMissionRequest $request): JsonResponse
{
    $ordre = $this->missionService->creer(
        $request->validated(),
        auth()->user()
    );

    return response()->json([
        'data'    => new OrdreMissionResource($ordre),
        'message' => 'Ordre de mission créé avec succès'
    ], 201);
}
```

### Nommage

| Type | Convention | Exemple |
|------|-----------|---------|
| Composants Vue | PascalCase | `MissionDetailView.vue` |
| Composables | camelCase avec `use` | `useMissions.ts` |
| Routes API | kebab-case | `/api/ordres-mission` |
| Models Laravel | PascalCase singulier | `OrdreMission.php` |
| Migrations | snake_case timestampé | `2026_01_01_create_ordres_table.php` |
| Variables PHP | camelCase | `$montantPerDiem` |
| Constantes | UPPER_SNAKE | `STATUT_APPROUVE` |

---

## 10. COMMANDES UTILES

```bash
# Frontend
cd frontend
npm ci                   # installe la version figée par package-lock.json (Vue 3.6 bêta)
npm run dev              # http://localhost:5173
npm run build            # type-check + build
npm run test:unit -- --run
npm run lint:check       # ESLint + oxlint (vérification)
npm run test:e2e         # Playwright (PLAYWRIGHT_CHROMIUM_EXECUTABLE pour un Chromium déjà installé ;
                         #  sans écran : binaire headless_shell + --project=chromium)

# Backend
cd backend
composer install
php artisan migrate --seed
php artisan serve        # http://localhost:8000
php artisan queue:work --queue=paiements,etats,default --tries=3   # Jobs en arrière-plan
./vendor/bin/pest        # Tests (aucun .env requis)
./vendor/bin/pint --test # Style (vérification)
php artisan schedule:run # cron : db:backup 02:00, purge des tokens
php artisan db:backup --compress
php artisan db:restore <fichier> --force   # sauvegarde de sécurité prise avant

# Windows : tout l'environnement de dev
.\start-dev.ps1 [-Install -Fresh]   /   .\stop-dev.ps1

# Génération de code Laravel
php artisan make:model OrdreMission -mcr   # Model + migration + controller
php artisan make:request CreateOrdreMissionRequest
php artisan make:resource OrdreMissionResource
php artisan make:job GeneratePaiementFile
php artisan make:class Services/MissionService   # classe de service (Laravel 11+)
```

---

## 11. GOOGLE FONTS (à inclure dans index.html)

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
```

---

## 12. VARIABLES D'ENVIRONNEMENT

```env
# frontend/.env
VITE_API_URL=http://localhost:8000
VITE_APP_NAME=ANaTT Missions
VITE_APP_VERSION=1.0.0

# backend/.env
APP_NAME="ANaTT Gestion Missions"
APP_URL=http://localhost:8000
APP_TIMEZONE=Africa/Porto-Novo   # heure du Bénin (défaut) ; dates de l'API toujours en UTC
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anatt_missions
DB_USERNAME=root
DB_PASSWORD=
DB_DUMP_BINARY=mysqldump   # utilisé par db:backup
DB_RESTORE_BINARY=mysql    # utilisé par db:restore

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@anatt.bj
MAIL_FROM_NAME="ANaTT Missions"

REDIS_HOST=127.0.0.1      # facultatif
REDIS_PORT=6379

# Fichiers de paiement (config/paiement.php)
MTN_COMPTE=SARALY          # compte payeur de l'en-tête MTN : HDR,{COMPTE},…
PAIEMENT_ASYNC_SEUIL=50    # au-delà, génération en file (202 + tracking_id)
```

> Aucune variable d'API Mobile Money : conformément à la règle d'or, l'application ne
> s'adresse jamais aux opérateurs (fichiers déposés à la main). En production, partir de
> `backend/.env.production.example` (voir `DEPLOY-CPANEL.md` §5.1).

---

*Ce fichier est maintenu par l'équipe développement ANaTT.*  
*Dernière mise à jour : 23 octobre 2026 (stack et règles confrontées au code : Vue 3.6 bêta, PHP 8.4.x, solde et
amendement après les correctifs n° 17 à 22) — précédente : 22 octobre 2026 (revue complète : intégrité des paiements, lots complémentaires, état des reliquats payable, restauration des sauvegardes ; puis PDF de l'OM signé par l'état, purge de la matrice à la restauration — cf. `docs/revue-projet-2026-10.md`)*  
*Version projet : 1.0.0*