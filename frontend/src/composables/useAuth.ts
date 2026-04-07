import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types'

export function useAuth() {
  const store = useAuthStore()

  const isAuthenticated = computed(() => store.isAuthenticated)
  const user = computed(() => store.user)
  const role = computed(() => store.user?.role)

  function hasRole(...roles: UserRole[]): boolean {
    return !!store.user && roles.includes(store.user.role)
  }

  function isAdmin(): boolean {
    return hasRole('ADMIN')
  }

  function canValidateN1(): boolean {
    return hasRole('CHEF_SERVICE', 'ADMIN')
  }

  function canApproveDG(): boolean {
    return hasRole('DIRECTEUR', 'ADMIN')
  }

  function canManageAgents(): boolean {
    return hasRole('DRH', 'ADMIN')
  }

  return {
    isAuthenticated,
    user,
    role,
    hasRole,
    isAdmin,
    canValidateN1,
    canApproveDG,
    canManageAgents,
    login: store.login,
    logout: store.logout,
    fetchUser: store.fetchUser,
  }
}
