import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Tableau de bord : les 4 KPIs se peuplent depuis le rapport mensuel + le total
 * d'OM actifs, le graphe par opérateur est rendu, et le bloc « Actions rapides »
 * (composant QuickActions, longtemps orphelin) est câblé et filtré par permission.
 */

const mensuel = vi.fn()
const list = vi.fn()
const can = vi.fn()

vi.mock('../services/rapports', () => ({ rapportsService: { mensuel: (...a: unknown[]) => mensuel(...a) } }))
vi.mock('../services/ordres', () => ({ ordresService: { list: (...a: unknown[]) => list(...a) } }))
vi.mock('../composables/useAuth', () => ({ useAuth: () => ({ can: (...a: unknown[]) => can(...a) }) }))
// vue-chartjs <Bar> : canvas indisponible en jsdom → on neutralise.
vi.mock('vue-chartjs', () => ({ Bar: defineComponent({ setup: () => () => h('canvas', { class: 'chart' }) }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.()]) }),
  AppCard: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  MissionStatusBadge: defineComponent({ props: ['statut'], setup: (p) => () => h('span', p.statut) }),
  'router-link': defineComponent({ props: ['to'], setup: (p, { slots }) => () => h('a', { href: p.to }, slots.default?.()) }),
}

beforeEach(() => {
  mensuel.mockReset(); list.mockReset(); can.mockReset()
  can.mockReturnValue(true)
  mensuel.mockResolvedValue({
    mois: '2026-09',
    synthese: { nb_versements: 12, montant_total: 3400000 },
    par_operateur: [{ operateur: 'MTN', montant_total: 2000000 }, { operateur: 'MOOV', montant_total: 1400000 }],
    par_tranche: [], par_statut: [],
  })
  list.mockResolvedValue({
    items: [{ id: 7, reference_semo: 'OM-2026-7', statut: 'VALIDE', agent: { nom: 'DADA', prenoms: 'Richard' } }],
    meta: { current_page: 1, last_page: 1, per_page: 5, total: 42 },
  })
})

describe('DashboardView', () => {
  async function monter() {
    const { default: View } = await import('../views/dashboard/DashboardView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    return w
  }

  it('ne compte que les OM actifs (exclut rejetés/annulés)', async () => {
    await monter()
    expect(list).toHaveBeenCalledWith(expect.objectContaining({ actifs: true, per_page: 5 }))
  })

  it('affiche les 4 KPIs peuplés et le graphe par opérateur', async () => {
    const w = await monter()
    const t = w.text()
    expect(t).toContain('Ordres de mission actifs')
    expect(t).toContain('42') // total d'OM actifs
    expect(t).toContain('Versements confirmés (mois)')
    expect(t).toContain('12') // nb versements confirmés
    expect(t).toContain('Opérateurs actifs')
    expect(w.find('canvas.chart').exists()).toBe(true)
  })

  it('liste les OM récents avec un lien vers la fiche', async () => {
    const w = await monter()
    expect(w.text()).toContain('OM-2026-7')
    expect(w.text()).toContain('DADA Richard')
  })
})

describe('QuickActions — filtrage par permission', () => {
  async function monter() {
    const { default: View } = await import('../components/dashboard/QuickActions.vue')
    return mount(View, { global: { stubs } })
  }

  it('ne montre que les actions autorisées', async () => {
    can.mockImplementation((p: string) => p === 'rapports.consulter')
    const w = await monter()
    const liens = w.findAll('a').map((a) => a.text())
    expect(liens).toEqual(['Rapports'])
  })

  it('masque tout le bloc quand aucune action n’est permise', async () => {
    can.mockReturnValue(false)
    const w = await monter()
    expect(w.text()).not.toContain('Actions rapides')
    expect(w.findAll('a')).toHaveLength(0)
  })
})
