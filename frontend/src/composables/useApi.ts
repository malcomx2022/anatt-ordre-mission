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

/**
 * Sur un téléchargement (responseType: 'blob'), un échec renvoie le corps
 * d'erreur EN BLOB, pas en JSON : error.response.data.message est alors
 * undefined et l'utilisateur ne voit qu'un message générique (« impossible de
 * télécharger »), jamais la vraie cause. On relit le blob et on le reconvertit
 * en objet { message, errors } pour que la suite du traitement le voie.
 */
export async function normaliserErreurBlob(error: AxiosError<ApiErrorResponse>): Promise<void> {
  const data = error.response?.data as unknown
  if (data instanceof Blob && data.type.includes('application/json')) {
    try {
      const texte = await data.text()
      error.response!.data = JSON.parse(texte)
    } catch {
      // Blob illisible ou non-JSON : on laisse le message générique.
    }
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<ApiErrorResponse>) => {
    await normaliserErreurBlob(error)
    const status = error.response?.status
    // Refus (422) : le motif par champ plutôt que « Donnees invalides. ».
    const message = motifRefus(error.response?.data) ?? error.response?.data?.message

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
        toastError(message || 'Accès refusé. Vous n\'avez pas les permissions nécessaires.')
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
        warning(message || 'Données invalides. Vérifiez le formulaire.')
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
        toastError(message || 'Erreur interne du serveur. Veuillez réessayer.')
        break
      }

      case 503: {
        // API en maintenance (php artisan down, pendant une mise à jour) : l'écran
        // Maintenance sonde l'API et revient à la page demandée dès qu'elle répond.
        const courante = router.currentRoute.value
        if (courante.name !== 'maintenance') {
          router.push({ name: 'maintenance', query: { redirect: courante.fullPath } })
        }
        break
      }

      default: {
        if (!error.response) {
          const { error: toastError } = useToast()
          toastError('Erreur de connexion. Vérifiez votre réseau.')
        }
      }
    }

    return Promise.reject(error)
  },
)

/**
 * Premier motif d'un refus de validation (422) : l'API répond « Donnees invalides. »
 * et met la vraie raison par champ dans `errors` (séparation des tâches, numéro
 * déjà attribué, part d'avance hors bornes…).
 */
function motifRefus(data: ApiErrorResponse | undefined): string | undefined {
  const motifs = data?.errors ? Object.values(data.errors) : []
  return motifs.find((m) => Array.isArray(m) && m.length > 0)?.[0]
}

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
    // Limite de requêtes (connexion au-delà des tentatives réglées, exports…) :
    // Laravel répond « Too Many Attempts. », en anglais.
    if (error.response?.status === 429) {
      return 'Trop de tentatives en peu de temps : patientez une minute avant de réessayer.'
    }
    return motifRefus(error.response?.data) || error.response?.data?.message || 'Une erreur est survenue.'
  }
  // Erreur levée par l'application elle-même (ex. assertValidBlobResponse, qui
  // y recopie le message du serveur) : son message est déjà destiné à l'usager.
  if (error instanceof Error && error.message) return error.message
  return 'Une erreur inattendue est survenue.'
}
