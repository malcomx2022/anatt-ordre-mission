import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { createRouter, createMemoryHistory } from 'vue-router'

/*
 * Minuteurs annulés au démontage (revue d'octobre 2026, §4 « Minuteur non annulé ») :
 * un écran quitté ne doit plus rien faire. Avant le correctif, la recherche débouncée
 * des Régularisations rechargeait la liste APRÈS le démontage (requête fantôme).
 * On verrouille ici chaque composant qui pose un setInterval / setTimeout :
 * après unmount(), plus aucun appel n'est fait, même en laissant filer le temps.
 * (Le suivi des gros lots de PaiementGeneration l'est dans paiements-async.spec.ts.)
 */

// ── Régularisations : recherche débouncée (300 ms) ──
const regularisations = vi.fn()
vi.mock('@/services/paiements', () => ({
  paiementsService: {
    regularisations: (...a: unknown[]) => regularisations(...a),
    recouvrer: vi.fn(),
    documentRetenue: vi.fn(),
  },
}))
vi.mock('@/composables/useAuth', () => ({ useAuth: () => ({ can: () => true }) }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ defaults: { baseURL: '' } }),
  getErrorMessage: (e: unknown) => (e instanceof Error ? e.message : 'Erreur'),
}))

// ── Cloche des notifications : rafraîchissement toutes les 60 s ──
const chargerNotifs = vi.fn()
vi.mock('@/stores/notifications', () => ({
  useNotificationsStore: () => reactive({
    notifications: [], unreadCount: 0, chargement: false,
    charger: chargerNotifs, marquerLu: vi.fn(), marquerToutLu: vi.fn(), vider: vi.fn(),
  }),
}))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => reactive({ user: { name: 'Awa Dossou', role: 'ADMIN' } }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
}

beforeEach(() => {
  vi.useFakeTimers()
  regularisations.mockReset()
  chargerNotifs.mockReset()
  regularisations.mockResolvedValue({
    page: { items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    totalARecouvrer: 0,
  })
})
afterEach(() => {
  vi.useRealTimers()
})

describe('PaiementRegularisationView — recherche débouncée', () => {
  it('ne recharge pas la liste si l’écran est quitté pendant la frappe', async () => {
    const { default: View } = await import('../views/paiements/PaiementRegularisationView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    expect(regularisations).toHaveBeenCalledTimes(1) // chargement initial

    await w.get('input[type="search"], input[type="text"], input:not([type])').setValue('DADA')
    w.unmount() // avant la fin des 300 ms

    await vi.advanceTimersByTimeAsync(1000)
    expect(regularisations).toHaveBeenCalledTimes(1)
  })

  it('recharge bien après la pause de frappe tant que l’écran est affiché (témoin)', async () => {
    const { default: View } = await import('../views/paiements/PaiementRegularisationView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    await w.get('input[type="search"], input[type="text"], input:not([type])').setValue('DADA')
    await vi.advanceTimersByTimeAsync(300)
    expect(regularisations).toHaveBeenCalledTimes(2)
    w.unmount()
  })
})

describe('AppTopBar — rafraîchissement de la cloche', () => {
  it('cesse d’interroger les notifications une fois démonté', async () => {
    const { default: TopBar } = await import('../components/shared/AppTopBar.vue')
    const w = mount(TopBar, { props: { title: 'Tableau de bord' } })
    expect(chargerNotifs).toHaveBeenCalledTimes(1) // au montage

    await vi.advanceTimersByTimeAsync(60000)
    expect(chargerNotifs).toHaveBeenCalledTimes(2) // témoin : le minuteur tourne

    w.unmount()
    await vi.advanceTimersByTimeAsync(5 * 60000)
    expect(chargerNotifs).toHaveBeenCalledTimes(2)
  })
})

describe('MaintenanceView — revérification de l’API', () => {
  it('ne sonde plus l’API une fois l’écran quitté', async () => {
    const axios = (await import('axios')).default
    const get = vi.spyOn(axios, 'get').mockResolvedValue({ status: 503 })
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { render: () => null } },
        { path: '/maintenance', component: { render: () => null } },
      ],
    })
    await router.push('/maintenance')
    const { default: View } = await import('../views/system/MaintenanceView.vue')
    const w = mount(View, { global: { plugins: [router] } })

    w.unmount()
    await vi.advanceTimersByTimeAsync(5 * 30000)
    expect(get).not.toHaveBeenCalled()
    get.mockRestore()
  })
})

describe('AppCombobox — recherche distante débouncée', () => {
  it('n’appelle pas le fetcher si le composant est démonté avant la fin du délai', async () => {
    const fetcher = vi.fn().mockResolvedValue([])
    const { default: Combo } = await import('../components/shared/AppCombobox.vue')
    const w = mount(Combo, { props: { modelValue: null, fetcher, minChars: 1, debounce: 250 } })
    await w.get('button').trigger('click')
    await w.get('input[role="combobox"]').setValue('dada')

    w.unmount()
    await vi.advanceTimersByTimeAsync(1000)
    expect(fetcher).not.toHaveBeenCalled()
  })
})

describe('AppNavProgress — barre de navigation', () => {
  function routeur() {
    return createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { render: () => null } },
        { path: '/a', component: { render: () => null } },
        { path: '/b', component: { render: () => null } },
      ],
    })
  }

  it('laisse un minuteur pendant une navigation tant qu’elle est montée (témoin)', async () => {
    const router = routeur()
    const { default: Barre } = await import('../components/shared/AppNavProgress.vue')
    const w = mount(Barre, { global: { plugins: [router] } })
    await router.push('/a')
    expect(vi.getTimerCount()).toBeGreaterThan(0) // fondu de fin (240 ms)
    w.unmount()
  })

  it('n’arme plus aucun minuteur aux navigations suivant le démontage', async () => {
    const router = routeur()
    const { default: Barre } = await import('../components/shared/AppNavProgress.vue')
    const w = mount(Barre, { global: { plugins: [router] } })
    await router.push('/a')
    await vi.advanceTimersByTimeAsync(1000)
    w.unmount()
    expect(vi.getTimerCount()).toBe(0)

    await router.push('/b')
    expect(vi.getTimerCount()).toBe(0)
  })
})
