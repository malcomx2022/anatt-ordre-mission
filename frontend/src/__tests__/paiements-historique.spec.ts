import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Historique des paiements : affiche le TOTAL des montants confirmés de la
 * période (servi par l'API) et bloque une plage de dates incohérente (Au < Du).
 */

const historique = vi.fn()
vi.mock('../services/paiements', () => ({
  paiementsService: { historique: (...a: unknown[]) => historique(...a) },
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  AppPagination: defineComponent({ setup: () => () => h('div') }),
  OperateurBadge: defineComponent({ props: ['operateur'], setup: (p) => () => h('span', p.operateur) }),
}

function pageDe(total: number) {
  return {
    page: { items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 } },
    totalMontant: total,
  }
}

async function monter() {
  const { default: View } = await import('../views/paiements/PaiementHistoriqueView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  return w
}

beforeEach(() => {
  historique.mockReset()
  historique.mockResolvedValue(pageDe(350000))
})

describe('PaiementHistoriqueView', () => {
  it('affiche le total confirmé de la période', async () => {
    const w = await monter()
    expect(historique).toHaveBeenCalled()
    expect(w.text()).toContain((350000).toLocaleString('fr-FR'))
  })

  it('bloque une plage incohérente (Au < Du) : pas d’appel API pour la combinaison invalide', async () => {
    const w = await monter()
    historique.mockClear()

    const dates = w.findAll('input[type="date"]')
    await dates[0]!.setValue('2026-02-01') // Du (encore valide seul)
    await dates[1]!.setValue('2026-01-01') // Au antérieur → plage invalide
    await flushPromises()

    // Le garde-fou empêche l'appel portant la combinaison invalide (date_fin définie).
    const appelInvalide = historique.mock.calls.some((c) => (c[0] as Record<string, unknown>)?.date_fin)
    expect(appelInvalide).toBe(false)
    expect(w.text()).toContain('postérieure')
  })
})
