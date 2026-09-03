import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Écran États de paiement (file de travail) : le filtre par statut.
 *
 * On verrouille le comportement d'ouverture : un acteur DÉDIÉ du circuit
 * (une seule étape) atterrit sur SA file ; un profil de supervision qui cumule
 * plusieurs étapes (ADMIN, SUPER_ADMIN) n'est PAS piégé sur une file unique
 * vide — il voit tous les états. C'est le bug « certains filtres n'affichent
 * pas de données pourtant présentes » (ex. Visé DAF) pour un super admin.
 */

const list = vi.fn()

vi.mock('../services/etats', () => ({
  etatsService: { list: (...args: unknown[]) => list(...args) },
}))

// Les droits de circuit sont pilotés test par test.
let droits = { valider: false, viser: false, approuver: false }
vi.mock('../composables/useAuth', () => ({
  useAuth: () => ({
    canValiderOM: () => droits.valider,
    canViserOM: () => droits.viser,
    canApprouverOM: () => droits.approuver,
  }),
}))

// AppLayout tire toute la coquille (sidebar, store, router) : on la neutralise.
const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/etats/EtatListView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}

/** Le statut demandé au service lors du dernier appel `list`. */
function dernierStatut() {
  return list.mock.calls.at(-1)?.[0]?.statut
}

beforeEach(() => {
  list.mockReset()
  list.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
  droits = { valider: false, viser: false, approuver: false }
})

describe('EtatListView — file de travail à l’ouverture', () => {
  it('ouvre le DG sur sa file « Visé DAF »', async () => {
    droits = { valider: false, viser: false, approuver: true }
    await monter()
    expect(dernierStatut()).toBe('VISA_DAF')
  })

  it('ouvre le contrôleur sur sa file « Brouillon »', async () => {
    droits = { valider: true, viser: false, approuver: false }
    await monter()
    expect(dernierStatut()).toBe('BROUILLON')
  })

  it('ouvre le DAF sur sa file « Contrôlé »', async () => {
    droits = { valider: false, viser: true, approuver: false }
    await monter()
    expect(dernierStatut()).toBe('CONTROLE')
  })

  it('n’enferme PAS un super admin (multi-étapes) sur une file unique', async () => {
    droits = { valider: true, viser: true, approuver: true }
    await monter()
    // Aucun statut imposé : tous les états sont visibles (« Tous »).
    expect(dernierStatut()).toBeUndefined()
  })

  it('laisse un profil sans rôle de circuit sur « Tous »', async () => {
    droits = { valider: false, viser: false, approuver: false }
    await monter()
    expect(dernierStatut()).toBeUndefined()
  })
})
