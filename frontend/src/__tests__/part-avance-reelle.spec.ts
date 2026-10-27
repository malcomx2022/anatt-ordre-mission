import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'

/*
 * Part d'avance d'une mission EXISTANTE : celle de ses OM (renvoyée par l'API,
 * `part_avance`), pas le réglage courant. Ici le réglage vaut 70 % alors que la
 * mission a été retranscrite à 60 % : l'écran de l'état et le sélecteur de
 * tranche de la génération doivent afficher 60 / 40, comme le PDF de l'OM.
 * Sans part renvoyée (mission exonérée, pas encore choisie), repli sur le réglage.
 */

const parMission = vi.fn()
const previsualiser = vi.fn()

vi.mock('@/services/etats', () => ({ etatsService: { parMission: (...a: unknown[]) => parMission(...a) } }))
vi.mock('@/services/paiements', () => ({
  paiementsService: {
    previsualiser: (...a: unknown[]) => previsualiser(...a),
    lots: vi.fn().mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } }),
  },
}))
vi.mock('@/services/missions', () => ({ missionsService: { search: vi.fn().mockResolvedValue([]) } }))
vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: ref(70) }) }))
vi.mock('@/composables/useApi', () => ({ useApi: () => ({}), getErrorMessage: () => 'Erreur' }))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), show: vi.fn() }),
}))
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    canCreerMissions: () => false, canValiderOM: () => false, canViserOM: () => false,
    canApprouverOM: () => false, canRejeterOM: () => false, canNumeroterEtat: () => false,
  }),
}))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { missionId: '5' } }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  AppCombobox: defineComponent({
    emits: ['change'],
    setup: (_, { emit }) => () => h('button', { 'data-test': 'choisir', onClick: () => emit('change', { value: 5 }) }, 'mission'),
  }),
}

function etat(partAvance: number | null) {
  return {
    id: 1, mission_id: 5, tranche: 'AVANCE', numero: null, statut: 'BROUILLON', nb_beneficiaires: 2,
    montant_total: 90000, montant_avance: 90000, montant_reliquat: 0, montant_total_lettres: '',
    part_avance: partAvance, controle: null, visa_daf: null, validation_dg: null,
    commentaire_rejet: null, prochaine_etape: 'CONTROLE', paiement_ouvert: false,
  }
}

async function monterEtat() {
  const { default: View } = await import('../views/etats/EtatDetailView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}

async function monterGeneration() {
  const { default: Comp } = await import('../components/paiements/PaiementGeneration.vue')
  const wrapper = mount(Comp, { global: { stubs } })
  await wrapper.get('[data-test="choisir"]').trigger('click')
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  parMission.mockReset()
  previsualiser.mockReset()
})

describe('EtatDetailView — part d’avance de la mission', () => {
  it('affiche la part des OM de la mission, pas le réglage courant', async () => {
    parMission.mockResolvedValue({ avance: etat(60), reliquat: null })
    const texte = (await monterEtat()).text()
    expect(texte).toContain('Avances (60 %)')
    expect(texte).toContain('Reliquats (40 %)')
    expect(texte).toContain('Avance (60 %)')
    expect(texte).not.toContain('70 %')
  })

  it('retombe sur le réglage courant quand l’API ne renvoie pas de part', async () => {
    parMission.mockResolvedValue({ avance: etat(null), reliquat: null })
    expect((await monterEtat()).text()).toContain('Avances (70 %)')
  })
})

describe('PaiementGeneration — part d’avance de la mission choisie', () => {
  it('libelle les tranches avec la part de la mission', async () => {
    previsualiser.mockResolvedValue({ part_avance: 60, nb_lignes: 1, total: 60000, beneficiaires: [] })
    const options = (await monterGeneration()).findAll('option').map((o) => o.text())
    expect(options).toContain('Avance (60 %)')
    expect(options).toContain('Reliquat (40 %)')
  })

  it('garde le réglage courant tant qu’aucune part n’est connue', async () => {
    previsualiser.mockResolvedValue({ part_avance: null, nb_lignes: 0, total: 0, beneficiaires: [] })
    const options = (await monterGeneration()).findAll('option').map((o) => o.text())
    expect(options).toContain('Avance (70 %)')
  })
})
