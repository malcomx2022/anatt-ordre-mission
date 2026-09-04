import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Barème (TauxListView) : la matrice n'affiche que les types de prise en charge
 * VALIDES pour la zone (repas ⇒ Intérieur ; hébergement ⇒ Extérieur), et
 * rappelle l'invariant « II = III ».
 */

const list = vi.fn()
vi.mock('../services/baremes', () => ({ baremesService: { list: (...a: unknown[]) => list(...a) } }))
vi.mock('../composables/useAuth', () => ({ useAuth: () => ({ canManageBaremes: () => true }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots.default?.(), slots['header-actions']?.()]) }),
  AppCard: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/taux/TauxListView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  return w
}

function boutonZone(w: Awaited<ReturnType<typeof monter>>, texte: string) {
  const b = w.findAll('button').find((x) => x.text().includes(texte))
  if (!b) throw new Error(`Bouton zone « ${texte} » introuvable`)
  return b
}

beforeEach(() => {
  list.mockReset()
  list.mockResolvedValue([])
})

describe('TauxListView — types par zone', () => {
  it('n’affiche que les types INTÉRIEUR (repas) par défaut', async () => {
    const w = await monter()
    const entetes = w.findAll('th').map((t) => t.text())
    expect(entetes).toContain('Un repas')
    expect(entetes).toContain('Deux repas')
    expect(entetes).toContain('Journée complète')
    expect(entetes).not.toContain('Logé seul')
    expect(entetes).not.toContain('Nourri seul')
  })

  it('bascule sur les types EXTÉRIEUR (hébergement) pour une zone externe', async () => {
    const w = await monter()
    await boutonZone(w, 'Zone II').trigger('click')
    await flushPromises()

    const entetes = w.findAll('th').map((t) => t.text())
    expect(entetes).toContain('Logé + nourri')
    expect(entetes).toContain('Logé seul')
    expect(entetes).toContain('Nourri seul')
    expect(entetes).not.toContain('Un repas')
  })

  it('rappelle l’invariant II = III', async () => {
    const w = await monter()
    expect(w.text()).toContain('identiques')
  })
})
