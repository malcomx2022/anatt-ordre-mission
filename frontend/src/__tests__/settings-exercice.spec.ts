import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { exerciceDe } from '@/utils/exercice'

/*
 * Exercice proposé par l'écran Paramètres. Sans année enregistrée, il doit
 * suivre la détermination du serveur (Parametres::anneeFiscalePour) : avec un
 * début en juillet, mars 2027 relève de l'exercice 2026 (« 2026-2027 »), pas
 * de 2027. Sinon le premier enregistrement fige un exercice faux.
 */

const get = vi.fn()
vi.mock('@/composables/useApi', () => ({ useApi: () => ({ get: (...a: unknown[]) => get(...a), put: vi.fn() }) }))
vi.mock('@/composables/useToast', () => ({ useToast: () => ({ success: vi.fn() }) }))
vi.mock('@/composables/usePartAvance', () => ({ chargerPartAvance: vi.fn(), reinitialiser: vi.fn() }))

async function monter() {
  const { default: SettingsView } = await import('@/views/admin/SettingsView.vue')
  const wrapper = mount(SettingsView, { global: { stubs: { AppLayout: { template: '<div><slot /></div>' } } } })
  await flushPromises()
  return wrapper
}

/** Libellé « Exercice courant », affiché dans un champ en lecture seule. */
function exerciceAffiche(wrapper: Awaited<ReturnType<typeof monter>>): string[] {
  return wrapper.findAll('input[disabled]').map((i) => (i.element as HTMLInputElement).value)
}

beforeEach(() => {
  get.mockReset()
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2027, 2, 15)) // 15 mars 2027
})
afterEach(() => vi.useRealTimers())

describe('exerciceDe', () => {
  it('suit l’année civile avec un début en janvier', () => {
    expect(exerciceDe(new Date(2027, 2, 15), 1)).toBe(2027)
  })

  it('rattache une date antérieure au mois de début à l’exercice précédent', () => {
    expect(exerciceDe(new Date(2027, 2, 15), 7)).toBe(2026)
    expect(exerciceDe(new Date(2026, 6, 1), 7)).toBe(2026)
    expect(exerciceDe(new Date(2026, 5, 30), 7)).toBe(2025)
  })
})

describe('SettingsView — exercice par défaut', () => {
  it('propose l’exercice en cours déterminé depuis le mois de début', async () => {
    get.mockResolvedValue({ data: { data: { fiscalYearStartMonth: 7 } } })
    const wrapper = await monter()

    expect(exerciceAffiche(wrapper)).toContain('2026-2027')
  })

  it('n’écrase jamais une année enregistrée', async () => {
    get.mockResolvedValue({ data: { data: { fiscalYear: 2027, fiscalYearStartMonth: 7 } } })
    const wrapper = await monter()

    expect(exerciceAffiche(wrapper)).toContain('2027-2028')
  })
})

describe('SettingsView — onglet Notifications', () => {
  it('liste les 7 notifications réellement envoyées, sans celles de l’ancien circuit', async () => {
    get.mockResolvedValue({ data: { data: {} } })
    const texte = (await monter()).text()
    for (const nom of ['État à signer', 'État validé', 'État rejeté', 'OM validé (circuit par OM)',
      'OM rejeté (circuit par OM)', 'Lot confirmé', 'Trop-perçu créé']) {
      expect(texte).toContain(nom)
    }
    for (const ancien of ['Nouvel ordre de mission', 'Paiement effectue', 'Echec de paiement', 'Rapport mensuel auto']) {
      expect(texte).not.toContain(ancien)
    }
  })

  it('accentue ses libellés', async () => {
    get.mockResolvedValue({ data: { data: {} } })
    const texte = (await monter()).text()
    expect(texte).toContain('Paramètres généraux')
    expect(texte).toContain('Sécurité')
    expect(texte).not.toMatch(/Parametres|Securite|Duree/)
  })
})

describe('SettingsView — réglages appliqués par le serveur', () => {
  it('ne présente plus aucun réglage comme « indicatif »', async () => {
    get.mockResolvedValue({ data: { data: {} } })
    const texte = (await monter()).text()
    expect(texte).not.toMatch(/indicati|pas encore appliqu/)
    expect(texte).toContain("sauf au super administrateur")
  })
})
