import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import type { EtatPaiement, StatutEtat, TrancheEtat } from '@/types'

/*
 * Écran du circuit de signature d'un état de paiement (avances puis reliquats).
 * Chaque bouton n'apparaît qu'à l'acteur de l'étape, au statut correspondant :
 * « Contrôler » (om.valider) sur un BROUILLON, « Apposer le visa DAF » (om.viser)
 * sur un état CONTRÔLÉ, « Valider définitivement » (om.approuver) sur un état
 * visé — et le DG est prévenu tant que le numéro de registre manque.
 * L'état des reliquats reste verrouillé tant que celui des avances n'est pas VALIDE.
 * (Les pourcentages de tranche sont couverts par part-avance-reelle.spec.ts.)
 */

const parMission = vi.fn()
const recapituler = vi.fn()
const controler = vi.fn()
const viser = vi.fn()
const approuver = vi.fn()
const rejeter = vi.fn()
const definirNumero = vi.fn()
const telechargerFinal = vi.fn()

vi.mock('@/services/etats', () => ({
  etatsService: {
    parMission: (...a: unknown[]) => parMission(...a),
    recapituler: (...a: unknown[]) => recapituler(...a),
    controler: (...a: unknown[]) => controler(...a),
    viser: (...a: unknown[]) => viser(...a),
    approuver: (...a: unknown[]) => approuver(...a),
    rejeter: (...a: unknown[]) => rejeter(...a),
    definirNumero: (...a: unknown[]) => definirNumero(...a),
    telecharger: vi.fn(),
    telechargerBrouillon: vi.fn(),
    telechargerFinal: (...a: unknown[]) => telechargerFinal(...a),
  },
}))

/** Droits de l'utilisateur simulé, modifiés test par test. */
let droits: string[] = []
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({
    canCreerMissions: () => droits.includes('om.retranscrire'),
    canValiderOM: () => droits.includes('om.valider'),
    canViserOM: () => droits.includes('om.viser'),
    canApprouverOM: () => droits.includes('om.approuver'),
    canRejeterOM: () => droits.includes('om.rejeter'),
    canNumeroterEtat: () => droits.includes('om.numeroter'),
  }),
}))
vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: ref(70) }) }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({}),
  getErrorMessage: (e: unknown) => (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Erreur',
}))
const toastWarning = vi.fn()
const toastError = vi.fn()
const toastSuccess = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: toastWarning, show: vi.fn() }),
}))
vi.mock('vue-router', () => ({ useRoute: () => ({ params: { missionId: '5' } }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  RouterLink: defineComponent({ setup: (_, { slots }) => () => h('a', slots.default?.()) }),
  teleport: true,
}

function etat(statut: StatutEtat, extra: Partial<EtatPaiement> = {}, tranche: TrancheEtat = 'AVANCE'): EtatPaiement {
  return {
    id: tranche === 'AVANCE' ? 1 : 2, mission_id: 5, tranche, numero: null, statut, nb_beneficiaires: 2,
    montant_total: 90000, montant_avance: tranche === 'AVANCE' ? 90000 : 0,
    montant_reliquat: tranche === 'RELIQUAT' ? 90000 : 0, montant_total_lettres: '',
    part_avance: 70, controle: null, visa_daf: null, validation_dg: null,
    commentaire_rejet: null, prochaine_etape: null, paiement_ouvert: statut === 'VALIDE',
    ...extra,
  } as EtatPaiement
}

async function monter() {
  const { default: View } = await import('../views/etats/EtatDetailView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}
type Wrapper = Awaited<ReturnType<typeof monter>>

const libelles = (w: Wrapper) => w.findAll('button').map((b) => b.text())
const bouton = (w: Wrapper, libelle: string) => {
  const b = w.findAll('button').find((x) => x.text().includes(libelle))
  if (!b) throw new Error(`Bouton « ${libelle} » introuvable`)
  return b
}

beforeEach(() => {
  droits = []
  for (const f of [parMission, recapituler, controler, viser, approuver, rejeter, definirNumero, telechargerFinal,
    toastWarning, toastError, toastSuccess]) f.mockReset()
})

describe('EtatDetailView — boutons du circuit selon le statut et les droits', () => {
  it('propose « Contrôler » au contrôleur sur un brouillon, et rien d’autre du circuit', async () => {
    droits = ['om.valider']
    parMission.mockResolvedValue({ avance: etat('BROUILLON'), reliquat: null })
    const w = await monter()

    expect(libelles(w)).toContain('Contrôler')
    expect(libelles(w)).not.toContain('Apposer le visa DAF')
    expect(libelles(w)).not.toContain('Valider définitivement')
    expect(w.text()).toContain('Étape 1 sur 3')
  })

  it('cache « Contrôler » à qui n’a pas om.valider, même sur un brouillon', async () => {
    droits = ['om.viser', 'om.approuver']
    parMission.mockResolvedValue({ avance: etat('BROUILLON'), reliquat: null })
    const w = await monter()

    expect(libelles(w)).not.toContain('Contrôler')
    expect(libelles(w)).not.toContain('Apposer le visa DAF')
    expect(libelles(w)).not.toContain('Valider définitivement')
  })

  it('le contrôle appelle le service et fait avancer l’écran à l’étape 2', async () => {
    droits = ['om.valider']
    parMission.mockResolvedValue({ avance: etat('BROUILLON'), reliquat: null })
    controler.mockResolvedValue(etat('CONTROLE', { controle: { nom: 'KPADE Jules', date: '2026-10-28T09:00:00Z', code: 'AB12' } as never }))
    const w = await monter()

    await bouton(w, 'Contrôler').trigger('click')
    await flushPromises()

    expect(controler).toHaveBeenCalledWith(1)
    expect(toastSuccess).toHaveBeenCalledWith('État contrôlé. Transmis au DAF.')
    expect(libelles(w)).not.toContain('Contrôler')
    expect(w.text()).toContain('Étape 2 sur 3')
    expect(w.text()).toContain('KPADE Jules')
  })

  it('propose le visa au DAF sur un état contrôlé, pas au contrôleur', async () => {
    parMission.mockResolvedValue({ avance: etat('CONTROLE'), reliquat: null })

    droits = ['om.valider']
    expect(libelles(await monter())).not.toContain('Apposer le visa DAF')

    droits = ['om.viser']
    const w = await monter()
    expect(libelles(w)).toContain('Apposer le visa DAF')
    expect(libelles(w)).not.toContain('Contrôler')

    viser.mockResolvedValue(etat('VISA_DAF'))
    await bouton(w, 'Apposer le visa DAF').trigger('click')
    await flushPromises()
    expect(viser).toHaveBeenCalledWith(1)
    expect(w.text()).toContain('Étape 3 sur 3')
  })

  it('n’offre la validation finale qu’au DG, sur un état visé', async () => {
    parMission.mockResolvedValue({ avance: etat('VISA_DAF', { numero: '0069/ANaTT/DAF/C-SFC' }), reliquat: null })

    droits = ['om.valider', 'om.viser']
    expect(libelles(await monter())).not.toContain('Valider définitivement')

    droits = ['om.approuver']
    const w = await monter()
    const valider = bouton(w, 'Valider définitivement')
    expect(valider.attributes('disabled')).toBeUndefined()
    expect(w.text()).not.toContain('Renseignez le numéro')

    approuver.mockResolvedValue(etat('VALIDE', { numero: '0069/ANaTT/DAF/C-SFC' }))
    await valider.trigger('click')
    await flushPromises()
    expect(approuver).toHaveBeenCalledWith(1)
    expect(toastSuccess).toHaveBeenCalledWith(expect.stringContaining('État des avances validé'))
    expect(w.text()).toContain('Circuit achevé')
  })

  it('prévient le DG et bloque la validation tant que le numéro manque', async () => {
    droits = ['om.approuver']
    parMission.mockResolvedValue({ avance: etat('VISA_DAF', { numero: null }), reliquat: null })
    const w = await monter()

    expect(bouton(w, 'Valider définitivement').attributes('disabled')).toBeDefined()
    expect(w.text()).toContain('Renseignez le numéro de l’état ci-dessous avant la validation finale.')
  })

  it('ne prévient pas du numéro un profil qui ne valide pas', async () => {
    droits = ['om.viser']
    parMission.mockResolvedValue({ avance: etat('VISA_DAF', { numero: null }), reliquat: null })
    expect((await monter()).text()).not.toContain('Renseignez le numéro')
  })

  it('n’affiche plus aucun bouton de signature ni de rejet sur un état validé', async () => {
    droits = ['om.valider', 'om.viser', 'om.approuver', 'om.rejeter']
    parMission.mockResolvedValue({ avance: etat('VALIDE', { numero: '12' }), reliquat: null })
    const w = await monter()

    for (const l of ['Contrôler', 'Apposer le visa DAF', 'Valider définitivement', 'Rejeter']) {
      expect(libelles(w)).not.toContain(l)
    }
    expect(libelles(w)).toContain('Télécharger l’état')
  })
})

describe('EtatDetailView — numéro et rejet', () => {
  it('enregistre le numéro sans le préfixe « N° » recopié du registre', async () => {
    droits = ['om.numeroter']
    parMission.mockResolvedValue({ avance: etat('VISA_DAF'), reliquat: null })
    definirNumero.mockResolvedValue(etat('VISA_DAF', { numero: '0069/ANaTT' }))
    const w = await monter()

    await w.get('input[type="text"]').setValue('  N° 0069/ANaTT ')
    await bouton(w, 'Enregistrer').trigger('click')
    await flushPromises()

    expect(definirNumero).toHaveBeenCalledWith(1, '0069/ANaTT')
    expect(w.text()).toContain('N°0069/ANaTT')
  })

  it('refuse un numéro vide sans appeler l’API', async () => {
    droits = ['om.numeroter']
    parMission.mockResolvedValue({ avance: etat('BROUILLON'), reliquat: null })
    const w = await monter()

    await bouton(w, 'Enregistrer').trigger('click')
    expect(definirNumero).not.toHaveBeenCalled()
    expect(toastWarning).toHaveBeenCalledWith('Saisissez le numéro de l’état.')
  })

  it('exige un motif d’au moins 10 caractères pour rejeter', async () => {
    droits = ['om.rejeter']
    parMission.mockResolvedValue({ avance: etat('CONTROLE'), reliquat: null })
    rejeter.mockResolvedValue(etat('REJETE', { commentaire_rejet: 'Montants erronés sur deux lignes' }))
    const w = await monter()

    await bouton(w, 'Rejeter').trigger('click')
    const motif = w.get('textarea')
    await motif.setValue('Trop bref') // 9 caractères
    await bouton(w, 'Confirmer le rejet').trigger('click')
    expect(rejeter).not.toHaveBeenCalled()
    expect(toastWarning).toHaveBeenCalledWith('Le motif doit comporter au moins 10 caractères.')

    await motif.setValue('  Montants erronés sur deux lignes  ')
    await bouton(w, 'Confirmer le rejet').trigger('click')
    await flushPromises()
    expect(rejeter).toHaveBeenCalledWith(1, 'Montants erronés sur deux lignes')
    expect(w.text()).toContain('Motif du rejet : Montants erronés sur deux lignes')
    expect(w.text()).toContain('État rejeté — un nouveau récapitulatif est nécessaire')
  })

  it('propose de refaire le récapitulatif d’un état rejeté au gestionnaire', async () => {
    droits = ['om.retranscrire']
    parMission.mockResolvedValue({ avance: etat('REJETE'), reliquat: null })
    recapituler.mockResolvedValue(etat('BROUILLON'))
    const w = await monter()

    await bouton(w, 'Refaire le récapitulatif').trigger('click')
    await flushPromises()
    expect(recapituler).toHaveBeenCalledWith(5, 'AVANCE')
  })

  it('remonte l’erreur du serveur sans changer l’état affiché', async () => {
    droits = ['om.valider']
    parMission.mockResolvedValue({ avance: etat('BROUILLON'), reliquat: null })
    controler.mockRejectedValue({ response: { data: { message: 'Séparation des tâches : déjà signé.' } } })
    const w = await monter()

    await bouton(w, 'Contrôler').trigger('click')
    await flushPromises()
    expect(toastError).toHaveBeenCalledWith('Séparation des tâches : déjà signé.')
    expect(libelles(w)).toContain('Contrôler')
  })
})

describe('EtatDetailView — séquencement avances → reliquats', () => {
  it('verrouille l’onglet des reliquats tant que l’état des avances n’est pas validé', async () => {
    droits = ['om.retranscrire']
    parMission.mockResolvedValue({ avance: etat('VISA_DAF'), reliquat: null })
    const w = await monter()

    expect(bouton(w, 'Reliquats').text()).toContain('verrouillé')
    await bouton(w, 'Reliquats').trigger('click')

    expect(w.text()).toContain('L’état des reliquats est verrouillé')
    expect(libelles(w).some((l) => l.includes('Établir le récapitulatif'))).toBe(false)

    await bouton(w, 'Suivre l’état des avances').trigger('click')
    expect(w.text()).not.toContain('L’état des reliquats est verrouillé')
  })

  it('ouvre le récapitulatif des reliquats une fois les avances validées', async () => {
    droits = ['om.retranscrire']
    parMission.mockResolvedValue({ avance: etat('VALIDE', { numero: '1' }), reliquat: null })
    recapituler.mockResolvedValue(etat('BROUILLON', {}, 'RELIQUAT'))
    const w = await monter()

    expect(bouton(w, 'Reliquats').text()).not.toContain('verrouillé')
    await bouton(w, 'Reliquats').trigger('click')
    expect(w.text()).toContain('Aucun état des reliquats pour cette mission')

    await bouton(w, 'Établir le récapitulatif des reliquats').trigger('click')
    await flushPromises()
    expect(recapituler).toHaveBeenCalledWith(5, 'RELIQUAT')
    expect(w.text()).toContain('Étape 1 sur 3')
    // L'aide à l'amendement n'apparaît qu'au stade du reliquat.
    expect(w.text()).toContain('Corriger la durée d’un ordre de mission ?')
  })

  it('n’offre le récapitulatif qu’au profil qui retranscrit', async () => {
    droits = ['om.valider']
    parMission.mockResolvedValue({ avance: null, reliquat: null })
    const w = await monter()

    expect(w.text()).toContain('Aucun état des avances pour cette mission')
    expect(libelles(w).some((l) => l.includes('Établir le récapitulatif'))).toBe(false)
  })

  it('ne propose l’état général qu’une fois les deux tranches validées', async () => {
    parMission.mockResolvedValue({ avance: etat('VALIDE'), reliquat: etat('VISA_DAF', {}, 'RELIQUAT') })
    expect(libelles(await monter())).not.toContain('Télécharger l’état général')

    parMission.mockResolvedValue({ avance: etat('VALIDE'), reliquat: etat('VALIDE', {}, 'RELIQUAT') })
    const w = await monter()
    await bouton(w, 'Télécharger l’état général').trigger('click')
    await flushPromises()
    expect(telechargerFinal).toHaveBeenCalledWith(5)
  })
})
