import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

/*
 * Barème (TAUX-02) et exports de rapports (RAP-03, RAP-04) : un échec affichait un message
 * générique (« Échec de l'enregistrement. », « Échec de l'export. ») même quand le serveur
 * donnait la raison. L'écran donne désormais le motif du serveur, et ne garde son message
 * propre qu'en repli (réseau coupé, réponse sans message).
 */

const update = vi.fn()
const list = vi.fn()
const exporter = vi.fn()
vi.mock('@/services/baremes', () => ({ baremesService: { list: (...a: unknown[]) => list(...a), update: (...a: unknown[]) => update(...a) } }))
vi.mock('@/services/rapports', () => ({
  rapportsService: { export: (...a: unknown[]) => exporter(...a), telechargerExport: vi.fn(), parDimension: vi.fn().mockResolvedValue([]) },
}))
const toastError = vi.fn()
const toastSuccess = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: vi.fn(), show: vi.fn() }),
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', [slots['header-actions']?.(), slots.default?.()]) }),
}

const refus = (motif: string) => ({ isAxiosError: true, response: { status: 422, data: { message: 'Donnees invalides.', errors: { champ: [motif] } } } })
const reseau = { isAxiosError: true }

beforeEach(() => {
  vi.clearAllMocks()
  list.mockResolvedValue([{ id: 1, categorie: 'INDICE_SUP_800', zone_code: 'INTERIEUR', type_prise_en_charge: 'UN_REPAS', taux_journalier: 15000 }])
})

async function enregistrerBareme() {
  const { default: View } = await import('../views/taux/TauxEditView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  await w.get('input[type="number"]').setValue('16000')
  await w.findAll('button').find((b) => b.text().includes('Enregistrer'))!.trigger('click')
  await flushPromises()
}

describe('Enregistrement partiel du barème', () => {
  it('garde le taux refusé et sa saisie, recharge les taux enregistrés', async () => {
    list.mockResolvedValue([
      { id: 1, categorie: 'INDICE_SUP_800', zone_code: 'INTERIEUR', type_prise_en_charge: 'UN_REPAS', taux_journalier: 15000 },
      { id: 2, categorie: 'INDICE_SUP_800', zone_code: 'INTERIEUR', type_prise_en_charge: 'DEUX_REPAS', taux_journalier: 30000 },
    ])
    update.mockImplementation((id: number) => (id === 2 ? Promise.reject(refus('Taux refusé par le serveur.')) : Promise.resolve({})))
    const { default: View } = await import('../views/taux/TauxEditView.vue')
    const w = mount(View, { global: { stubs } })
    await flushPromises()
    const [premier, second] = w.findAll('input[type="number"]')
    await premier!.setValue('16000')
    await second!.setValue('-5')
    await w.findAll('button').find((b) => b.text().includes('Enregistrer'))!.trigger('click')
    await flushPromises()

    expect(toastSuccess).toHaveBeenCalledWith('1 taux mis à jour.')
    expect(toastError).toHaveBeenCalledWith('Taux refusé par le serveur.')
    expect(list).toHaveBeenCalledTimes(2) // rechargé après l'enregistrement partiel
    const apres = w.findAll('input[type="number"]')
    expect((apres[1]!.element as HTMLInputElement).value).toBe('-5') // saisie refusée conservée
    expect(apres[1]!.classes()).toContain('border-gold-400') // toujours marquée modifiée
    expect(apres[0]!.classes()).not.toContain('border-gold-400')
  })
})

async function genererExport() {
  const { default: View } = await import('../views/rapports/RapportExportView.vue')
  const w = mount(View, { global: { stubs } })
  await flushPromises()
  await w.findAll('button').find((b) => /G[ée]n[ée]rer/.test(b.text()))!.trigger('click')
  await flushPromises()
}

describe('Échec d’enregistrement du barème', () => {
  it('donne le motif du serveur', async () => {
    update.mockRejectedValue(refus('Le champ taux journalier doit être supérieur ou égal à 0.'))
    await enregistrerBareme()
    expect(toastError).toHaveBeenCalledWith('Le champ taux journalier doit être supérieur ou égal à 0.')
  })

  it('garde son message en repli quand le serveur n’en donne pas', async () => {
    update.mockRejectedValue(reseau)
    await enregistrerBareme()
    expect(toastError).toHaveBeenCalledWith("Échec de l'enregistrement.")
  })
})

describe('Échec d’un export de rapport', () => {
  it('donne le motif du serveur', async () => {
    exporter.mockRejectedValue(refus('La valeur du champ dimension n\'est pas valide.'))
    await genererExport()
    expect(toastError).toHaveBeenCalledWith('La valeur du champ dimension n\'est pas valide.')
  })

  it('garde son message en repli quand le serveur n’en donne pas', async () => {
    exporter.mockRejectedValue(reseau)
    await genererExport()
    expect(toastError).toHaveBeenCalledWith("Échec de la génération de l'export.")
  })
})
