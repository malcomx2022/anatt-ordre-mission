import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Écran 403 : affiche la/les permission(s) RÉELLEMENT exigée(s) par la route
 * (transmise(s) par le guard via ?required=...), avec leur libellé lisible —
 * fini le « administrateur » codé en dur. Sans info, message générique.
 */

let query: Record<string, string> = {}
vi.mock('vue-router', () => ({
  useRoute: () => ({ query }),
  useRouter: () => ({ back: vi.fn() }),
}))

const stubs = {
  'router-link': defineComponent({ props: ['to'], setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/system/ForbiddenView.vue')
  return mount(View, { global: { stubs } })
}

beforeEach(() => {
  query = {}
})

describe('ForbiddenView', () => {
  it('affiche le libellé de la permission exigée', async () => {
    query = { required: 'paiements.generer' }
    const w = await monter()
    expect(w.text()).toContain('Permission requise')
    expect(w.text()).toContain('Générer les fichiers de paiement')
    // Ne colle plus l'ancien libellé codé en dur.
    expect(w.text()).not.toContain('Permission requise : administrateur')
  })

  it('accorde le pluriel pour plusieurs permissions', async () => {
    query = { required: 'om.viser,om.approuver' }
    const w = await monter()
    expect(w.text()).toContain('Permissions requises')
    expect(w.text()).toContain("Viser l'état de paiement (DAF)")
    expect(w.text()).toContain("Approuver l'état de paiement (DG)")
  })

  it('reste générique quand aucune permission n’est transmise', async () => {
    query = {}
    const w = await monter()
    expect(w.text()).not.toContain('Permission requise')
    expect(w.text()).toContain('Acces interdit')
  })
})
