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

const routes: RouteRecordRaw[] = [
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

  // ── DASHBOARD ──
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/dashboard/DashboardView.vue'),
    meta: { auth: true },
  },

  // ── PERSONNEL / AGENTS ──
  {
    path: '/agents',
    name: 'agents',
    component: () => import('@/views/agents/AgentListView.vue'),
    meta: { auth: true },
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
    meta: { auth: true },
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
  {
    path: '/ordres',
    name: 'missions',
    component: () => import('@/views/missions/MissionListView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ordres/nouveau',
    name: 'mission-create',
    component: () => import('@/views/missions/MissionCreateView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ordres/:id',
    name: 'mission-detail',
    component: () => import('@/views/missions/MissionDetailView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ordres/:id/modifier',
    name: 'mission-edit',
    component: () => import('@/views/missions/MissionEditView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ordres/:id/valider-n1',
    name: 'mission-validate-n1',
    component: () => import('@/views/missions/MissionValidation1View.vue'),
    meta: { auth: true, permission: 'om.valider' },
  },
  {
    path: '/ordres/:id/valider-dg',
    name: 'mission-validate-dg',
    component: () => import('@/views/missions/MissionValidation2View.vue'),
    meta: { auth: true, permission: 'om.valider' },
  },
  {
    path: '/ordres/:id/pdf',
    name: 'mission-pdf',
    component: () => import('@/views/missions/MissionPdfView.vue'),
    meta: { auth: true },
  },
  {
    path: '/ordres/:id/historique',
    name: 'mission-history',
    component: () => import('@/views/missions/MissionHistoryView.vue'),
    meta: { auth: true },
  },

  // ── PAIEMENTS ──
  {
    path: '/paiements',
    name: 'paiements',
    component: () => import('@/views/paiements/PaiementListView.vue'),
    meta: { auth: true },
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
    meta: { auth: true },
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
    meta: { auth: true },
  },

  // ── TAUX PER DIEM ──
  {
    path: '/taux',
    name: 'taux',
    component: () => import('@/views/taux/TauxListView.vue'),
    meta: { auth: true },
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
    name: 'utilisateurs.gerer',
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
    path: '/admin/parametres',
    name: 'parametres.gerer',
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
    name: 'parametres.gerer',
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
