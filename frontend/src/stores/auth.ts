import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { useApi } from '@/composables/useApi'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(email: string, password: string) {
    const api = useApi()
    const { data } = await api.post('/api/auth/login', { email, password })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('auth_token', data.token)
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const api = useApi()
      const { data } = await api.get('/api/auth/me')
      user.value = data.data || data
    } catch {
      logout()
    }
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('auth_token')
  }

  return { user, token, isAuthenticated, login, fetchUser, logout }
})
