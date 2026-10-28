import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { defineComponent, h, ref } from 'vue'
import type { Agent, Bareme } from '@/types'

/*
 * Wizard de retranscription (OM-02) : l'aperçu du per diem doit suivre le
 * barème (taux × jours), la part d'avance PARAMÉTRÉE (ici 60 %, pour prouver
 * qu'aucun 70 % n'est codé en dur), le transport en sus, et tomber à 0 en cas
 * d'exonération (prise en charge par un tiers). Une référence SEMO saisie deux
 * fois bloque le passage au récapitulatif.
 */

// Le store lit la part au chargement de son module, importé dynamiquement par
// `monter()` : la réf. ci-dessous est donc initialisée à ce moment-là.
const partRef = ref(60)

const listeBaremes = vi.fn()
const creer = vi.fn()
const push = vi.fn()
const toastWarning = vi.fn()
const toastError = vi.fn()

vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: partRef }) }))
vi.mock('@/services/baremes', () => ({ baremesService: { list: (...a: unknown[]) => listeBaremes(...a) } }))
vi.mock('@/services/missions', () => ({ missionsService: { create: (...a: unknown[]) => creer(...a) } }))
vi.mock('@/services/agents', () => ({ agentsService: { search: vi.fn().mockResolvedValue([]) } }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({}),
  getValidationErrors: (e: unknown) =>
    (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors ?? {},
  getErrorMessage: () => 'Erreur',
}))
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: toastError, warning: toastWarning, show: vi.fn() }),
}))
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  WizardStepper: true,
  teleport: true,
}

const BAREMES: Bareme[] = [
  { id: 1, categorie: 'INDICE_400_799', zone_code: 'INTERIEUR', type_prise_en_charge: 'UN_REPAS', taux_journalier: 5000 },
  { id: 2, categorie: 'INDICE_400_799', zone_code: 'INTERIEUR', type_prise_en_charge: 'DEUX_REPAS', taux_journalier: 10000 },
  { id: 3, categorie: 'INDICE_400_799', zone_code: 'INTERIEUR', type_prise_en_charge: 'JOURNEE_COMPLETE', taux_journalier: 20000 },
]

function agent(id: number, nom: string): Agent {
  return {
    id, nom, prenoms: 'Koffi', matricule: `M${id}`, categorie: 'INDICE_400_799',
    telephone: '0197000000', operateur: 'MTN', actif: true,
  } as Agent
}

/** Espaces insécables de toLocaleString('fr-FR') ramenés à des espaces simples. */
const texte = (w: { text: () => string }) => w.text().replace(/\s+/g, ' ')

type Store = Awaited<ReturnType<typeof monter>>['store']

async function monter() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const { default: View } = await import('../views/missions/MissionCreateView.vue')
  const { useOrdresMissionStore } = await import('../stores/ordres-mission')
  const wrapper = mount(View, { global: { plugins: [pinia], stubs } })
  await flushPromises()
  return { wrapper, store: useOrdresMissionStore() }
}

/** Contexte valide : 3 jours à l'intérieur (décompte inclusif). */
function remplirContexte(store: Store) {
  Object.assign(store.contexte, {
    objet: 'Contrôle technique',
    destination: 'Bohicon',
    zone_code: 'INTERIEUR',
    date_depart: '2026-11-02',
    date_retour: '2026-11-04',
  })
}

/** Bénéficiaire complet : agent, référence, catégorie, journée complète. */
function remplirBeneficiaire(store: Store, i: number, reference: string, extra: Record<string, unknown> = {}) {
  if (!store.beneficiaires[i]) store.ajouterBeneficiaire()
  Object.assign(store.beneficiaires[i]!, {
    agent_id: 10 + i,
    agent: agent(10 + i, `AGENT${i}`),
    reference_semo: reference,
    categorie: 'INDICE_400_799',
    type_prise_en_charge: 'JOURNEE_COMPLETE',
    ...extra,
  })
}

function bouton(wrapper: Awaited<ReturnType<typeof monter>>['wrapper'], libelle: string) {
  const b = wrapper.findAll('button').find((x) => x.text().includes(libelle))
  if (!b) throw new Error(`Bouton « ${libelle} » introuvable`)
  return b
}

/** Cellule « Total mission » du pied du récapitulatif. */
function totalMission(wrapper: Awaited<ReturnType<typeof monter>>['wrapper']): string {
  return texte(wrapper.get('tfoot').findAll('td')[1]!)
}

async function allerEtape2(wrapper: Awaited<ReturnType<typeof monter>>['wrapper'], store: Store) {
  remplirContexte(store)
  await flushPromises()
  await bouton(wrapper, 'Suivant').trigger('click')
}

beforeEach(() => {
  partRef.value = 60
  listeBaremes.mockReset().mockResolvedValue(BAREMES)
  creer.mockReset()
  push.mockReset()
  toastWarning.mockReset()
  toastError.mockReset()
})

describe('MissionCreateView — aperçu du per diem', () => {
  it('calcule taux × jours de la mission et répartit selon la part d’avance paramétrée', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001')
    await flushPromises()

    const t = texte(wrapper)
    // 3 j × 20 000 = 60 000 ; avance 60 % = 36 000 (et non 42 000 à 70 %).
    expect(t).toContain('Total : 60 000 FCFA')
    expect(t).toContain('(3 j × 20 000)')
    expect(t).toContain('Avance 36 000 · Reliquat 24 000')
    expect(t).not.toContain('42 000')
  })

  it('suit en direct une nouvelle part d’avance', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001')
    partRef.value = 50
    await flushPromises()

    expect(texte(wrapper)).toContain('Avance 30 000 · Reliquat 30 000')
  })

  it('retient les jours facturés de l’OM plutôt que la durée de la mission', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001', { nb_jours: 2 })
    await flushPromises()

    const t = texte(wrapper)
    expect(t).toContain('Total : 40 000 FCFA')
    expect(t).toContain('(2 j × 20 000)')
  })

  it('ajoute le transport au total, et l’avance porte sur l’ensemble', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001', { montant_transport: 15000 })
    await flushPromises()

    const t = texte(wrapper)
    // 60 000 + 15 000 = 75 000 ; avance round(75 000 × 60 %) = 45 000.
    expect(t).toContain('Total : 75 000 FCFA')
    expect(t).toContain('Per diem 60 000 · Transport 15 000 · Avance 45 000 · Reliquat 30 000')
  })

  it('additionne une répartition détaillée par type de repas', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001', {
      type_prise_en_charge: '',
      lignes: [{ type: 'UN_REPAS', nb_jours: 1 }, { type: 'JOURNEE_COMPLETE', nb_jours: 2 }],
    })
    await flushPromises()

    const t = texte(wrapper)
    // 1 × 5 000 + 2 × 20 000 = 45 000 ; avance 27 000.
    expect(t).toContain('Total : 45 000 FCFA')
    expect(t).toContain('(3 j répartis)')
    expect(t).toContain('Avance 27 000 · Reliquat 18 000')
  })

  it('signale un barème introuvable au lieu d’afficher un montant', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001', { categorie: 'INDICE_SUP_800' })
    await flushPromises()

    const t = texte(wrapper)
    expect(t).toContain('Barème introuvable pour ce choix')
    expect(t).not.toContain('Total :')
  })

  it('exonère un bénéficiaire pris en charge par un tiers, transport compris', async () => {
    const { wrapper, store } = await monter()
    store.contexte.prise_en_charge_tiers = true
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001', { montant_transport: 15000 })
    await flushPromises()

    expect(texte(wrapper)).toContain('Exonéré (prise en charge tiers)')
    expect(store.calculer(store.beneficiaires[0]!)).toMatchObject({ total: 0, avance: 0, reliquat: 0, transport: 0 })

    await bouton(wrapper, 'Suivant').trigger('click')
    expect(texte(wrapper)).toContain('Prise en charge tiers : Oui (exonéré)')
    expect(totalMission(wrapper)).toBe('0 FCFA')
  })
})

describe('MissionCreateView — contrôles de saisie', () => {
  it('refuse de quitter l’étape 1 tant que le contexte est incomplet', async () => {
    const { wrapper } = await monter()
    await bouton(wrapper, 'Suivant').trigger('click')

    expect(toastWarning).toHaveBeenCalledWith(expect.stringContaining('Complétez le contexte'))
    expect(wrapper.text()).toContain('Contexte de la mission')
  })

  it('signale une référence SEMO en doublon et bloque le récapitulatif', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001')
    remplirBeneficiaire(store, 1, ' SEMO-001 ') // espaces ignorés
    await flushPromises()

    const t = texte(wrapper)
    expect(t.match(/Référence en doublon dans cette mission\./g)).toHaveLength(2)
    expect(t).toContain('Bénéficiaire 1 — référence SEMO en doublon')
    expect(t).toContain('Bénéficiaire 2 — référence SEMO en doublon')

    await bouton(wrapper, 'Suivant').trigger('click')
    expect(toastWarning).toHaveBeenCalledWith('Bénéficiaire 1 : référence SEMO en doublon à compléter.')
    expect(wrapper.text()).not.toContain('Récapitulatif')
  })

  it('nomme ce qui manque à un bénéficiaire incomplet', async () => {
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)

    expect(texte(wrapper)).toContain('Bénéficiaire 1 — agent, référence SEMO, catégorie, type de prise en charge')
  })
})

describe('MissionCreateView — récapitulatif et enregistrement', () => {
  it('totalise la mission et envoie la retranscription à l’API', async () => {
    creer.mockResolvedValue({ id: 42 })
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, ' SEMO-001 ', { montant_transport: 15000, conducteur_a_bord: 'DOSSOU' })
    remplirBeneficiaire(store, 1, 'SEMO-002', { role: 'CAV', nb_jours: 2, conducteur_a_bord: 'ignoré' })
    await flushPromises()
    await bouton(wrapper, 'Suivant').trigger('click')

    // 75 000 + 40 000
    expect(totalMission(wrapper)).toBe('115 000 FCFA')

    await bouton(wrapper, 'Enregistrer la mission').trigger('click')
    await flushPromises()

    expect(creer).toHaveBeenCalledTimes(1)
    const payload = creer.mock.calls[0]![0]
    expect(payload).toMatchObject({
      objet: 'Contrôle technique',
      zone_code: 'INTERIEUR',
      prise_en_charge_tiers: false,
    })
    expect(payload.ordres).toHaveLength(2)
    expect(payload.ordres[0]).toMatchObject({
      reference_semo: 'SEMO-001', agent_id: 10, role: 'PARTICIPANT',
      type_prise_en_charge: 'JOURNEE_COMPLETE', montant_transport: 15000, conducteur_a_bord: 'DOSSOU',
    })
    // Le conducteur à bord n'est qu'une mention de participant : jamais pour un CAV.
    expect(payload.ordres[1]).toMatchObject({ reference_semo: 'SEMO-002', role: 'CAV', nb_jours: 2 })
    expect(payload.ordres[1].conducteur_a_bord).toBeUndefined()
    // Aucun montant n'est envoyé : le serveur recalcule depuis le barème.
    expect(payload.ordres[0]).not.toHaveProperty('avance')

    expect(texte(wrapper)).toContain('Mission enregistrée')
  })

  it('affiche les erreurs de validation renvoyées par l’API', async () => {
    creer.mockRejectedValue({ response: { status: 422, data: { errors: { 'ordres.0.reference_semo': ['Référence SEMO déjà retranscrite.'] } } } })
    const { wrapper, store } = await monter()
    await allerEtape2(wrapper, store)
    remplirBeneficiaire(store, 0, 'SEMO-001')
    await flushPromises()
    await bouton(wrapper, 'Suivant').trigger('click')
    await bouton(wrapper, 'Enregistrer la mission').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Référence SEMO déjà retranscrite.')
    expect(toastError).toHaveBeenCalledWith("Échec de l'enregistrement de la mission.")
  })
})
