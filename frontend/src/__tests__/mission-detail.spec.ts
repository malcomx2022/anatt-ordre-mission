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
  RouterLink: defineComponent({ props: { to: { type: String, default: '' } }, setup: (props, { slots }) => () => h('a', { href: props.to }, slots.default?.()) }),
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

describe('Accès au PDF officiel de l’OM', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    permissions = ['om.consulter']
  })

  it('propose le lien vers le PDF même une fois l’OM validé (le brouillon n’est plus proposé)', async () => {
    get.mockResolvedValue(ordre('VALIDE', { id: 5, tranche: 'AVANCE', statut: 'VALIDE', numero: '0042', valide: true }))
    const w = await monter()
    const lien = w.findAll('a').find((a) => a.text().includes('PDF de l’OM'))
    expect(lien?.attributes('href')).toBe('/ordres/10/pdf')
    expect(w.text()).not.toContain('Télécharger le brouillon')
  })
})

describe('Amendement d’un OM soldé', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    permissions = ['om.consulter', 'om.retranscrire']
  })

  const solde = (statutReliquat: string) => ({
    ...ordre('SOLDE', { id: 5, tranche: 'AVANCE', statut: 'VALIDE', numero: '0042', valide: true }),
    versements: [
      { id: 1, type: 'AVANCE', montant: 90000, statut: 'CONFIRME', operateur: 'MTN', telephone: '0197984877' },
      { id: 2, type: 'RELIQUAT', montant: statutReliquat === 'CONFIRME' ? 27000 : 0, statut: statutReliquat, operateur: 'MTN', telephone: '0197984877' },
    ],
  })

  it('propose « Amender » quand le reliquat n’est ni payé ni recouvré (avance à 100 %)', async () => {
    get.mockResolvedValue(solde('EN_ATTENTE'))
    const w = await monter()
    expect(bouton(w, 'Amender')).toBeTruthy()
  })

  it('masque « Amender » quand le reliquat est déjà payé (avenant formel)', async () => {
    get.mockResolvedValue(solde('CONFIRME'))
    const w = await monter()
    expect(bouton(w, 'Amender')).toBeUndefined()
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

describe('Répartition affichée après un amendement (libellés du PDF officiel)', () => {
  // OM réduit à 1 jour après paiement d'une avance engagée de 94 500 : reliquat négatif.
  const tropPercu = {
    ...ordre('VALIDE', { statut: 'VALIDE' }),
    nb_jours: 1, montant_total: 45000, avance: 94500, avance_engagee: 94500, reliquat: -49500,
    amendements: [{ id: 1, nb_jours_avant: 3, nb_jours_apres: 1, montant_total_avant: 135000, montant_total_apres: 45000, delta_montant: -90000, motif: 'Retour anticipé constaté.', amende_par: 3, amende_par_nom: 'Marie DOSSOU', amende_le: '2026-10-26T20:08:05Z' }],
  }

  beforeEach(() => { get.mockReset() })

  it('la fiche affiche « Avance engagée » et « Trop-perçu », plus de pourcentage', async () => {
    get.mockResolvedValue(tropPercu)
    const texte = (await monter()).text()
    expect(texte).toContain('Avance engagée')
    expect(texte).toContain('Trop-perçu (à recouvrer par retenue sur salaire)')
    expect(texte).not.toContain('Avance (70 %)')
  })

  it('l’aperçu PDF suit les mêmes libellés', async () => {
    get.mockResolvedValue(tropPercu)
    const { default: View } = await import('../views/missions/MissionPdfView.vue')
    const texte = mount(View, { global: { stubs } })
    await flushPromises()
    expect(texte.text()).toContain('Avance engagée')
    expect(texte.text()).toContain('Trop-perçu (à recouvrer par retenue sur salaire)')
    expect(texte.text()).not.toContain('Reliquat (30 %)')
  })
})
