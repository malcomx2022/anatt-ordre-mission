import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Module Rapports : agrégation des trop-perçus par opérateur, présence de la
 * carte Trop-perçus sur le tableau de bord, et garde-fou de plage de dates
 * (Au < Du) sur l'export.
 */

const tropPercus = vi.fn()
const mensuel = vi.fn()
const parAgent = vi.fn()
const exportFn = vi.fn()
const telechargerExport = vi.fn()
const toastError = vi.fn()
const toastSuccess = vi.fn()

vi.mock('../services/rapports', () => ({
  rapportsService: {
    tropPercus: (...a: unknown[]) => tropPercus(...a),
    mensuel: (...a: unknown[]) => mensuel(...a),
    parAgent: (...a: unknown[]) => parAgent(...a),
    parOperateur: vi.fn(async () => []),
    parZone: vi.fn(async () => []),
    parImputation: vi.fn(async () => []),
    export: (...a: unknown[]) => exportFn(...a),
    telechargerExport: (...a: unknown[]) => telechargerExport(...a),
  },
}))
vi.mock('../composables/useToast', () => ({
  useToast: () => ({ error: toastError, success: toastSuccess, warning: vi.fn() }),
}))
// vue-chartjs <Bar> : canvas indisponible en jsdom → on neutralise.
vi.mock('vue-chartjs', () => ({ Bar: defineComponent({ setup: () => () => h('canvas') }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
  AppCard: defineComponent({ setup: (_, { slots }) => () => h('div', { class: 'card' }, slots.default?.()) }),
  AppAlert: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  AppButton: defineComponent({
    props: ['disabled', 'loading'],
    emits: ['click'],
    setup: (p, { slots, emit }) => () => h('button', {
      disabled: !!p.disabled,
      onClick: () => { if (!p.disabled) emit('click') },
    }, slots.default?.()),
  }),
  'router-link': defineComponent({ props: ['to'], setup: (p, { slots }) => () => h('a', { href: p.to }, slots.default?.()) }),
}

beforeEach(() => {
  vi.clearAllMocks()
  mensuel.mockResolvedValue({
    mois: '2026-09', periode: { debut: '2026-09-01', fin: '2026-09-30' },
    synthese: { nb_versements: 0, montant_total: 0 },
    par_operateur: [], par_tranche: [], par_statut: [],
  })
  parAgent.mockResolvedValue([])
})

describe('RapportTropPercusView', () => {
  it('agrège les trop-perçus par opérateur et affiche les KPIs', async () => {
    tropPercus.mockResolvedValue({
      a_recouvrer: { nb: 2, montant: 30000 },
      recouvre: { nb: 1, montant: 5000 },
      total: { nb: 3, montant: 35000 },
      par_operateur: [
        { operateur: 'MTN', statut: 'A_RECOUVRER', nb: 1, montant: 10000 },
        { operateur: 'MTN', statut: 'RECOUVRE', nb: 1, montant: 5000 },
        { operateur: 'MOOV', statut: 'A_RECOUVRER', nb: 1, montant: 20000 },
      ],
    })
    const { default: View } = await import('../views/rapports/RapportTropPercusView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    expect(w.text()).toContain((35000).toLocaleString('fr-FR'))
    expect(w.text()).toContain('MTN')
    expect(w.text()).toContain('MOOV')
    // MTN : 10 000 à recouvrer + 5 000 recouvré agrégés sur une ligne.
    expect(w.text()).toContain((10000).toLocaleString('fr-FR'))
    expect(w.text()).toContain((20000).toLocaleString('fr-FR'))
  })
})

describe('RapportDashboardView', () => {
  it('expose la carte Trop-perçus', async () => {
    const { default: View } = await import('../views/rapports/RapportDashboardView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    const liens = w.findAll('a').map((a) => a.attributes('href'))
    expect(liens).toContain('/rapports/trop-percus')
    expect(w.text()).toContain('Trop-perçus')
  })
})

describe('RapportExportView — garde de plage de dates', () => {
  it('refuse l’export quand « Au » précède « Du »', async () => {
    const { default: View } = await import('../views/rapports/RapportExportView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    const dates = w.findAll('input[type="date"]')
    await dates[0]!.setValue('2026-02-01') // Du
    await dates[1]!.setValue('2026-01-01') // Au (antérieur → invalide)

    // Le bouton est désactivé (plage invalide) → un clic ne déclenche pas l'export.
    await w.get('button').trigger('click') // Générer
    await flushPromises()

    expect(exportFn).not.toHaveBeenCalled()
    expect(w.text()).toContain('postérieure ou égale à « Du »') // une même date est acceptée (after_or_equal côté API)
  })
})
