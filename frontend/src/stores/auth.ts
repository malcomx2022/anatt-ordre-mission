import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { authService } from '@/services/auth'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const loading = ref(false)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(email: string, password: string) {
    loading.value = true
    try {
      const { token: jeton, user: utilisateur } = await authService.login(email, password)
      token.value = jeton
      user.value = utilisateur
      localStorage.setItem('auth_token', jeton)
    } finally {
      loading.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      user.value = await authService.me()
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
    localStorage.removeItem('auth_token')
    sessionStorage.clear()
  }

  return { user, token, loading, isAuthenticated, login, fetchUser, logout }
})
