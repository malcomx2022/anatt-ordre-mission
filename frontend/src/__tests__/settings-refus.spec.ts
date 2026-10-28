import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

/*
 * Écran Paramètres : un réglage refusé par le serveur (422) doit être expliqué.
 * L'API répond « Donnees invalides. » et met le motif par champ dans `errors` ;
 * l'écran ne montrait que le message générique (toast de l'intercepteur), sans
 * dire quel réglage était en cause — une part d'avance à 120 % par exemple.
 */

const { get, put, toastSuccess, toastError } = vi.hoisted(() => ({
  get: vi.fn(), put: vi.fn(), toastSuccess: vi.fn(), toastError: vi.fn(),
}))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({ get, put }),
  getErrorMessage: (e: { response?: { data?: { message?: string } } }) => e.response?.data?.message ?? 'Erreur',
  getValidationErrors: (e: { response?: { data?: { errors?: Record<string, string[]> } } }) => e.response?.data?.errors ?? {},
}))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: toastSuccess, error: toastError }) }))
vi.mock('@/composables/usePartAvance', () => ({ chargerPartAvance: vi.fn(), reinitialiser: vi.fn() }))
vi.mock('@/composables/useApplication', () => ({ chargerApplication: vi.fn() }))

async function monter() {
  const { default: SettingsView } = await import('@/views/admin/SettingsView.vue')
  const w = mount(SettingsView, {
    global: { stubs: { AppLayout: { template: '<div><slot /><slot name="header-actions" /></div>' } } },
  })
  await flushPromises()
  return w
}

beforeEach(() => {
  get.mockReset(); put.mockReset(); toastSuccess.mockReset(); toastError.mockReset()
  get.mockResolvedValue({ data: { data: {} } })
})

describe('SettingsView — refus du serveur', () => {
  it('affiche le motif du refus (part d’avance hors bornes), pas le message générique', async () => {
    put.mockRejectedValue({ response: { status: 422, data: {
      message: 'Donnees invalides.',
      errors: { partAvance: ["Le pourcentage d'avance doit être un entier entre 0 et 100."] },
    } } })
    const w = await monter()
    await w.findAll('button').find((b) => b.text().includes('Enregistrer'))!.trigger('click')
    await flushPromises()
    expect(toastError).toHaveBeenCalledWith("Le pourcentage d'avance doit être un entier entre 0 et 100.")
    expect(toastSuccess).not.toHaveBeenCalled()
  })
})
