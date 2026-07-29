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
→ calcul per diem (barème ANaTT) → RÉCAPITULATIF : état de paiement (BROUILLON)
→ CONTRÔLE (contrôleur) → VISA DAF → VALIDATION DG (numéro requis)  ← circuit
→ les OM de la mission deviennent payables (VALIDE)
→ génération fichier de paiement (.txt MTN / .xlsx Moov), AVANCE puis RELIQUAT
→ dépôt manuel sur la plateforme opérateur → confirmation manuelle dans l'app
```

### Circuit de validation sur l'ÉTAT DE PAIEMENT (cœur du workflow)

L'unité validée est l'**état de paiement de la mission** (un seul par mission), **pas** les OM pris un à un.
Un seul geste par étape vaut pour tous les bénéficiaires de la mission.

```
récapitulatif ─▶ BROUILLON ──contrôle──▶ CONTROLE ──visa DAF──▶ VISA_DAF ──validation DG──▶ VALIDE
                    │  (om.valider)          (om.viser)             (om.approuver, numéro requis)
                    └── rejet possible à chaque étape (om.rejeter, motif ≥ 10) ─▶ REJETE ─▶ refait
```

- **Les OM gardent leur statut SEMO (`IMPORTE`)** pendant tout le circuit ; ils ne passent `VALIDE` (payables)
  qu'à la **validation finale du DG**, par propagation depuis l'état. Le contrôle, le visa et le rejet ne
  touchent **pas** au statut des OM.
- **Double barrière avant paiement** : (1) un OM n'est `VALIDE` qu'après le DG ; (2) la génération d'un fichier
  vérifie en plus que l'état de la mission est `VALIDE`.
- **Numéro** de l'état : saisi **manuellement** (registre de l'agence, `om.numeroter`), requis avant la
  validation DG, puis figé. Unicité vérifiée.
- **PDF** : brouillon filigrané « BROUILLON — NON VALIDE » **sans signature** ; état **définitif** signé
  (visa DAF + validation DG) au format **paysage**. Toujours régénéré à la demande.
- **Signatures** des titulaires (contrôleur/DAF/DG : nom, prénoms, fonction, paraphe scanné) configurées dans
  **Administration → Signatures** ; ce sont les titulaires en poste, indépendamment du compte qui valide
  (l'auteur réel reste tracé en audit).
- **Rejet** : efface les signatures amont, remet l'état en correction (`REJETE`), sans figer les OM ; le
  récapitulatif peut être **refait** (`REJETE → BROUILLON`).
- **Circuit OM par OM** (`/api/ordres/:id/valider|viser|approuver|rejeter`) : conservé pour la **reprise de
  données** sur une mission **sans** état ; refusé dès qu'un état existe.
- Statuts de l'état : `BROUILLON | CONTROLE | VISA_DAF | VALIDE | REJETE`. Le circuit vit dans
  `EtatPaiementService` ; barrière de génération dans `Paiement/FileGenerator::exigerEtatValide`.

### Règles métier (référence pour toutes les tâches)

- **1 OM SEMO = 1 enregistrement = 1 bénéficiaire** (mono-bénéficiaire).
- Une **mission** (événement) regroupe **N OM individuels** (participants + chauffeurs).
- Le **CAV** (conducteur de véhicule administratif) est un **bénéficiaire à part entière**, payé selon **SA** catégorie. Le champ « conducteur à bord » sur l'OM d'un participant n'est qu'une **mention logistique**.
- **Catégorie d'indice** reprise telle quelle de SEMO :
  - `ORDRE_1` : indice ≥ 800 — salaire brut ≥ 372 000
  - `ORDRE_2` : indice 400-799 — salaire brut 186 000-372 000
  - `ORDRE_3` : indice < 400 — salaire brut < 186 000
  - Formule de secours : `indice = salaire_base × 12 / 3097`.
- **Per diem** = taux journalier (catégorie × **ZONE** × **type de prise en charge**) × nb jours.
  - 4 zones : `INTERIEUR`, `EXT_ZONE_I`, `EXT_ZONE_II`, `EXT_ZONE_III` (**II = III**, taux identiques).
  - Types `INTERIEUR` : `UN_REPAS` / `DEUX_REPAS` / `JOURNEE_COMPLETE`.
  - Types `EXTERIEUR` : `JOURNEE_COMPLETE` / `LOGE_NOURRI` / `LOGE_SEUL` / `NOURRI_SEUL`.
  - **Règle des 12 h** pour le nb de jours (période ≥ 12 h = jour entier ; retour 0-12 h = rien).
  - Répartition : **AVANCE 70 %** / **RELIQUAT 30 %**.
  - **Exonération** si prise en charge par un organisme tiers (aucun frais alloué).
- **Téléphone et opérateur** (MTN / Moov) viennent du **référentiel agents**, pas de l'OM.
- **Référence SEMO** = clé naturelle **UNIQUE** (anti-doublon).
- **Paiements** : GÉNÉRATION de fichiers — un fichier = **1 mission × 1 tranche [`AVANCE` | `RELIQUAT`] × 1 opérateur**.
  - MTN → `.txt` ; Moov → `.xlsx`.
  - Statuts versement : `EN_ATTENTE` / `GENERE` / `CONFIRME` / `ANNULE`.
  - Confirmation **manuelle** après dépôt réussi. Régénération possible tant que **non confirmé**.
  - **Idempotence** : un versement `CONFIRME` n'est **jamais** réinclus dans un nouveau fichier (anti-double-paiement).

### Profils utilisateurs (RBAC Sanctum par PERMISSION — middleware `can:`)

8 profils, autorisation par **clé de permission** (Gates définies dans `AuthServiceProvider`, source : `App\Support\Permissions`).

| Profil | Rôle |
|--------|------|
| `SUPER_ADMIN` | Accès **total** (Gate::before). Seul à gérer les super admins et les paramètres système (dont les signatures). Créé au seed. |
| `ADMIN` | Utilisateurs (sauf super admins), référentiels, barème, audit ; supervise sans exécuter les paiements |
| `GESTIONNAIRE_OM` | Retranscrit/gère missions, OM et agents (`om.retranscrire`, `personnel.gerer`) ; établit le récapitulatif de l'état |
| `AGENT_FINANCIER` | Génère les fichiers MTN/Moov et confirme les paiements (`paiements.generer/confirmer`) |
| `CONTROLEUR` | **Étape 1** du circuit : contrôle l'état de paiement (`om.valider`) ; consulte paiements et audit |
| `DAF` | **Étape 2** : appose le visa « Vu » sur l'état (`om.viser`) — Directeur de l'Administration et des Finances |
| `DG` | **Étape 3** (dernière) : valide définitivement l'état (`om.approuver`), ce qui ouvre le paiement — Directeur Général |
| `LECTEUR` | Lecture seule (dashboard, OM, rapports) |

> **Séparation ordonnateur/payeur.** Le DAF et le DG **autorisent**, l'agent financier **exécute** : ni le DAF
> ni le DG ne détiennent `paiements.generer/confirmer`. Une même personne ne peut porter le visa DAF **et**
> la validation finale d'un même état (`separation_des_taches`). `om.numeroter` et `om.rejeter` sont partagés
> par les trois acteurs du circuit (contrôleur, DAF, DG).

Clés de permission : `dashboard.consulter` · `personnel.consulter|gerer` · `om.consulter|retranscrire|valider|numeroter|viser|approuver|rejeter` · `paiements.consulter|generer|confirmer` · `baremes.consulter|modifier` · `rapports.consulter` · `audit.consulter` · `utilisateurs.gerer` · `superadmins.gerer` · `parametres.gerer`.

> **Cohérence menu ↔ routes ↔ API.** Chaque entrée du menu latéral
> (`components/shared/navigation.ts`) porte la **même** clé que le `meta.permission`
> de sa route (`router/index.ts`), qui porte elle-même la clé exigée par l'API.
> Les trois niveaux sont verrouillés par des tests : `tests/Feature/RoutesPermissionsTest.php`
> (aucune route API sans permission) et `src/__tests__/navigation.spec.ts`
> (aucun lien mort, aucun écran hors menu, aucun 403 après un clic).

> **Super admin** : accès implicite à tout ; il existe toujours ≥ 1 SUPER_ADMIN actif (le dernier ne peut être supprimé, désactivé ni rétrogradé). Comptes de démo (mdp `password`, `UserSeeder`, local/testing uniquement) : `superadmin@`, `admin@`, `gestion@`, `finance@`, `controle@`, `daf@`, `dg@`, `lecteur@anatt.bj`.
>
> **En production**, les comptes du circuit se créent via `ComptesCircuitSeeder` (mot de passe temporaire aléatoire, changement forcé à la 1ʳᵉ connexion), ou depuis l'écran **Administration → Utilisateurs** (profils DAF et DG disponibles dans le formulaire). Pour un essai complet du circuit en local : `php artisan db:seed --class=DemoCircuitSeeder` (refusé en production).

---

## 2. STACK TECHNIQUE

### Frontend
- **Framework** : Vue.js 3 (Composition API) + TypeScript strict
- **Build** : Vite 5
- **CSS** : Tailwind CSS 3.4+ (config personnalisée ANaTT)
- **State** : Pinia
- **Router** : Vue Router 4 (guards d'authentification)
- **HTTP** : Axios avec intercepteurs (token Bearer)
- **Graphiques** : Chart.js 4
- **PDF viewer** : VuePDF
- **Tests** : Vitest + Vue Test Utils

### Backend
- **Framework** : Laravel 11
- **PHP** : 8.3+
- **Auth** : Laravel Sanctum (SPA tokens)
- **BDD** : MySQL 8 (production) / SQLite (tests)
- **Cache/Queue** : Redis
- **PDF** : DomPDF (Laravel-dompdf)
- **Excel** : Maatwebsite/Laravel-Excel 3.1
- **Tests** : Pest PHP + PHPUnit

### DevOps
- Docker + Docker Compose
- Nginx
- GitHub Actions (CI/CD)
- Certbot (SSL)

---

## 3. STRUCTURE DU PROJET

```
anatt-ordre-mission/
├── CLAUDE.md                    ← CE FICHIER
├── docker-compose.yml
├── frontend/                    ← Application Vue 3
│   ├── src/
│   │   ├── assets/
│   │   │   ├── logo-anatt.png        ← Logo officiel couleur (fonds clairs)
│   │   │   ├── logo-anatt-white.png  ← Logo blanc, barre tricolore conservée (fonds sombres)
│   │   │   └── icon-anatt.png         ← Marque carrée (vert ANaTT + ANaTT blanc) — base favicon
│   │   ├── components/
│   │   │   ├── shared/         ← Composants réutilisables
│   │   │   │   ├── AppLayout.vue
│   │   │   │   ├── AppSidebar.vue
│   │   │   │   ├── AppTopBar.vue
│   │   │   │   ├── AppButton.vue
│   │   │   │   ├── AppCard.vue
│   │   │   │   ├── AppBadge.vue
│   │   │   │   ├── AppTable.vue
│   │   │   │   ├── AppModal.vue
│   │   │   │   ├── AppAlert.vue
│   │   │   │   └── AppPagination.vue
│   │   │   ├── auth/
│   │   │   ├── dashboard/
│   │   │   ├── agents/
│   │   │   ├── missions/
│   │   │   ├── paiements/
│   │   │   ├── taux/
│   │   │   ├── rapports/
│   │   │   ├── audit/
│   │   │   └── admin/
│   │   ├── composables/
│   │   │   ├── useApi.ts
│   │   │   ├── useAuth.ts
│   │   │   ├── useMissions.ts
│   │   │   ├── usePaiements.ts
│   │   │   └── useToast.ts
│   │   ├── router/
│   │   │   └── index.ts
│   │   ├── stores/
│   │   │   ├── auth.ts
│   │   │   ├── missions.ts
│   │   │   └── notifications.ts
│   │   ├── types/
│   │   │   ├── agent.ts
│   │   │   ├── mission.ts
│   │   │   └── paiement.ts
│   │   ├── views/              ← Pages (42 écrans)
│   │   └── main.ts
│   ├── tailwind.config.ts
│   └── vite.config.ts
│
├── backend/                     ← API Laravel 11
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── AgentController.php
│   │   │   ├── MissionController.php      ← missions + OM (index/show/storeMission/pdf)
│   │   │   ├── ValidationController.php   ← valider / rejeter
│   │   │   ├── PaiementController.php     ← lots (génération/confirmation)
│   │   │   ├── BaremeController.php
│   │   │   ├── RapportController.php
│   │   │   └── AuditController.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Agent.php
│   │   │   ├── Mission.php
│   │   │   ├── OrdreMission.php
│   │   │   ├── Versement.php
│   │   │   ├── LotPaiement.php
│   │   │   ├── Bareme.php
│   │   │   ├── ZoneGeographique.php
│   │   │   └── AuditLog.php
│   │   ├── Services/
│   │   │   ├── MissionService.php
│   │   │   ├── CalculIndemniteService.php
│   │   │   ├── LotPaiementService.php
│   │   │   ├── BaremeService.php
│   │   │   ├── RapportService.php
│   │   │   ├── AuditService.php
│   │   │   ├── PdfGenerator.php
│   │   │   └── Paiement/
│   │   │       ├── MtnFileGenerator.php   ← .txt
│   │   │       └── MoovFileGenerator.php  ← .xlsx (PhpSpreadsheet)
│   │   └── Jobs/
│   │       ├── GenererLotPaiementJob.php  ← file Redis « paiements »
│   │       └── GenererEtatJob.php         ← file Redis « etats »
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/
│       └── api.php
│
└── docs/                        ← Documentation
    ├── manuel-utilisateur.docx
    ├── specs-techniques.pdf
    └── architecture.png
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

## 5. REGISTRE DES 47 ÉCRANS

### Module AUTH (4 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| AUTH-01 | `views/auth/LoginView.vue` | `/login` | Formulaire email + mot de passe, logo ANaTT centré |
| AUTH-02 | `views/auth/ForgotPasswordView.vue` | `/mot-de-passe-oublie` | Saisie email, envoi lien reset |
| AUTH-03 | `views/auth/ResetPasswordView.vue` | `/reinitialiser/:token` | Nouveau mot de passe + confirmation |
| AUTH-04 | `views/auth/ForcePasswordChangeView.vue` | `/changer-mot-de-passe` | Changement imposé à la 1ʳᵉ connexion (création/réinit. admin) ; bloque l'API tant qu'il n'est pas fait |

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
| OM-03 | `views/missions/MissionDetailView.vue` | `/ordres/:id` | Détail complet + versements ; renvoie vers l'état de la mission (pas de validation sur l'OM) |
| OM-04 | `views/missions/MissionEditView.vue` | `/ordres/:id/modifier` | Redirige vers la fiche (OM non éditables individuellement) |
| OM-05 | `views/missions/MissionValidation1View.vue` | `/ordres/:id/valider-n1` | Redirection (validation unifiée sur la fiche) |
| OM-06 | `views/missions/MissionValidation2View.vue` | `/ordres/:id/valider-dg` | Redirection (plus de double validation N1/DG) |
| OM-07 | `views/missions/MissionPdfView.vue` | `/ordres/:id/pdf` | Aperçu imprimable + téléchargement du PDF serveur |
| OM-08 | `views/missions/MissionHistoryView.vue` | `/ordres/:id/historique` | Journal d'audit de l'OM |

### Module ÉTATS DE PAIEMENT (3 écrans) — CIRCUIT DE VALIDATION

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| ETAT-01 | `views/missions/MissionsIndexView.vue` | `/missions` | Liste des missions (point d'entrée) : N OM + 1 état, avancement du circuit, filtre par statut d'état |
| ETAT-02 | `views/etats/EtatListView.vue` | `/etats` | File de travail : états filtrables par statut, ouverte d'emblée sur la file de l'acteur (contrôleur/DAF/DG) |
| ETAT-03 | `views/etats/EtatDetailView.vue` | `/missions/:missionId/etat` | Détail de l'état : montants, frise du circuit, numérotation, actions (contrôler/viser/valider/rejeter/rafraîchir), PDF brouillon & définitif |

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

### Module ADMINISTRATION (7 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| ADM-01 | `views/admin/UserManagementView.vue` | `/admin/utilisateurs` | CRUD complet utilisateurs + rôles (liste, filtres, statut, reset mot de passe) |
| ADM-02 | `views/admin/UserCreateView.vue` | `/admin/utilisateurs/nouveau` | Formulaire de création d'un utilisateur (nom, email, rôle — dont DAF/DG, téléphone) |
| ADM-03 | `views/admin/RolesView.vue` | `/admin/roles` | Matrice permissions par rôle (dérivée du RBAC, 8 profils) |
| ADM-04 | `views/admin/SignaturesView.vue` | `/admin/signatures` | Signatures du circuit : identités + paraphes scannés du contrôleur, du DAF et du DG imprimés sur l'état |
| ADM-05 | `views/admin/SettingsView.vue` | `/admin/parametres` | Config système, nom app, logo, email |
| ADM-06 | `views/admin/EmailConfigView.vue` | `/admin/email` | Config SMTP, templates notifications |
| ADM-07 | `views/admin/BackupView.vue` | `/admin/sauvegardes` | Sauvegardes BDD, téléchargement, restauration |

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
  categorie: 'ORDRE_1' | 'ORDRE_2' | 'ORDRE_3'  // reprise de SEMO
  zone_code: 'INTERIEUR' | 'EXT_ZONE_I' | 'EXT_ZONE_II' | 'EXT_ZONE_III'
  type_prise_en_charge: 'UN_REPAS' | 'DEUX_REPAS' | 'JOURNEE_COMPLETE'
                       | 'LOGE_NOURRI' | 'LOGE_SEUL' | 'NOURRI_SEUL'
  nb_jours: number
  montant_total: number         // taux_journalier × nb_jours (FCFA)
  avance: number                // round(montant_total × 0,70)  (70 %)
  reliquat: number              // montant_total - avance       (30 %)
  statut: 'BROUILLON' | 'IMPORTE' | 'EN_ATTENTE' | 'VALIDE' | 'REJETE' | 'SOLDE' | 'ANNULE'
  conducteur_a_bord?: string    // mention logistique uniquement (≠ bénéficiaire)
  reference_autorisation?: string
  moyen_transport?: string
  commentaire_rejet?: string
  versements?: Versement[]
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
  indice?: number              // ORDRE_1 ≥ 800 ; ORDRE_2 400-799 ; ORDRE_3 < 400
  salaire_brut?: number        // ≥ 372000 / 186000-372000 / < 186000
  categorie: 'ORDRE_1' | 'ORDRE_2' | 'ORDRE_3'  // déduite de l'indice OU du salaire
  telephone: string            // national 10 chiffres, débute par 01
  operateur: 'MTN' | 'MOOV'    // cohérent avec le préfixe du numéro
  actif: boolean
}
```

### Bareme (barème officiel ANaTT — remplace TauxPerDiem)

```typescript
interface Bareme {
  id: number
  categorie: 'ORDRE_1' | 'ORDRE_2' | 'ORDRE_3'
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
  montant: number
  operateur: 'MTN' | 'MOOV'    // figé depuis le référentiel agent
  telephone: string
  statut: 'EN_ATTENTE' | 'GENERE' | 'CONFIRME' | 'ANNULE'
  date_paiement?: string       // rempli à la confirmation manuelle
}
```

### LotPaiement (1 mission × 1 tranche × 1 opérateur)

```typescript
interface LotPaiement {
  id: number
  mission_id: number
  tranche: 'AVANCE' | 'RELIQUAT'
  operateur: 'MTN' | 'MOOV'
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
```
> Le login renvoie `{ token, user, permissions[] }` et met à jour `derniere_connexion`.

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
> Matrice éditable : `config/profils.php`. SUPER_ADMIN initial paramétrable via `.env`
> (`SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`).

### Agents
```
GET    /api/agents                  → { data: Agent[], meta } (filtres: search, categorie, operateur, actif)
GET    /api/agents/recherche        → ?q=...  autocomplétion (nom/matricule/IFU)
POST   /api/agents                  → Créer (catégorie déduite, cohérence numéro/opérateur)
GET    /api/agents/:id              → Agent + ordres de mission
PUT    /api/agents/:id              → Modifier agent
DELETE /api/agents/:id              → Désactiver agent
POST   /api/agents/import           → Import Excel/CSV (rapport d'erreurs ligne à ligne, rollback)
```
> Écriture/import : rôle `gestionnaire_om`. Lecture : tout authentifié.

### Missions & Ordres de Mission
```
POST   /api/missions                → Retranscription : crée la mission + N OM (rôle gestionnaire_om)
                                       Body: { objet, destination, zone_code?, dates, prise_en_charge_tiers,
                                               ordres:[{ reference_semo, agent_id, role, type_prise_en_charge,
                                                         categorie?, nb_jours? }] }
GET    /api/ordres                  → Liste filtrée (statut, zone, agent_id, operateur, dates, search)
GET    /api/ordres/:id              → Détail OM (mission, agent, versements)
POST   /api/ordres/:id/valider      → IMPORTE → VALIDE (rôle controleur : om.valider)
POST   /api/ordres/:id/rejeter      → IMPORTE → REJETE (motif ≥ 10 chars ; rôle controleur : om.valider)
GET    /api/ordres/:id/pdf          → Génère le PDF de l'OM → { url, fichier }
GET    /api/ordres/:id/pdf/download → Télécharge le PDF
GET    /api/ordres/:id/historique   → Journal d'audit de l'OM
```
> Les OM ne sont ni créés ni modifiés individuellement (retranscrits via /missions). Pas de PUT/DELETE.

### Paiements (mode GÉNÉRATION — rôle agent_financier)
```
GET    /api/paiements/lots                  → Liste des lots (filtres mission/tranche/operateur/statut)
GET    /api/paiements/lots/previsualiser    → ?mission_id&tranche&operateur → bénéficiaires éligibles
POST   /api/paiements/lots/generer          → Génère le fichier (.txt MTN / .xlsx Moov), lot → GENERE
                                              petit lot : téléchargement direct ; gros lot : 202 + tracking_id (file Redis)
GET    /api/paiements/lots/:lot/download    → Re-télécharge le fichier du lot
POST   /api/paiements/lots/:lot/confirmer   → Lot + versements → CONFIRME, horodatés
POST   /api/paiements/lots/:lot/erreur      → Lot → ERREUR (autorise régénération)
GET    /api/paiements/lots/job/:trackingId  → Suivi d'une génération asynchrone
GET    /api/paiements/statuts               → Synthèse versements par opérateur/statut
GET    /api/paiements/historique            → Versements CONFIRMÉS filtrables (date_paiement)
```
> Idempotence : un versement CONFIRME n'est jamais réinclus. Aucun réimport, aucune API opérateur.

### Barème (per diem)
```
GET    /api/baremes                 → Liste du barème (lecture pour tous)
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
GET    /api/rapports/export         → ?dimension&format=xlsx|pdf&date_debut&date_fin → { fichier, url }
GET    /api/rapports/export/download → ?fichier=... → téléchargement
POST   /api/rapports/export-async   → Production en file Redis → { tracking_id }
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

$montantTotal = $taux * $nbJours;
$avance       = (int) round($montantTotal * 0.70);     // 70 %
$reliquat     = $montantTotal - $avance;               // 30 %

// Exonération : si mission.prise_en_charge_tiers => montants à 0, aucun versement.
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
- Paiement : cycle `EN_ATTENTE → GENERE → CONFIRME`. Confirmation **manuelle** après dépôt. Régénération tant que non confirmé ; **idempotence** (un versement CONFIRME n'est jamais réinclus).

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
npm install
npm run dev              # http://localhost:5173
npm run build
npm run test

# Backend
cd backend
composer install
php artisan migrate --seed
php artisan serve        # http://localhost:8000
php artisan queue:work   # Jobs en arrière-plan
./vendor/bin/pest        # Tests

# Docker
docker-compose up -d     # Démarre tout
docker-compose logs -f   # Logs temps réel

# Génération de code Laravel
php artisan make:model OrdreMission -mcr   # Model + migration + controller
php artisan make:request CreateOrdreMissionRequest
php artisan make:resource OrdreMissionResource
php artisan make:job GeneratePaiementFile
php artisan make:service MissionService    # (package nécessaire)
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
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=anatt_missions
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@anatt.bj
MAIL_FROM_NAME="ANaTT Missions"

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Mobile Money (sandbox)
MOOV_API_URL=https://api-sandbox.moov-africa.bj
MOOV_API_KEY=
MTN_API_URL=https://sandbox.momodeveloper.mtn.com
MTN_API_KEY=
MTN_SUBSCRIPTION_KEY=
```

---

*Ce fichier est maintenu par l'équipe développement ANaTT.*  
*Dernière mise à jour : 31 mai 2026*  
*Version projet : 1.0.0*