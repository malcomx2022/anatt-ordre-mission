import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { permissionsForRole } from '@/utils/permissions'
import type { Permission } from '@/utils/permissions'

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
    guest?: boolean
    permission?: Permission | Permission[]
  }
}

// Exporté pour les contrôles de cohérence menu ↔ routes ↔ RBAC
// (cf. __tests__/navigation.spec.ts).
export const routes: RouteRecordRaw[] = [
  // ── AUTH ──
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/mot-de-passe-oublie',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/reinitialiser/:token',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/changer-mot-de-passe',
    name: 'force-password-change',
    component: () => import('@/views/auth/ForcePasswordChangeView.vue'),
    meta: { auth: true },
  },

  // ── DASHBOARD ──
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    meta: { auth: true, permission: 'dashboard.consulter' },
  },

  // ── PERSONNEL / AGENTS ──
  {
    path: '/agents',
    name: 'agents',
    component: () => import('@/views/agents/AgentListView.vue'),
    meta: { auth: true, permission: 'personnel.consulter' },
  },
  {
    path: '/agents/nouveau',
    name: 'agent-create',
    component: () => import('@/views/agents/AgentCreateView.vue'),
    meta: { auth: true, permission: 'personnel.gerer' },
  },
  {
    path: '/agents/import',
    name: 'agent-import',
    component: () => import('@/views/agents/AgentImportView.vue'),
    meta: { auth: true, permission: 'personnel.gerer' },
  },
  {
    path: '/agents/:id',
    name: 'agent-detail',
    component: () => import('@/views/agents/AgentDetailView.vue'),
    meta: { auth: true, permission: 'personnel.consulter' },
  },
  {
    path: '/agents/:id/modifier',
    name: 'agent-edit',
    component: () => import('@/views/agents/AgentEditView.vue'),
    meta: { auth: true, permission: 'personnel.gerer' },
  },
  {
    path: '/mon-profil',
    name: 'profile',
    component: () => import('@/views/agents/AgentProfileView.vue'),
    meta: { auth: true },
  },

  // ── ORDRES DE MISSION ──
  // `ordres` et non `missions` : ce nom était en collision avec la route
  // /missions ci-dessous, et Vue Router garde alors la DERNIÈRE déclarée.
  {
    path: '/ordres',
    name: 'ordres',
    component: () => import('@/views/missions/MissionListView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },
  {
    path: '/ordres/nouveau',
    name: 'mission-create',
    component: () => import('@/views/missions/MissionCreateView.vue'),
    meta: { auth: true, permission: 'om.retranscrire' },
  },
  {
    path: '/ordres/:id',
    name: 'mission-detail',
    component: () => import('@/views/missions/MissionDetailView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },
  {
    path: '/ordres/:id/modifier',
    name: 'mission-edit',
    component: () => import('@/views/missions/MissionEditView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },
  // La validation ne se fait plus OM par OM : elle vit sur l'état de paiement de
  // la mission (EtatDetailView, /missions/:missionId/etat). Les anciennes routes
  // de redirection /ordres/:id/valider-n1 et /valider-dg — vestiges de la double
  // validation N1/DG abandonnée — ont été supprimées.
  {
    path: '/ordres/:id/pdf',
    name: 'mission-pdf',
    component: () => import('@/views/missions/MissionPdfView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },
  {
    path: '/ordres/:id/historique',
    name: 'mission-history',
    component: () => import('@/views/missions/MissionHistoryView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },

  // ── MISSIONS (point d'entrée : N OM + 1 état de paiement) ──
  {
    path: '/missions',
    name: 'missions',
    component: () => import('@/views/missions/MissionsIndexView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },

  // ── ÉTATS DE PAIEMENT (circuit contrôle -> DAF -> DG) ──
  {
    path: '/etats',
    name: 'etats',
    component: () => import('@/views/etats/EtatListView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },
  {
    path: '/missions/:missionId/etat',
    name: 'etat-mission',
    component: () => import('@/views/etats/EtatDetailView.vue'),
    meta: { auth: true, permission: 'om.consulter' },
  },

  // ── PAIEMENTS ──
  {
    path: '/paiements',
    name: 'paiements',
    component: () => import('@/views/paiements/PaiementListView.vue'),
    meta: { auth: true, permission: 'paiements.consulter' },
  },
  {
    path: '/paiements/moov',
    name: 'paiement-moov',
    component: () => import('@/views/paiements/PaiementMoovView.vue'),
    meta: { auth: true, permission: 'paiements.generer' },
  },
  {
    path: '/paiements/mtn',
    name: 'paiement-mtn',
    component: () => import('@/views/paiements/PaiementMtnView.vue'),
    meta: { auth: true, permission: 'paiements.generer' },
  },
  {
    path: '/paiements/statuts',
    name: 'paiement-statuts',
    component: () => import('@/views/paiements/PaiementStatutView.vue'),
    meta: { auth: true, permission: 'paiements.consulter' },
  },
  {
    path: '/paiements/reconciliation',
    name: 'paiement-reconciliation',
    component: () => import('@/views/paiements/PaiementReconciliationView.vue'),
    meta: { auth: true, permission: 'paiements.consulter' },
  },
  {
    path: '/paiements/historique',
    name: 'paiement-historique',
    component: () => import('@/views/paiements/PaiementHistoriqueView.vue'),
    meta: { auth: true, permission: 'paiements.consulter' },
  },

  // ── TAUX PER DIEM ──
  {
    path: '/taux',
    name: 'taux',
    component: () => import('@/views/taux/TauxListView.vue'),
    meta: { auth: true, permission: 'baremes.consulter' },
  },
  {
    path: '/taux/configurer',
    name: 'taux-edit',
    component: () => import('@/views/taux/TauxEditView.vue'),
    meta: { auth: true, permission: 'baremes.modifier' },
  },

  // ── RAPPORTS ──
  {
    path: '/rapports',
    name: 'rapports',
    component: () => import('@/views/rapports/RapportDashboardView.vue'),
    meta: { auth: true, permission: 'rapports.consulter' },
  },
  {
    path: '/rapports/mensuel',
    name: 'rapport-mensuel',
    component: () => import('@/views/rapports/RapportMensuelView.vue'),
    meta: { auth: true, permission: 'rapports.consulter' },
  },
  {
    path: '/rapports/agents',
    name: 'rapport-agents',
    component: () => import('@/views/rapports/RapportAgentView.vue'),
    meta: { auth: true, permission: 'rapports.consulter' },
  },
  {
    path: '/rapports/export',
    name: 'rapport-export',
    component: () => import('@/views/rapports/RapportExportView.vue'),
    meta: { auth: true, permission: 'rapports.consulter' },
  },

  // ── AUDIT ──
  {
    path: '/audit',
    name: 'audit',
    component: () => import('@/views/audit/AuditListView.vue'),
    meta: { auth: true, permission: 'audit.consulter' },
  },
  {
    path: '/audit/:id',
    name: 'audit-detail',
    component: () => import('@/views/audit/AuditDetailView.vue'),
    meta: { auth: true, permission: 'audit.consulter' },
  },

  // ── ADMINISTRATION ──
  {
    path: '/admin/utilisateurs',
    name: 'admin-users',
    component: () => import('@/views/admin/UserManagementView.vue'),
    meta: { auth: true, permission: 'utilisateurs.gerer' },
  },
  {
    path: '/admin/utilisateurs/nouveau',
    name: 'admin-user-create',
    component: () => import('@/views/admin/UserCreateView.vue'),
    meta: { auth: true, permission: 'utilisateurs.gerer' },
  },
  {
    path: '/admin/roles',
    name: 'admin-roles',
    component: () => import('@/views/admin/RolesView.vue'),
    meta: { auth: true, permission: 'utilisateurs.gerer' },
  },
  {
    path: '/admin/signatures',
    name: 'admin-signatures',
    component: () => import('@/views/admin/SignaturesView.vue'),
    meta: { auth: true, permission: 'parametres.gerer' },
  },
  {
    path: '/admin/parametres',
    name: 'admin-settings',
    component: () => import('@/views/admin/SettingsView.vue'),
    meta: { auth: true, permission: 'parametres.gerer' },
  },
  {
    path: '/admin/email',
    name: 'admin-email',
    component: () => import('@/views/admin/EmailConfigView.vue'),
    meta: { auth: true, permission: 'parametres.gerer' },
  },
  {
    path: '/admin/sauvegardes',
    name: 'admin-backups',
    component: () => import('@/views/admin/BackupView.vue'),
    meta: { auth: true, permission: 'parametres.gerer' },
  },

  // ── PAGES SYSTEME ──
  {
    path: '/403',
    name: 'forbidden',
    component: () => import('@/views/system/ForbiddenView.vue'),
  },
  {
    path: '/500',
    name: 'server-error',
    component: () => import('@/views/system/ServerErrorView.vue'),
  },
  {
    path: '/maintenance',
    name: 'maintenance',
    component: () => import('@/views/system/MaintenanceView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/system/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Redirect to login if auth required and not authenticated
  if (to.meta.auth && !auth.isAuthenticated) {
    if (auth.token) {
      try {
        await auth.fetchUser()
        if (auth.isAuthenticated) return checkPermission()
      } catch {
        // token invalid
      }
    }
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Redirect to dashboard if guest route and authenticated
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Changement de mot de passe imposé : on confine l'utilisateur à l'écran dédié
  // tant que must_change_password n'est pas levé (cohérent avec le verrou backend 423).
  if (auth.isAuthenticated && auth.mustChangePassword && to.name !== 'force-password-change') {
    return { name: 'force-password-change' }
  }

  return checkPermission()

  function checkPermission() {
    if (!to.meta.permission) return true

    const userRole = auth.user?.role
    if (!userRole) return { name: 'forbidden' }

    // Permissions renvoyées par l'API (repli sur la matrice locale si non chargées).
    const perms = auth.permissions.length
      ? (auth.permissions as Permission[])
      : permissionsForRole(userRole)
    const isSuperAdmin = userRole === 'SUPER_ADMIN'
    const required = Array.isArray(to.meta.permission) ? to.meta.permission : [to.meta.permission]
    const hasAny = isSuperAdmin || required.some((p) => perms.includes(p))

    if (!hasAny) return { name: 'forbidden' }
    return true
  }
})

export default router
