import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'
import { permissionsForRole } from '@/utils/permissions'
import type { Permission } from '@/utils/permissions'

export type { Permission }

export function useAuth() {
  const store = useAuthStore()

  const isAuthenticated = computed(() => store.isAuthenticated)
  const user = computed(() => store.user)
  const role = computed(() => store.user?.role)

  // Source : permissions renvoyées par l'API (store). Repli sur la matrice
  // locale tant que l'appel /me/permissions n'a pas encore peuplé le store.
  const permissions = computed<Permission[]>(() =>
    store.permissions.length ? (store.permissions as Permission[]) : permissionsForRole(store.user?.role),
  )

  function hasRole(...roles: UserRole[]): boolean {
    return !!store.user && roles.includes(store.user.role)
  }

  function can(permission: Permission): boolean {
    return permissions.value.includes(permission)
  }

  function canAny(...perms: Permission[]): boolean {
    return perms.some((p) => permissions.value.includes(p))
  }

  function isSuperAdmin(): boolean {
    return hasRole('SUPER_ADMIN')
  }

  function isAdmin(): boolean {
    return hasRole('ADMIN')
  }

  function canManageAgents(): boolean {
    return can('personnel.gerer')
  }

  function canCreerMissions(): boolean {
    return can('om.retranscrire')
  }

  function canValiderOM(): boolean {
    return can('om.valider')
  }

  /** Étape 2 du circuit : visa du DAF. */
  function canViserOM(): boolean {
    return can('om.viser')
  }

  /** Étape 3 du circuit : validation finale du Directeur Général. */
  function canApprouverOM(): boolean {
    return can('om.approuver')
  }

  /** Rejet motivé : ouvert aux trois acteurs du circuit. */
  function canRejeterOM(): boolean {
    return canAny('om.rejeter', 'om.valider')
  }

  function canGenererPaiements(): boolean {
    return can('paiements.generer')
  }

  function canConfirmerPaiements(): boolean {
    return can('paiements.confirmer')
  }

  function canManageBaremes(): boolean {
    return can('baremes.modifier')
  }

  function canAccessRapports(): boolean {
    return can('rapports.consulter')
  }

  function canAccessAudit(): boolean {
    return can('audit.consulter')
  }

  function canGererUtilisateurs(): boolean {
    return can('utilisateurs.gerer')
  }

  function canAccessAdmin(): boolean {
    return canAny('utilisateurs.gerer', 'parametres.gerer')
  }

  return {
    isAuthenticated,
    user,
    role,
    permissions,
    hasRole,
    can,
    canAny,
    isSuperAdmin,
    isAdmin,
    canManageAgents,
    canCreerMissions,
    canValiderOM,
    canViserOM,
    canApprouverOM,
    canRejeterOM,
    canGenererPaiements,
    canConfirmerPaiements,
    canManageBaremes,
    canAccessRapports,
    canAccessAudit,
    canGererUtilisateurs,
    canAccessAdmin,
    login: store.login,
    logout: store.logout,
    fetchUser: store.fetchUser,
  }
}
