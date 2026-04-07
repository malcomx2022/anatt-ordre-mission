import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

export type Permission =
  | 'dashboard.view'
  | 'agents.read' | 'agents.write' | 'agents.import'
  | 'ordres.read' | 'ordres.write' | 'ordres.submit'
  | 'ordres.validate_n1' | 'ordres.approve_dg' | 'ordres.reject'
  | 'paiements.read' | 'paiements.generate' | 'paiements.reconcile'
  | 'taux.read' | 'taux.write'
  | 'rapports.read' | 'rapports.export'
  | 'audit.read'
  | 'admin.users' | 'admin.roles' | 'admin.settings' | 'admin.backups'

const rolePermissions: Record<UserRole, Permission[]> = {
  ADMIN: [
    'dashboard.view',
    'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'ordres.write', 'ordres.submit',
    'ordres.validate_n1', 'ordres.approve_dg', 'ordres.reject',
    'paiements.read', 'paiements.generate', 'paiements.reconcile',
    'taux.read', 'taux.write',
    'rapports.read', 'rapports.export',
    'audit.read',
    'admin.users', 'admin.roles', 'admin.settings', 'admin.backups',
  ],
  DIRECTEUR: [
    'dashboard.view',
    'agents.read',
    'ordres.read', 'ordres.write', 'ordres.submit', 'ordres.approve_dg', 'ordres.reject',
    'paiements.read',
    'taux.read',
    'rapports.read', 'rapports.export',
    'audit.read',
  ],
  CHEF_SERVICE: [
    'dashboard.view',
    'agents.read',
    'ordres.read', 'ordres.write', 'ordres.submit', 'ordres.validate_n1', 'ordres.reject',
    'paiements.read',
    'taux.read',
    'rapports.read', 'rapports.export',
  ],
  DRH: [
    'dashboard.view',
    'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'ordres.write', 'ordres.submit',
    'paiements.read', 'paiements.generate', 'paiements.reconcile',
    'taux.read', 'taux.write',
    'rapports.read', 'rapports.export',
  ],
  AGENT: [
    'dashboard.view',
    'agents.read',
    'ordres.read', 'ordres.submit',
    'paiements.read',
    'taux.read',
  ],
}

export function useAuth() {
  const store = useAuthStore()

  const isAuthenticated = computed(() => store.isAuthenticated)
  const user = computed(() => store.user)
  const role = computed(() => store.user?.role)

  const permissions = computed<Permission[]>(() => {
    if (!store.user?.role) return []
    return rolePermissions[store.user.role] || []
  })

  function hasRole(...roles: UserRole[]): boolean {
    return !!store.user && roles.includes(store.user.role)
  }

  function can(permission: Permission): boolean {
    return permissions.value.includes(permission)
  }

  function canAny(...perms: Permission[]): boolean {
    return perms.some((p) => permissions.value.includes(p))
  }

  function isAdmin(): boolean {
    return hasRole('ADMIN')
  }

  function canValidateN1(): boolean {
    return can('ordres.validate_n1')
  }

  function canApproveDG(): boolean {
    return can('ordres.approve_dg')
  }

  function canManageAgents(): boolean {
    return can('agents.write')
  }

  function canManagePaiements(): boolean {
    return can('paiements.generate')
  }

  function canAccessAdmin(): boolean {
    return canAny('admin.users', 'admin.roles', 'admin.settings', 'admin.backups')
  }

  function canAccessRapports(): boolean {
    return can('rapports.read')
  }

  function canAccessAudit(): boolean {
    return can('audit.read')
  }

  return {
    isAuthenticated,
    user,
    role,
    permissions,
    hasRole,
    can,
    canAny,
    isAdmin,
    canValidateN1,
    canApproveDG,
    canManageAgents,
    canManagePaiements,
    canAccessAdmin,
    canAccessRapports,
    canAccessAudit,
    login: store.login,
    logout: store.logout,
    fetchUser: store.fetchUser,
  }
}
