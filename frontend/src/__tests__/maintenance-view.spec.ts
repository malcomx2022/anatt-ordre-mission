import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import axios from 'axios'

/*
 * Page de maintenance (SYS-03 ; revue d'octobre 2026, §4 « Page de maintenance ») :
 * - `?fin=<ISO>` : compte à rebours HH:MM:SS jusqu'à la fin annoncée ;
 * - revérification AUTOMATIQUE de l'API toutes les 30 s (GET /api/auth/me) ;
 * - dès que l'API répond (hors 5xx, même 401), retour à la page demandée (`?redirect=`).
 */
vi.mock('@/composables/useApi', () => ({ useApi: () => ({ defaults: { baseURL: 'http://api.test' } }) }))

const get = vi.spyOn(axios, 'get')

const vide = { render: () => null }
function routeur() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: vide },
      { path: '/ordres', name: 'ordres', component: vide },
      { path: '/maintenance', name: 'maintenance', component: vide },
    ],
  })
}

async function monter(url: string) {
  const router = routeur()
  await router.push(url)
  const { default: View } = await import('../views/system/MaintenanceView.vue')
  const wrapper = mount(View, { global: { plugins: [router] } })
  return { wrapper, router }
}

const DEBUT = new Date('2026-10-29T10:00:00Z')

beforeEach(() => {
  vi.useFakeTimers()
  vi.setSystemTime(DEBUT)
  get.mockReset()
  get.mockResolvedValue({ status: 503 })
})
afterEach(() => {
  vi.useRealTimers()
})

describe('MaintenanceView — compte à rebours', () => {
  it('affiche le temps restant jusqu’à ?fin= et le décompte chaque seconde', async () => {
    const fin = new Date(DEBUT.getTime() + (1 * 3600 + 2 * 60 + 5) * 1000).toISOString()
    const { wrapper } = await monter(`/maintenance?fin=${encodeURIComponent(fin)}`)

    await vi.advanceTimersByTimeAsync(1000)
    expect(wrapper.get('[data-test="countdown-fin"]').text()).toBe('01:02:04')

    await vi.advanceTimersByTimeAsync(4000)
    expect(wrapper.get('[data-test="countdown-fin"]').text()).toBe('01:02:00')
    wrapper.unmount()
  })

  it('n’affiche aucun compte à rebours sans ?fin= (ou fin invalide / passée)', async () => {
    for (const url of ['/maintenance', '/maintenance?fin=demain', '/maintenance?fin=2026-10-29T09:00:00Z']) {
      const { wrapper } = await monter(url)
      await vi.advanceTimersByTimeAsync(1000)
      expect(wrapper.find('[data-test="countdown-fin"]').exists()).toBe(false)
      wrapper.unmount()
    }
  })
})

describe('MaintenanceView — revérification automatique toutes les 30 s', () => {
  it('décompte 30 s puis sonde l’API ; tant qu’elle est en 5xx, reste et relance 30 s', async () => {
    const { wrapper, router } = await monter('/maintenance?redirect=/ordres')
    expect(wrapper.get('[data-test="countdown-retry"]').text()).toContain('30 s')

    await vi.advanceTimersByTimeAsync(1000)
    expect(wrapper.get('[data-test="countdown-retry"]').text()).toContain('29 s')

    await vi.advanceTimersByTimeAsync(28000)
    expect(get).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(1000) // 30e seconde : vérification
    expect(get).toHaveBeenCalledTimes(1)
    expect(get.mock.calls[0]![0]).toBe('/api/auth/me')
    expect(get.mock.calls[0]![1]).toMatchObject({ baseURL: 'http://api.test' })
    expect(router.currentRoute.value.name).toBe('maintenance')
    expect(wrapper.get('[data-test="countdown-retry"]').text()).toContain('30 s')

    await vi.advanceTimersByTimeAsync(30000)
    expect(get).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('revient à la page demandée (?redirect=) dès que l’API répond', async () => {
    get.mockResolvedValueOnce({ status: 503 }).mockResolvedValue({ status: 401 })
    const { wrapper, router } = await monter('/maintenance?redirect=' + encodeURIComponent('/ordres?statut=VALIDE'))

    await vi.advanceTimersByTimeAsync(30000)
    expect(router.currentRoute.value.name).toBe('maintenance')

    await vi.advanceTimersByTimeAsync(30000)
    expect(router.currentRoute.value.fullPath).toBe('/ordres?statut=VALIDE')

    // Retour effectué : le minuteur est arrêté, plus aucune sonde.
    await vi.advanceTimersByTimeAsync(120000)
    expect(get).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('revient à l’accueil sans ?redirect= ou avec une redirection externe', async () => {
    get.mockResolvedValue({ status: 200 })
    for (const url of ['/maintenance', '/maintenance?redirect=' + encodeURIComponent('https://exemple.org')]) {
      const { wrapper, router } = await monter(url)
      await vi.advanceTimersByTimeAsync(30000)
      expect(router.currentRoute.value.fullPath).toBe('/')
      wrapper.unmount()
    }
  })

  it('une erreur réseau compte comme indisponible', async () => {
    get.mockRejectedValue(new Error('Network Error'))
    const { wrapper, router } = await monter('/maintenance?redirect=/ordres')
    await vi.advanceTimersByTimeAsync(30000)
    expect(get).toHaveBeenCalledTimes(1)
    expect(router.currentRoute.value.name).toBe('maintenance')
    wrapper.unmount()
  })
})
