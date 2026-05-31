import { api, unwrap } from './http'
import type { LoginResponse, User } from '@/types'

export const authService = {
  async login(email: string, password: string): Promise<LoginResponse> {
    const { data } = await api.post('/api/auth/login', { email, password })
    return unwrap<LoginResponse>(data)
  },

  async me(): Promise<User> {
    const { data } = await api.get('/api/auth/me')
    return unwrap<User>(data)
  },

  async logout(): Promise<void> {
    await api.post('/api/auth/logout')
  },

  async sendResetLink(email: string): Promise<void> {
    await api.post('/api/auth/mot-de-passe/email', { email })
  },

  async resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await api.post('/api/auth/mot-de-passe/reset', payload)
  },
}
