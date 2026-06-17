import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { authService } from '@/services/auth'
import type { User } from '@/types'

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
      user.value = utilisateur
      permissions.value = perms ?? []
    } catch {
      await logout()
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
    localStorage.removeItem('auth_token')
    sessionStorage.clear()
  }

  return { user, token, permissions, loading, isAuthenticated, isSuperAdmin, mustChangePassword, can, login, fetchUser, logout }
})
