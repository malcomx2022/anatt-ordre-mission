import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { authService } from '@/services/auth'
import { chargerPartAvance, reinitialiser as reinitialiserPartAvance } from '@/composables/usePartAvance'
import { chargerApplication } from '@/composables/useApplication'
import { useNotificationsStore } from '@/stores/notifications'
import type { User } from '@/types'

/** Statut HTTP d'une erreur Axios (undefined : réseau injoignable ou autre erreur). */
function statutHttp(e: unknown): number | undefined {
  return (e as { response?: { status?: number } } | null)?.response?.status
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const permissions = ref<string[]>([])
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isSuperAdmin = computed(() => user.value?.role === 'SUPER_ADMIN')
  /** L'utilisateur doit changer son mot de passe (création/réinitialisation admin). */
  const mustChangePassword = computed(() => user.value?.must_change_password === true)

  /** Le SUPER_ADMIN a toujours la permission (cohérent avec Gate::before backend). */
  function can(permission: string): boolean {
    return isSuperAdmin.value || permissions.value.includes(permission)
  }

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const { token: jeton, user: utilisateur, permissions: perms } = await authService.login(email, password)
      token.value = jeton
      user.value = utilisateur
      permissions.value = perms ?? []
      localStorage.setItem('auth_token', jeton)
      // Pré-charge le pourcentage d'avance paramétrable dès la session ouverte
      // (idempotent) : les écrans le liront sans latence ni retour à 70 %.
      // Pas pendant un changement de mot de passe imposé : l'API répond 423.
      if (!mustChangePassword.value) { void chargerPartAvance(); void chargerApplication() }
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const [utilisateur, perms] = await Promise.all([
        authService.me(),
        authService.mePermissions(),
      ])
      const devaitChanger = mustChangePassword.value
      user.value = utilisateur
      permissions.value = perms ?? []
      // Sortie d'un changement de mot de passe imposé : on repart d'un cache
      // vierge pour relire la vraie valeur serveur (et non le défaut 70 %).
      if (devaitChanger && !mustChangePassword.value) reinitialiserPartAvance()
      // Session restaurée (rechargement) : recharge aussi le pourcentage d'avance
      // (sauf mot de passe à changer : l'API répondrait 423).
      if (!mustChangePassword.value) { void chargerPartAvance(); void chargerApplication() }
    } catch (e) {
      // Seul un 401 (jeton expiré ou révoqué) met fin à la session. Une erreur
      // passagère (429, 5xx, réseau) la garde : logout() révoquerait le jeton.
      if (statutHttp(e) === 401) {
        await logout()
        return
      }
      throw e
    }
  }

  async function logout() {
    if (token.value) {
      try {
        await authService.logout()
      } catch {
        // Ignorer si le serveur est injoignable
      }
    }
    user.value = null
    token.value = null
    permissions.value = []
    // Ne pas conserver le réglage d'une session à l'autre : purge du cache.
    reinitialiserPartAvance()
    // Purge le feed de la cloche : pas de notifications d'un compte à l'autre.
    useNotificationsStore().vider()
    localStorage.removeItem('auth_token')
    sessionStorage.clear()
  }

  return { user, token, permissions, loading, isAuthenticated, isSuperAdmin, mustChangePassword, can, login, fetchUser, logout }
})
