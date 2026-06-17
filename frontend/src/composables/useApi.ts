import axios from 'axios'
import type { AxiosInstance, AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import router from '@/router'

interface ApiErrorResponse {
  message?: string
  errors?: Record<string, string[]>
}

// Priorité : config runtime (public/runtime-config.js, éditable après déploiement)
// > variable de build VITE_API_URL > défaut local.
const runtimeApiUrl =
  (typeof window !== 'undefined' && window.__ANATT_CONFIG__?.apiUrl) || ''

const apiClient: AxiosInstance = axios.create({
  baseURL: runtimeApiUrl || import.meta.env.VITE_API_URL || 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorResponse>) => {
    const status = error.response?.status
    const message = error.response?.data?.message

    switch (status) {
      case 401: {
        localStorage.removeItem('auth_token')
        const auth = useAuthStore()
        auth.logout()
        router.push({ name: 'login', query: { redirect: window.location.pathname } })
        break
      }

      case 403: {
        const { error: toastError } = useToast()
        toastError(message || 'Acces refuse. Vous n\'avez pas les permissions necessaires.')
        router.push('/403')
        break
      }

      case 404: {
        const { error: toastError } = useToast()
        toastError(message || 'Ressource introuvable.')
        break
      }

      case 422: {
        const { warning } = useToast()
        warning(message || 'Donnees invalides. Verifiez le formulaire.')
        break
      }

      case 423: {
        // Changement de mot de passe imposé par le backend (PASSWORD_CHANGE_REQUIRED).
        if (router.currentRoute.value.name !== 'force-password-change') {
          router.push({ name: 'force-password-change' })
        }
        break
      }

      case 500: {
        const { error: toastError } = useToast()
        toastError(message || 'Erreur interne du serveur. Veuillez reessayer.')
        break
      }

      default: {
        if (!error.response) {
          const { error: toastError } = useToast()
          toastError('Erreur de connexion. Verifiez votre reseau.')
        }
      }
    }

    return Promise.reject(error)
  },
)

export function useApi() {
  return apiClient
}

export function getValidationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    return error.response.data?.errors || {}
  }
  return {}
}

export function getErrorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message || 'Une erreur est survenue.'
  }
  return 'Une erreur inattendue est survenue.'
}
