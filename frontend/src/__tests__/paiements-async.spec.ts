import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'

/*
 * Gros lots (> paiement.async_seuil) : POST /paiements/lots/generer répond 202
 * { async, tracking_id, nb_lignes, message } au lieu d'un fichier. L'écran ne
 * doit PAS afficher d'erreur : il sonde /paiements/lots/job/{id} jusqu'à
 * TERMINE (puis télécharge le lot) ou ERREUR, et arrête le sondage au démontage.
 */

const previsualiser = vi.fn()
const lots = vi.fn()
const generer = vi.fn()
const statutJob = vi.fn()
const telechargerLot = vi.fn()

vi.mock('@/services/paiements', () => ({
  paiementsService: {
    previsualiser: (...a: unknown[]) => previsualiser(...a),
    lots: (...a: unknown[]) => lots(...a),
    generer: (...a: unknown[]) => generer(...a),
    statutJob: (...a: unknown[]) => statutJob(...a),
    telechargerLot: (...a: unknown[]) => telechargerLot(...a),
    confirmer: vi.fn(),
    marquerErreur: vi.fn(),
  },
}))
vi.mock('@/services/missions', () => ({ missionsService: { search: vi.fn().mockResolvedValue([]) } }))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({}),
  getErrorMessage: (e: unknown) => (e instanceof Error ? e.message : 'Erreur'),
}))
vi.mock('@/composables/usePartAvance', () => ({ usePartAvance: () => ({ partAvance: ref(70) }) }))

const toastSuccess = vi.fn()
const toastError = vi.fn()
const toastShow = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: vi.fn(), show: toastShow }),
}))

// Combobox simplifiée : un bouton qui sélectionne la mission 5.
const stubs = {
  AppCombobox: defineComponent({
    emits: ['change'],
    setup: (_, { emit }) => () => h('button', { 'data-test': 'choisir', onClick: () => emit('change', { value: 5 }) }, 'mission'),
  }),
}

async function monter() {
  const { default: Comp } = await import('../components/paiements/PaiementGeneration.vue')
  const wrapper = mount(Comp, { global: { stubs } })
  await wrapper.get('[data-test="choisir"]').trigger('click')
  await flushPromises()
  return wrapper
}

function boutonGenerer(wrapper: Awaited<ReturnType<typeof monter>>) {
  const b = wrapper.findAll('button').find((x) => x.text().includes('Générer le fichier'))
  if (!b) throw new Error('Bouton Générer introuvable')
  return b
}

beforeEach(() => {
  vi.useFakeTimers()
  for (const m of [previsualiser, lots, generer, statutJob, telechargerLot, toastSuccess, toastError, toastShow]) m.mockReset()
  previsualiser.mockResolvedValue({ nb_lignes: 60, total: 600000, beneficiaires: [] })
  lots.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
  generer.mockResolvedValue({ async: true, tracking_id: 'abc-123', nb_lignes: 60, message: 'Gros lot : génération lancée en file.' })
})

afterEach(() => {
  vi.useRealTimers()
})

describe('PaiementGeneration — génération asynchrone (202)', () => {
  it('suit la tâche puis télécharge le lot une fois TERMINE, sans erreur', async () => {
    statutJob
      .mockResolvedValueOnce({ statut: 'INTROUVABLE' })
      .mockResolvedValueOnce({ statut: 'EN_COURS' })
      .mockResolvedValueOnce({ statut: 'TERMINE', lot_id: 9, fichier: 'MTN_AVANCE_5.txt' })

    const wrapper = await monter()
    await boutonGenerer(wrapper).trigger('click')
    await flushPromises()

    expect(toastError).not.toHaveBeenCalled()
    expect(toastShow).toHaveBeenCalledWith('Gros lot : génération lancée en file.', 'info')
    expect(wrapper.text()).toContain('60 ligne(s)')
    expect(wrapper.text()).toContain('Génération en file')

    await vi.advanceTimersByTimeAsync(2000)
    await vi.advanceTimersByTimeAsync(2000)
    expect(wrapper.text()).toContain('Génération du fichier en cours')

    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(statutJob).toHaveBeenCalledWith('abc-123')
    expect(telechargerLot).toHaveBeenCalledWith(9, 'MTN_AVANCE_5.txt')
    expect(toastSuccess).toHaveBeenCalled()
    expect(toastError).not.toHaveBeenCalled()
    // Suivi terminé : plus de sondage, rafraîchissement du lot.
    const appels = statutJob.mock.calls.length
    await vi.advanceTimersByTimeAsync(6000)
    expect(statutJob.mock.calls.length).toBe(appels)
    expect(lots.mock.calls.length).toBeGreaterThanOrEqual(2)
  })

  it('affiche le message serveur si la tâche passe en ERREUR', async () => {
    statutJob.mockResolvedValue({ statut: 'ERREUR', message: 'Numéro invalide ligne 12' })

    const wrapper = await monter()
    await boutonGenerer(wrapper).trigger('click')
    await flushPromises()
    await vi.advanceTimersByTimeAsync(2000)
    await flushPromises()

    expect(toastError).toHaveBeenCalledWith('Numéro invalide ligne 12')
    expect(telechargerLot).not.toHaveBeenCalled()
  })

  it('arrête le sondage au démontage', async () => {
    statutJob.mockResolvedValue({ statut: 'EN_COURS' })

    const wrapper = await monter()
    await boutonGenerer(wrapper).trigger('click')
    await flushPromises()
    wrapper.unmount()

    await vi.advanceTimersByTimeAsync(10000)
    expect(statutJob).not.toHaveBeenCalled()
  })
})

describe('PaiementGeneration — lot complémentaire', () => {
  const lotConfirme = {
    id: 9, mission_id: 5, tranche: 'AVANCE', operateur: 'MTN', sequence: 1, statut: 'CONFIRME',
    nom_fichier: 'mission_AVANCE.txt', nb_lignes: 2, montant_total: 406000, confirme_le: '2026-10-23T10:00:00Z',
  }

  it('propose le lot complémentaire quand un versement est devenu payable après confirmation', async () => {
    lots.mockResolvedValue({ items: [lotConfirme], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } })
    previsualiser.mockResolvedValue({ nb_lignes: 1, total: 199500, beneficiaires: [] })

    const wrapper = await monter()
    const bouton = wrapper.findAll('button').find((x) => x.text().includes('Générer le lot complémentaire'))

    expect(bouton).toBeDefined()
    expect(bouton!.attributes('disabled')).toBeUndefined()
    expect(wrapper.text()).not.toContain('réinclusion bloquée')
  })

  it('bloque la génération quand le lot est confirmé et que rien de nouveau n’est payable', async () => {
    lots.mockResolvedValue({ items: [lotConfirme], meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } })
    previsualiser.mockResolvedValue({ nb_lignes: 0, total: 0, beneficiaires: [] })

    const wrapper = await monter()

    expect(wrapper.text()).toContain('réinclusion bloquée')
    expect(wrapper.findAll('button').some((x) => x.text().includes('Générer'))).toBe(false)
  })
})

describe('paiementsService.generer — réponse 202', () => {
  it('renvoie la charge asynchrone au lieu de lever une erreur', async () => {
    vi.resetModules()
    vi.doUnmock('@/services/paiements')
    const post = vi.fn().mockResolvedValue({
      status: 202,
      headers: { 'content-type': 'application/json' },
      data: new Blob([JSON.stringify({ async: true, tracking_id: 't-1', nb_lignes: 75, message: 'lancé' })], { type: 'application/json' }),
    })
    const get = vi.fn().mockResolvedValue({ data: { data: { statut: 'TERMINE', lot_id: 3 } } })
    vi.doMock('@/composables/useApi', () => ({ useApi: () => ({ post, get }), getErrorMessage: () => '' }))

    const { paiementsService } = await import('../services/paiements')
    const res = await paiementsService.generer({ mission_id: 1, tranche: 'AVANCE', operateur: 'MTN' })
    expect(res).toEqual({ async: true, tracking_id: 't-1', nb_lignes: 75, message: 'lancé' })

    const st = await paiementsService.statutJob('t-1')
    expect(get).toHaveBeenCalledWith('/api/paiements/lots/job/t-1')
    expect(st).toEqual({ statut: 'TERMINE', lot_id: 3 })
  })
})
