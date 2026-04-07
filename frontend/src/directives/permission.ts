import type { Directive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { Permission } from '@/composables/useAuth'

const rolePermissions: Record<string, string[]> = {
  ADMIN: [
    'dashboard.view', 'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'ordres.write', 'ordres.submit',
    'ordres.validate_n1', 'ordres.approve_dg', 'ordres.reject',
    'paiements.read', 'paiements.generate', 'paiements.reconcile',
    'taux.read', 'taux.write', 'rapports.read', 'rapports.export',
    'audit.read', 'admin.users', 'admin.roles', 'admin.settings', 'admin.backups',
  ],
  DIRECTEUR: [
    'dashboard.view', 'agents.read',
    'ordres.read', 'ordres.write', 'ordres.submit', 'ordres.approve_dg', 'ordres.reject',
    'paiements.read', 'taux.read', 'rapports.read', 'rapports.export', 'audit.read',
  ],
  CHEF_SERVICE: [
    'dashboard.view', 'agents.read',
    'ordres.read', 'ordres.write', 'ordres.submit', 'ordres.validate_n1', 'ordres.reject',
    'paiements.read', 'taux.read', 'rapports.read', 'rapports.export',
  ],
  DRH: [
    'dashboard.view', 'agents.read', 'agents.write', 'agents.import',
    'ordres.read', 'ordres.write', 'ordres.submit',
    'paiements.read', 'paiements.generate', 'paiements.reconcile',
    'taux.read', 'taux.write', 'rapports.read', 'rapports.export',
  ],
  AGENT: [
    'dashboard.view', 'agents.read', 'ordres.read', 'ordres.submit', 'paiements.read', 'taux.read',
  ],
}

function hasPermission(permission: string): boolean {
  const store = useAuthStore()
  const userRole = store.user?.role
  if (!userRole) return false
  const perms = rolePermissions[userRole] || []
  return perms.includes(permission)
}

export const vPermission: Directive<HTMLElement, string | string[]> = {
  mounted(el, binding) {
    const required = Array.isArray(binding.value) ? binding.value : [binding.value]
    const hasAny = required.some((p) => hasPermission(p))
    if (!hasAny) {
      el.style.display = 'none'
    }
  },
  updated(el, binding) {
    const required = Array.isArray(binding.value) ? binding.value : [binding.value]
    const hasAny = required.some((p) => hasPermission(p))
    el.style.display = hasAny ? '' : 'none'
  },
}
