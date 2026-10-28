import { describe, it, expect, vi } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'

/*
 * Limite de requêtes atteinte (429) : connexion au-delà des tentatives réglées
 * dans Paramètres › Sécurité, exports, génération. Laravel répond en anglais
 * (« Too Many Attempts. ») : l'utilisateur doit lire un message en français.
 */

vi.mock('@/router', () => ({ default: { push: vi.fn(), currentRoute: { value: {} } } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: vi.fn() }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ error: vi.fn(), warning: vi.fn() }) }))

function erreur(status: number, message?: string): AxiosError {
  const config = { headers: new AxiosHeaders() }
  return new AxiosError('x', 'ERR', config, null, {
    status, statusText: '', headers: {}, config, data: message ? { message } : {},
  })
}

describe('getErrorMessage — 429', () => {
  it('traduit la limite de tentatives en français', async () => {
    const { getErrorMessage } = await import('@/composables/useApi')
    expect(getErrorMessage(erreur(429, 'Too Many Attempts.'))).toBe(
      'Trop de tentatives en peu de temps : patientez une minute avant de réessayer.',
    )
  })

  it('garde le message du serveur pour les autres erreurs', async () => {
    const { getErrorMessage } = await import('@/composables/useApi')
    expect(getErrorMessage(erreur(422, 'Identifiants incorrects.'))).toBe('Identifiants incorrects.')
  })
})
