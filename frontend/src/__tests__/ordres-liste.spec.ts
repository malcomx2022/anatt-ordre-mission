import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'

/*
 * Liste des ordres de mission : le filtre « Statut » ne propose que des statuts
 * RÉELLEMENT produits sur un OM (IMPORTE/VALIDE/REJETE/SOLDE/ANNULE). VERIFIE et
 * VISA_DAF sont hérités et ne vivent plus que sur l'état → options mortes.
 * La plage de dates (Au < Du) est gardée.
 */

const list = vi.fn()
vi.mock('../services/ordres', () => ({ ordresService: { list: (...a: unknown[]) => list(...a) } }))
vi.mock('../composables/useAuth', () => ({ useAuth: () => ({ canCreerMissions: () => true }) }))
vi.mock('../composables/useSearch', () => ({ useSearch: () => ({ query: ref(''), debouncedQuery: ref('') }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
  AppPagination: defineComponent({ setup: () => () => h('div') }),
  MissionStatusBadge: defineComponent({ props: ['statut'], setup: (p) => () => h('span', p.statut) }),
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/missions/MissionListView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  return w
}

function statutOptions(w: Awaited<ReturnType<typeof monter>>) {
  return w.findAll('select')[0]!.findAll('option').map((o) => o.attributes('value')).filter((v): v is string => !!v)
}

beforeEach(() => {
  list.mockReset()
  list.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
})

describe('MissionListView — filtres', () => {
  it('ne propose que les statuts OM réellement produits', async () => {
    const opts = statutOptions(await monter())
    for (const vivant of ['IMPORTE', 'VALIDE', 'REJETE', 'SOLDE', 'ANNULE']) expect(opts).toContain(vivant)
    for (const mort of ['VERIFIE', 'VISA_DAF']) expect(opts).not.toContain(mort)
  })

  it('bloque une plage incohérente (Au < Du)', async () => {
    const w = await monter()
    list.mockClear()
    const dates = w.findAll('input[type="date"]')
    await dates[0]!.setValue('2026-02-01') // Du
    await dates[1]!.setValue('2026-01-01') // Au antérieur
    await flushPromises()

    const appelInvalide = list.mock.calls.some((c) => (c[0] as Record<string, unknown>)?.date_fin)
    expect(appelInvalide).toBe(false)
    expect(w.text()).toContain('postérieure ou égale à la date de début')
  })
})
