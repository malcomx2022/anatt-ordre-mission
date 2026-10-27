import { describe, it, expect, vi, beforeEach } from 'vitest'

/*
 * Mode maintenance de l'API (`php artisan down`, exigé pendant la mise à jour 1.4) :
 * toute route répond 503. Le SPA doit ouvrir l'écran Maintenance (qui sonde l'API
 * et revient à la page demandée), au lieu de laisser des écrans en échec silencieux.
 */
const { push, route } = vi.hoisted(() => ({
  push: vi.fn(),
  route: { value: { name: 'ordres', fullPath: '/ordres?statut=VALIDE' } },
}))
vi.mock('@/router', () => ({ default: { push: (...a: unknown[]) => push(...a), currentRoute: route } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: () => {} }) }))

import { useApi } from '../composables/useApi'

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const intercepteurErreur = () => (useApi().interceptors.response as any).handlers[0].rejected as (e: unknown) => Promise<unknown>
const erreur503 = { isAxiosError: true, response: { status: 503, data: { message: 'Service Unavailable' } } }

describe('API en maintenance (503)', () => {
  beforeEach(() => {
    push.mockReset()
    route.value = { name: 'ordres', fullPath: '/ordres?statut=VALIDE' }
  })

  it('ouvre l’écran Maintenance en gardant la page demandée', async () => {
    await expect(intercepteurErreur()(erreur503)).rejects.toBe(erreur503)
    expect(push).toHaveBeenCalledWith({ name: 'maintenance', query: { redirect: '/ordres?statut=VALIDE' } })
  })

  it('ne relance pas la navigation si l’écran Maintenance est déjà affiché', async () => {
    route.value = { name: 'maintenance', fullPath: '/maintenance' }
    await expect(intercepteurErreur()(erreur503)).rejects.toBe(erreur503)
    expect(push).not.toHaveBeenCalled()
  })
})
