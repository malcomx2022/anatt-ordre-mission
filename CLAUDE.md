# CLAUDE.md — ANaTT Système de Gestion des Ordres de Mission

> Ce fichier est lu automatiquement par Claude Code à chaque session.
> Il contient tout le contexte nécessaire pour développer l'application sans instructions répétées.

---

## 1. CONTEXTE PROJET

**Application** : Système de gestion des ordres de mission et paiements per diem  
**Client** : ANaTT — Agence Nationale des Transports Terrestres, République du Bénin  
**Objectif** : Dématérialiser le circuit des ordres de mission (création → validation → paiement mobile money)  
**Version** : 1.0.0  
**Statut** : En développement

### Workflow métier principal

```
Agent soumet OM → Chef de service valide → DG approuve → RH calcule per diem → Paiement Moov/MTN
```

### Rôles utilisateurs

| Rôle | Permissions |
|------|-------------|
| `AGENT` | Soumettre ses propres ordres de mission |
| `CHEF_SERVICE` | Valider les OM de son service |
| `DRH` | Gérer les agents, taux per diem, rapports |
| `DIRECTEUR` | Approbation finale, tableau de bord global |
| `ADMIN` | Accès total, configuration système |

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
│   │   │   └── logo-anatt.jpg  ← Logo base64 (NE PAS supprimer)
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
│   │   ├── views/              ← Pages (41 écrans)
│   │   └── main.ts
│   ├── tailwind.config.ts
│   └── vite.config.ts
│
├── backend/                     ← API Laravel 11
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── AgentController.php
│   │   │   ├── MissionController.php
│   │   │   ├── ValidationController.php
│   │   │   ├── PaiementController.php
│   │   │   ├── TauxController.php
│   │   │   ├── RapportController.php
│   │   │   └── AuditController.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Agent.php
│   │   │   ├── OrdreMission.php
│   │   │   ├── Mission.php
│   │   │   ├── Paiement.php
│   │   │   ├── TauxPerDiem.php
│   │   │   └── AuditLog.php
│   │   ├── Services/
│   │   │   ├── MissionService.php
│   │   │   ├── PerDiemCalculator.php
│   │   │   ├── PdfGenerator.php
│   │   │   ├── MoovPaymentService.php
│   │   │   └── MtnPaymentService.php
│   │   └── Jobs/
│   │       ├── GeneratePaiementFile.php
│   │       └── SendNotification.php
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

1. Le logo ANaTT DOIT être en base64 embarqué — jamais de lien externe
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
│  │   Taux · Rapports · Audit · Administration              │
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

## 5. REGISTRE DES 41 ÉCRANS

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
| OM-01 | `views/missions/MissionListView.vue` | `/ordres` | Table ordres avec filtres statut/date/agent |
| OM-02 | `views/missions/MissionCreateView.vue` | `/ordres/nouveau` | Formulaire création : agent, destination, dates, motif |
| OM-03 | `views/missions/MissionDetailView.vue` | `/ordres/:id` | Détail complet, timeline statut, documents |
| OM-04 | `views/missions/MissionEditView.vue` | `/ordres/:id/modifier` | Modification (seulement si brouillon) |
| OM-05 | `views/missions/MissionValidation1View.vue` | `/ordres/:id/valider-n1` | Validation chef de service (approuver/rejeter + commentaire) |
| OM-06 | `views/missions/MissionValidation2View.vue` | `/ordres/:id/valider-dg` | Validation DG (approbation finale) |
| OM-07 | `views/missions/MissionPdfView.vue` | `/ordres/:id/pdf` | Aperçu PDF in-browser + bouton télécharger/imprimer |
| OM-08 | `views/missions/MissionHistoryView.vue` | `/ordres/:id/historique` | Journal complet des actions sur l'OM |

### Module PAIEMENTS (6 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| PAY-01 | `views/paiements/PaiementListView.vue` | `/paiements` | Liste paiements avec statuts Moov/MTN |
| PAY-02 | `views/paiements/PaiementMoovView.vue` | `/paiements/moov` | Sélection OM, calcul per diem, génération fichier Moov |
| PAY-03 | `views/paiements/PaiementMtnView.vue` | `/paiements/mtn` | Sélection OM, calcul per diem, génération fichier MTN |
| PAY-04 | `views/paiements/PaiementStatutView.vue` | `/paiements/statuts` | Tableau de bord statuts retours opérateurs |
| PAY-05 | `views/paiements/PaiementReconciliationView.vue` | `/paiements/reconciliation` | Rapprochement fichiers envoyés vs retours |
| PAY-06 | `views/paiements/PaiementHistoriqueView.vue` | `/paiements/historique` | Historique complet virements par période |

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

### Module ADMINISTRATION (5 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| ADM-01 | `views/admin/UserManagementView.vue` | `/admin/utilisateurs` | CRUD complet utilisateurs + rôles |
| ADM-02 | `views/admin/RolesView.vue` | `/admin/roles` | Matrice permissions par rôle |
| ADM-03 | `views/admin/SettingsView.vue` | `/admin/parametres` | Config système, nom app, logo, email |
| ADM-04 | `views/admin/EmailConfigView.vue` | `/admin/email` | Config SMTP, templates notifications |
| ADM-05 | `views/admin/BackupView.vue` | `/admin/sauvegardes` | Sauvegardes BDD, téléchargement, restauration |

### Pages SYSTÈME (4 écrans)

| ID | Fichier Vue | Route | Description |
|----|-------------|-------|-------------|
| SYS-01 | `views/system/NotFoundView.vue` | `/:pathMatch(.*)` | 404 avec lien retour accueil |
| SYS-02 | `views/system/ServerErrorView.vue` | `/500` | 500 avec détails techniques (dev) |
| SYS-03 | `views/system/MaintenanceView.vue` | `/maintenance` | Page maintenance avec countdown |
| SYS-04 | `views/system/ForbiddenView.vue` | `/403` | Accès refusé avec rôle requis |

---

## 6. MODÈLES DE DONNÉES

### OrdreMission

```typescript
interface OrdreMission {
  id: number
  numero: string           // OM-2026-0001 (auto-généré)
  agent_id: number
  agent: Agent
  objet: string
  destination: string
  date_depart: string      // ISO 8601
  date_retour: string
  nb_jours: number         // calculé automatiquement
  taux_id: number
  taux: TauxPerDiem
  montant_per_diem: number // calculé : nb_jours × taux.montant_journalier
  statut: 'BROUILLON' | 'SOUMIS' | 'VALIDE_N1' | 'APPROUVE_DG' | 'PAYE' | 'REJETE'
  commentaire_rejet?: string
  valide_par_chef?: number
  valide_par_dg?: number
  created_at: string
  updated_at: string
}
```

### Agent

```typescript
interface Agent {
  id: number
  matricule: string
  nom: string
  prenom: string
  email: string
  telephone: string
  numero_moov?: string    // pour paiement Moov
  numero_mtn?: string     // pour paiement MTN
  service: string
  categorie: 'A1' | 'A2' | 'B' | 'C' | 'D'  // détermine le taux per diem
  actif: boolean
}
```

### TauxPerDiem

```typescript
interface TauxPerDiem {
  id: number
  categorie: string
  destination_type: 'LOCALE' | 'REGIONALE' | 'INTERNATIONALE'
  montant_journalier: number  // en FCFA
  annee: number
  actif: boolean
}
```

### Paiement

```typescript
interface Paiement {
  id: number
  ordre_mission_id: number
  agent_id: number
  operateur: 'MOOV' | 'MTN'
  numero_destinataire: string
  montant: number
  statut: 'EN_ATTENTE' | 'INITIE' | 'SUCCES' | 'ECHEC'
  reference_transaction?: string
  fichier_virement?: string   // nom du fichier généré
  date_virement?: string
  created_at: string
}
```

---

## 7. API ENDPOINTS LARAVEL

### Authentification
```
POST   /api/auth/login              → { token, user }
POST   /api/auth/logout
POST   /api/auth/mot-de-passe/email
POST   /api/auth/mot-de-passe/reset
GET    /api/auth/me                 → User connecté
```

### Agents
```
GET    /api/agents                  → { data: Agent[], meta: Pagination }
POST   /api/agents                  → Créer agent
GET    /api/agents/:id              → Agent + missions
PUT    /api/agents/:id              → Modifier agent
DELETE /api/agents/:id              → Désactiver agent
POST   /api/agents/import           → Upload CSV agents
```

### Ordres de Mission
```
GET    /api/ordres                  → Liste avec filtres (statut, agent, dates)
POST   /api/ordres                  → Créer OM (statut: BROUILLON)
GET    /api/ordres/:id              → Détail OM
PUT    /api/ordres/:id              → Modifier OM (seulement si BROUILLON)
DELETE /api/ordres/:id              → Supprimer OM (seulement si BROUILLON)
POST   /api/ordres/:id/soumettre    → Passer BROUILLON → SOUMIS
POST   /api/ordres/:id/valider-n1   → Chef service : SOUMIS → VALIDE_N1
POST   /api/ordres/:id/approuver    → DG : VALIDE_N1 → APPROUVE_DG
POST   /api/ordres/:id/rejeter      → Rejeter avec commentaire → REJETE
GET    /api/ordres/:id/pdf          → Génération PDF ordre de mission
GET    /api/ordres/:id/historique   → Journal des actions sur l'OM
```

### Paiements
```
GET    /api/paiements               → Liste paiements
POST   /api/paiements/generer/moov  → Générer fichier virement Moov (CSV)
POST   /api/paiements/generer/mtn   → Générer fichier virement MTN (CSV)
GET    /api/paiements/statuts       → Statuts depuis retours opérateurs
POST   /api/paiements/reconcilier   → Upload fichier retour + rapprochement
GET    /api/paiements/historique    → Historique filtrable
```

### Taux Per Diem
```
GET    /api/taux                    → Liste taux actifs
PUT    /api/taux/:id                → Modifier taux
POST   /api/taux                    → Créer nouveau taux
```

### Rapports
```
GET    /api/rapports/mensuel        → ?mois=2026-01  → stats complètes
GET    /api/rapports/agents         → ?agent_id=X&annee=2026
GET    /api/rapports/export         → ?format=excel&periode=... → fichier
```

### Audit
```
GET    /api/audit                   → Journal activité (filtres: action, user, date)
GET    /api/audit/:id               → Détail action
```

---

## 8. RÈGLES MÉTIER

### Calcul du Per Diem

```php
// PerDiemCalculator.php
$nb_jours = Carbon::parse($date_retour)->diffInDays(Carbon::parse($date_depart)) + 1;
$taux = TauxPerDiem::where('categorie', $agent->categorie)
                    ->where('destination_type', $destination_type)
                    ->where('annee', now()->year)
                    ->where('actif', true)
                    ->first();
$montant = $nb_jours * $taux->montant_journalier;
```

### Format fichier virement Moov (CSV)

```
NUMERO_TELEPHONE;MONTANT;REFERENCE;NOM_BENEFICIAIRE;MOTIF
22997123456;45000;OM-2026-0001;KOUASSI Jean;Per diem mission Parakou Jan 2026
```

### Format fichier virement MTN (CSV)

```
MSISDN;AMOUNT;EXTERNAL_REF;BENEFICIARY_NAME;NARRATION
22961234567;45000;OM-2026-0001;KOUASSI Jean;Per diem mission
```

### Règles de validation

- Un OM ne peut être modifié que si statut = `BROUILLON`
- La validation N+1 nécessite le rôle `CHEF_SERVICE` du même service que l'agent
- L'approbation DG nécessite le rôle `DIRECTEUR`
- Un OM `APPROUVE_DG` ne peut plus être modifié ni supprimé
- Le paiement génère automatiquement une notification email à l'agent
- Tout rejeter nécessite un commentaire obligatoire (min 10 chars)

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
*Dernière mise à jour : Avril 2026*  
*Version projet : 1.0.0*