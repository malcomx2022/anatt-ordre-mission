import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

import PaiementStatusBadge from '../components/paiements/PaiementStatusBadge.vue'
import type { Regularisation } from '../types'

/*
 * Écran Régularisations (Phase 5) : liste les trop-perçus (reliquats négatifs
 * après amendement) et permet de constater leur recouvrement. On verrouille le
 * rendu des lignes, le total « à recouvrer » et l'action de recouvrement.
 */

const A_RECOUVRER: Regularisation = {
  versement_id: 7,
  ordre_mission_id: 42,
  reference_semo: 'OM-2026-001',
  beneficiaire: 'DADA Richard',
  mission: 'Atelier régional — BOHICON',
  operateur: 'MTN',
  telephone: '0167000000',
  montant_a_recouvrer: 54000,
  statut: 'A_RECOUVRER',
}

const regularisations = vi.fn()
const recouvrer = vi.fn()
const documentRetenue = vi.fn()

vi.mock('../services/paiements', () => ({
  paiementsService: {
    regularisations: (...args: unknown[]) => regularisations(...args),
    recouvrer: (...args: unknown[]) => recouvrer(...args),
    documentRetenue: (...args: unknown[]) => documentRetenue(...args),
  },
}))

// L'agent financier peut recouvrer (permission paiements.confirmer).
vi.mock('../composables/useAuth', () => ({
  useAuth: () => ({ can: (p: string) => p === 'paiements.confirmer' || p === 'paiements.consulter' }),
}))

// AppLayout tire toute la coquille (sidebar, store, router) : on la neutralise.
const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
}

async function monter() {
  const { default: View } = await import('../views/paiements/PaiementRegularisationView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  regularisations.mockReset()
  recouvrer.mockReset()
  documentRetenue.mockReset()
})

/** Enveloppe la nouvelle forme paginée renvoyée par le service. */
function pageDe(items: Regularisation[], totalARecouvrer = 0) {
  return {
    page: { items, meta: { current_page: 1, last_page: 1, per_page: 20, total: items.length } },
    totalARecouvrer,
  }
}

/** Le bouton d'une ligne portant ce libellé (désambiguïse « Document » / « Marquer recouvré »). */
function bouton(wrapper: Awaited<ReturnType<typeof monter>>, texte: string) {
  const b = wrapper.findAll('button').find((x) => x.text().includes(texte))
  if (!b) throw new Error(`Bouton « ${texte} » introuvable`)
  return b
}

describe('PaiementRegularisationView', () => {
  it('affiche une ligne à recouvrer et le total', async () => {
    regularisations.mockResolvedValue(pageDe([A_RECOUVRER], 54000))

    const wrapper = await monter()

    expect(wrapper.text()).toContain('DADA Richard')
    // toLocaleString('fr-FR') insère une espace insécable : on formate pareil.
    expect(wrapper.text()).toContain((54000).toLocaleString('fr-FR'))
    expect(wrapper.text()).toContain('À recouvrer')
  })

  it('marque une régularisation recouvrée et recharge la liste', async () => {
    regularisations
      .mockResolvedValueOnce(pageDe([A_RECOUVRER], 54000))
      .mockResolvedValueOnce(pageDe([{ ...A_RECOUVRER, statut: 'RECOUVRE', date_regularisation: '2026-09-01T10:00:00Z' }], 0))
    recouvrer.mockResolvedValue({ versement_id: 7, statut: 'RECOUVRE' })

    const wrapper = await monter()
    await bouton(wrapper, 'Marquer recouvré').trigger('click')
    await flushPromises()

    expect(recouvrer).toHaveBeenCalledWith(7)
    expect(regularisations).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('Recouvré')
  })

  it('télécharge le document de retenue d’une ligne', async () => {
    regularisations.mockResolvedValue(pageDe([A_RECOUVRER], 54000))
    documentRetenue.mockResolvedValue(undefined)

    const wrapper = await monter()
    await bouton(wrapper, 'Document').trigger('click')
    await flushPromises()

    expect(documentRetenue).toHaveBeenCalledWith(7)
  })

  it('n’affiche aucune ligne quand la liste est vide', async () => {
    regularisations.mockResolvedValue(pageDe([]))

    const wrapper = await monter()

    expect(wrapper.text()).toContain('Aucune régularisation.')
  })
})

describe('PaiementStatusBadge — statuts de régularisation', () => {
  it('libelle A_RECOUVRER et RECOUVRE', () => {
    expect(mount(PaiementStatusBadge, { props: { statut: 'A_RECOUVRER' } }).text()).toBe('À recouvrer')
    expect(mount(PaiementStatusBadge, { props: { statut: 'RECOUVRE' } }).text()).toBe('Recouvré')
  })
})
