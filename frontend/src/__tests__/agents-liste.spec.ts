import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'

/*
 * Écran Personnel : les cartes de compteurs (Actifs / MTN / MOOV) reflètent des
 * totaux GLOBAUX servis par l'API (/api/agents/stats), pas un décompte de la
 * seule page affichée.
 */

const list = vi.fn()
const stats = vi.fn()

vi.mock('../services/agents', () => ({
  agentsService: { list: (...a: unknown[]) => list(...a), stats: (...a: unknown[]) => stats(...a) },
}))
vi.mock('../composables/useAuth', () => ({ useAuth: () => ({ canManageAgents: () => true }) }))
vi.mock('../composables/useToast', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
vi.mock('../composables/useSearch', () => ({ useSearch: () => ({ query: ref(''), debouncedQuery: ref('') }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
  AppPagination: defineComponent({ setup: () => () => h('div') }),
  AppModal: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  AppButton: defineComponent({ setup: (_, { slots }) => () => h('button', slots.default?.()) }),
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

beforeEach(() => {
  list.mockReset(); stats.mockReset()
  // La page ne contient qu'UN agent MTN, mais les totaux globaux en comptent bien plus.
  list.mockResolvedValue({
    items: [{ id: 1, nom: 'A', prenoms: 'B', matricule: 'M1', categorie: 'INDICE_SUP_800', operateur: 'MTN', telephone: '0190000000', actif: true }],
    meta: { current_page: 1, last_page: 3, per_page: 15, total: 42 },
  })
  stats.mockResolvedValue({ total: 42, actifs: 40, inactifs: 2, mtn: 25, moov: 17 })
})

describe('AgentListView — compteurs globaux', () => {
  it('appelle /agents/stats et affiche les totaux globaux (pas la page)', async () => {
    const { default: View } = await import('../views/agents/AgentListView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    expect(stats).toHaveBeenCalled()
    const txt = w.text()
    // Global : 40 actifs, 25 MTN, 17 MOOV — impossible à dériver d'une page d'1 agent.
    expect(txt).toContain('40')
    expect(txt).toContain('25')
    expect(txt).toContain('17')
  })

  it('recompte avec les filtres (stats reçoit l’opérateur choisi)', async () => {
    const { default: View } = await import('../views/agents/AgentListView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()

    const selects = w.findAll('select')
    // 2e select = opérateur (catégorie, opérateur, statut).
    await selects[1]!.setValue('MOOV')
    await flushPromises()

    expect(stats).toHaveBeenLastCalledWith(expect.objectContaining({ operateur: 'MOOV' }))
  })
})
