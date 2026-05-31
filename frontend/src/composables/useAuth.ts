import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

export type Permission =
  | 'dashboard.view'
  | 'agents.read' | 'agents.write' | 'agents.import'
  | 'ordres.read' | 'missions.create' | 'ordres.validate' | 'ordres.reject'
  | 'paiements.read' | 'paiements.generate' | 'paiements.confirm'
  | 'baremes.read' | 'baremes.write'
  | 'rapports.read' | 'rapports.export'
  | 'audit.read'
  | 'admin.users' | 'admin.settings' | 'admin.backups'

const rolePermissions: Record<UserRole, Permission[]> = {
  admin: [
    'dashboard.view',
    'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'missions.create', 'ordres.validate', 'ordres.reject',
    'paiements.read', 'paiements.generate', 'paiements.confirm',
    'baremes.read', 'baremes.write',
    'rapports.read', 'rapports.export',
    'audit.read',
    'admin.users', 'admin.settings', 'admin.backups',
  ],
  gestionnaire_om: [
    'dashboard.view',
    'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'missions.create', 'ordres.validate', 'ordres.reject',
    'paiements.read',
    'baremes.read',
    'rapports.read', 'rapports.export',
  ],
  agent_financier: [
    'dashboard.view',
    'agents.read',
    'ordres.read',
    'paiements.read', 'paiements.generate', 'paiements.confirm',
    'baremes.read',
    'rapports.read', 'rapports.export',
  ],
  lecteur: [
    'dashboard.view',
    'agents.read',
    'ordres.read',
    'paiements.read',
    'baremes.read',
    'rapports.read',
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
    return hasRole('admin')
  }

  function canManageAgents(): boolean {
    return can('agents.write')
  }

  function canCreerMissions(): boolean {
    return can('missions.create')
  }

  function canValiderOM(): boolean {
    return can('ordres.validate')
  }

  function canGenererPaiements(): boolean {
    return can('paiements.generate')
  }

  function canConfirmerPaiements(): boolean {
    return can('paiements.confirm')
  }

  function canManageBaremes(): boolean {
    return can('baremes.write')
  }

  function canAccessRapports(): boolean {
    return can('rapports.read')
  }

  function canAccessAudit(): boolean {
    return can('audit.read')
  }

  function canAccessAdmin(): boolean {
    return canAny('admin.users', 'admin.settings', 'admin.backups')
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
    canManageAgents,
    canCreerMissions,
    canValiderOM,
    canGenererPaiements,
    canConfirmerPaiements,
    canManageBaremes,
    canAccessRapports,
    canAccessAudit,
    canAccessAdmin,
    login: store.login,
    logout: store.logout,
    fetchUser: store.fetchUser,
  }
}
