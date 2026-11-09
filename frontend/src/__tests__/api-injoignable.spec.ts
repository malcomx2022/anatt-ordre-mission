import { describe, it, expect, vi } from 'vitest'

/*
 * Connexion impossible sans réponse de l'API (« Erreur de connexion ») : l'écran
 * de connexion nomme l'adresse appelée et, après un déploiement qui a écrasé
 * runtime-config.js (apiUrl vide → repli http://localhost:8000), le signale.
 */

vi.mock('@/router', () => ({ default: { push: vi.fn(), currentRoute: { value: {} } } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: vi.fn() }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ error: vi.fn(), warning: vi.fn() }) }))

describe('diagnosticApiInjoignable', () => {
  it('signale un runtime-config.js vide en production', async () => {
    const { diagnosticApiInjoignable } = await import('@/composables/useApi')
    const msg = diagnosticApiInjoignable('http://localhost:8000', 'omsemo.anatt.bj')
    expect(msg).toContain("L'adresse de l'API n'est pas configurée")
    expect(msg).toContain('runtime-config.js')
  })

  it("nomme l'adresse de l'API qui ne répond pas", async () => {
    const { diagnosticApiInjoignable } = await import('@/composables/useApi')
    const msg = diagnosticApiInjoignable('https://api-omsemo.anatt.bj', 'omsemo.anatt.bj')
    expect(msg).toContain('https://api-omsemo.anatt.bj')
    expect(msg).toContain('CORS_ALLOWED_ORIGINS')
  })

  it('reste générique en développement local', async () => {
    const { diagnosticApiInjoignable } = await import('@/composables/useApi')
    expect(diagnosticApiInjoignable('http://localhost:8000', 'localhost')).toContain('ne répond pas')
  })
})
