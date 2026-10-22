import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'

/*
 * Fiche OM (OM-03) et aperçu PDF (OM-07) :
 * - circuit de repli PAR OM : « Valider » / « Rejeter » n'apparaissent que pour
 *   un OM IMPORTE d'une mission SANS état de paiement (exclusif du circuit par état) ;
 * - un échec de chargement (404) affiche le message serveur au lieu d'une page blanche.
 */

const get = vi.fn()
const valider = vi.fn()
const rejeter = vi.fn()
vi.mock('@/services/ordres', () => ({
  ordresService: {
    get: (...a: unknown[]) => get(...a),
    valider: (...a: unknown[]) => valider(...a),
    rejeter: (...a: unknown[]) => rejeter(...a),
    amender: vi.fn(),
    telechargerBrouillon: vi.fn(),
    telechargerPdf: vi.fn(),
  },
}))

let permissions: string[] = ['om.consulter', 'om.valider', 'om.rejeter']
vi.mock('@/composables/useAuth', () => ({ useAuth: () => ({ can: (p: string) => permissions.includes(p) }) }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({}),
  getErrorMessage: (e: unknown) => (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Erreur',
}))
vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: ref(70) }) }))
const toastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: toastError, warning: vi.fn(), show: vi.fn() }),
}))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { id: '10' } }), useRouter: () => ({ push: vi.fn() }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots['header-actions']?.(), slots.default?.()]) }),
  RouterLink: defineComponent({ setup: (_, { slots }) => () => h('a', slots.default?.()) }),
  Teleport: true,
}

function ordre(statut: string, etat: unknown) {
  return {
    id: 10, reference_semo: 'SEMO-2026-0042', mission_id: 3, agent_id: 1, role: 'PARTICIPANT',
    categorie: 'INDICE_400_799', zone_code: 'INTERIEUR', type_prise_en_charge: 'JOURNEE_COMPLETE',
    nb_jours: 3, montant_total: 90000, avance: 63000, reliquat: 27000, statut,
    mission: { id: 3, objet: 'Atelier', destination: 'Bohicon', etat_paiement: etat },
    versements: [], amendements: [],
  }
}

async function monter() {
  const { default: View } = await import('../views/missions/MissionDetailView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}

const bouton = (w: Awaited<ReturnType<typeof monter>>, texte: string) => w.findAll('button').find((b) => b.text().trim() === texte)

beforeEach(() => {
  get.mockReset()
  valider.mockReset()
  rejeter.mockReset()
  toastError.mockReset()
  permissions = ['om.consulter', 'om.valider', 'om.rejeter']
})

describe('MissionDetailView — circuit de repli par OM', () => {
  it('propose Valider / Rejeter pour un OM IMPORTE d’une mission sans état', async () => {
    get.mockResolvedValue(ordre('IMPORTE', null))
    const w = await monter()
    expect(bouton(w, 'Valider')).toBeTruthy()
    expect(bouton(w, 'Rejeter')).toBeTruthy()
  })

  it('masque les actions dès qu’un état de paiement existe', async () => {
    get.mockResolvedValue(ordre('IMPORTE', { id: 1, tranche: 'AVANCE', statut: 'BROUILLON', numero: null, valide: false }))
    const w = await monter()
    expect(bouton(w, 'Valider')).toBeUndefined()
    expect(bouton(w, 'Rejeter')).toBeUndefined()
  })

  it('masque les actions pour un OM déjà validé ou sans permission', async () => {
    get.mockResolvedValue(ordre('VALIDE', null))
    let w = await monter()
    expect(bouton(w, 'Valider')).toBeUndefined()

    permissions = ['om.consulter']
    get.mockResolvedValue(ordre('IMPORTE', null))
    w = await monter()
    expect(bouton(w, 'Valider')).toBeUndefined()
    expect(bouton(w, 'Rejeter')).toBeUndefined()
  })

  it('valide l’OM puis recharge la fiche', async () => {
    get.mockResolvedValueOnce(ordre('IMPORTE', null)).mockResolvedValueOnce(ordre('VALIDE', null))
    valider.mockResolvedValue(ordre('VALIDE', null))
    const w = await monter()
    await bouton(w, 'Valider')!.trigger('click')
    await flushPromises()
    expect(valider).toHaveBeenCalledWith(10)
    expect(get).toHaveBeenCalledTimes(2)
    expect(bouton(w, 'Valider')).toBeUndefined()
  })

  it('exige un motif d’au moins 10 caractères pour rejeter', async () => {
    get.mockResolvedValue(ordre('IMPORTE', null))
    rejeter.mockResolvedValue(ordre('REJETE', null))
    const w = await monter()
    await bouton(w, 'Rejeter')!.trigger('click')

    const confirmer = () => bouton(w, 'Confirmer le rejet')!
    await w.get('[data-test="motif-rejet"]').setValue('court')
    expect(confirmer().attributes('disabled')).toBeDefined()

    await w.get('[data-test="motif-rejet"]').setValue('Pièces justificatives manquantes')
    expect(confirmer().attributes('disabled')).toBeUndefined()
    await confirmer().trigger('click')
    await flushPromises()
    expect(rejeter).toHaveBeenCalledWith(10, 'Pièces justificatives manquantes')
  })

  it('remonte le refus serveur (422 : un état existe) par un toast', async () => {
    get.mockResolvedValue(ordre('IMPORTE', null))
    valider.mockRejectedValue({ response: { status: 422, data: { message: 'Cette mission a un état de paiement.' } } })
    const w = await monter()
    await bouton(w, 'Valider')!.trigger('click')
    await flushPromises()
    expect(toastError).toHaveBeenCalledWith('Cette mission a un état de paiement.')
  })
})

describe('Chargement en échec (404)', () => {
  const e404 = { response: { status: 404, data: { message: 'Ordre de mission introuvable.' } } }

  it('MissionDetailView affiche le message serveur', async () => {
    get.mockRejectedValue(e404)
    const w = await monter()
    expect(w.get('[data-test="erreur-chargement"]').text()).toBe('Ordre de mission introuvable.')
  })

  it('MissionPdfView affiche le message serveur', async () => {
    get.mockRejectedValue(e404)
    const { default: View } = await import('../views/missions/MissionPdfView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    expect(w.get('[data-test="erreur-chargement"]').text()).toBe('Ordre de mission introuvable.')
    expect(w.text()).not.toContain('Télécharger PDF')
  })
})
