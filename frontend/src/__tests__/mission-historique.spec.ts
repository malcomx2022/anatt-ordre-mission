import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Historique d'un OM (OM-08) : l'écran lisait des champs et des codes d'action
 * qui n'existent plus (numero, details, VALIDATE_N1…). On verrouille la
 * lecture du journal réel : reference_semo, colonne `donnees`, codes *_OM.
 */

const get = vi.fn((url: string) =>
  Promise.resolve(url.endsWith('/historique')
    ? { data: { data: [{
        action: 'AMEND_OM', created_at: '2026-10-20T10:00:00Z', ip_address: '10.0.0.1',
        user: { name: 'Gestion OM', role: 'GESTIONNAIRE_OM' },
        donnees: { nb_jours: 1, delta: -77000, motif: 'Retour anticipé constaté par avenant' },
      }] } }
    : { data: { data: { id: 10, reference_semo: 'SEMO-2026-0042' } } }),
)

vi.mock('@/composables/useApi', () => ({ useApi: () => ({ get }) }))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '10' } }), useRouter: () => ({ push: vi.fn() }) }))

const stubs = {
  AppLayout: defineComponent({ props: ['title', 'subtitle'], setup: (p, { slots }) => () => h('div', [p.title, p.subtitle, slots.default?.()]) }),
  AppButton: defineComponent({ setup: (_, { slots }) => () => h('button', slots.default?.()) }),
}

describe('MissionHistoryView', () => {
  it('affiche la référence SEMO, le libellé de l’amendement et son motif', async () => {
    const { default: View } = await import('../views/missions/MissionHistoryView.vue')
    const wrapper = mount(View, { global: { stubs } })
    await flushPromises()

    const texte = wrapper.text()
    expect(texte).toContain('SEMO-2026-0042')
    expect(texte).toContain('Amendement (avenant)')
    expect(texte).toContain('Retour anticipé constaté par avenant')
    expect(texte).not.toContain('AMEND_OM')
  })
})
