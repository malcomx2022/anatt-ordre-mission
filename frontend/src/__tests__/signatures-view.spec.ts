import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import type { PosteSignataire, SignatairesParametres } from '@/types'

/*
 * Paramètres › Signatures : un paraphe par poste du circuit (contrôleur, DAF, DG).
 * L'écran téléverse ou retire l'image du BON poste, affiche l'état renvoyé par
 * le serveur (jamais l'image elle-même) et enregistre les identités imprimées
 * dans les paramètres généraux. Le transport multipart du fichier est couvert
 * par signatures-upload.spec.ts.
 */

const list = vi.fn()
const televerser = vi.fn()
const supprimer = vi.fn()
const apiGet = vi.fn()
const apiPut = vi.fn()

vi.mock('@/services/etats', () => ({
  signaturesService: {
    list: (...a: unknown[]) => list(...a),
    televerser: (...a: unknown[]) => televerser(...a),
    supprimer: (...a: unknown[]) => supprimer(...a),
  },
}))
vi.mock('@/services/http', () => ({
  api: { get: (...a: unknown[]) => apiGet(...a), put: (...a: unknown[]) => apiPut(...a) },
}))
vi.mock('@/composables/useApi', () => ({
  useApi: () => ({}),
  getErrorMessage: (e: unknown) => (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Erreur',
}))
const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('@/composables/useToast', () => ({
  useToast: () => ({ success: toastSuccess, error: toastError, warning: vi.fn(), show: vi.fn() }),
}))

const stubs = {
  AppLayout: defineComponent({ setup: (_, { slots }) => () => h('div', slots.default?.()) }),
  RouterLink: defineComponent({ setup: (_, { slots }) => () => h('a', slots.default?.()) }),
}

function signataires(enregistrees: PosteSignataire[] = []): SignatairesParametres {
  const un = (poste: PosteSignataire, nom: string, prenoms: string) => ({
    nom, prenoms, nom_complet: `${prenoms} ${nom}`, fonction: '', signature_enregistree: enregistrees.includes(poste),
  })
  return {
    controleur: un('controleur', 'KPADE', 'Jules'),
    daf: un('daf', 'HOUNSOU', 'Aline'),
    dg: un('dg', 'ATOHOUN', 'Euloge D.'),
  }
}

async function monter() {
  const { default: View } = await import('../views/admin/SignaturesView.vue')
  const wrapper = mount(View, { global: { stubs } })
  await flushPromises()
  return wrapper
}
type Wrapper = Awaited<ReturnType<typeof monter>>

/** Carte d'un poste, dans l'ordre d'affichage : contrôleur, DAF, DG. */
const ORDRE: PosteSignataire[] = ['controleur', 'daf', 'dg']
function carte(w: Wrapper, poste: PosteSignataire) {
  const cartes = w.findAll('h3').map((t) => t.element.closest('.flex.flex-wrap')!.parentElement!)
  return cartes[ORDRE.indexOf(poste)]!
}

async function choisirFichier(w: Wrapper, poste: PosteSignataire, fichiers: File[]) {
  const input = w.findAll('input[type="file"]')[ORDRE.indexOf(poste)]!
  Object.defineProperty(input.element, 'files', { value: fichiers, configurable: true })
  await input.trigger('change')
  await flushPromises()
  return input
}

beforeEach(() => {
  for (const f of [list, televerser, supprimer, apiGet, apiPut, toastSuccess, toastError]) f.mockReset()
  list.mockResolvedValue(signataires(['dg']))
  apiGet.mockResolvedValue({ data: { data: {} } })
  apiPut.mockResolvedValue({ data: { data: {} } })
})

describe('SignaturesView — état des paraphes par poste', () => {
  it('indique pour chaque poste si un paraphe est enregistré', async () => {
    const w = await monter()

    expect(carte(w, 'dg').textContent).toContain('Signature enregistrée')
    expect(carte(w, 'dg').textContent).toContain('Remplacer la signature')
    for (const poste of ['controleur', 'daf'] as const) {
      expect(carte(w, poste).textContent).toContain('Aucune signature')
      expect(carte(w, poste).textContent).toContain('Téléverser la signature')
    }
    // « Supprimer » n'existe que pour un paraphe présent.
    expect(w.findAll('button').filter((b) => b.text() === 'Supprimer')).toHaveLength(1)
    // L'image n'est jamais chargée dans la page.
    expect(w.find('img').exists()).toBe(false)
  })

  it('préremplit les identités : paramètres saisis d’abord, signataire en repli', async () => {
    apiGet.mockResolvedValue({ data: { data: { dafNom: 'DOSSOU', dafFonction: 'DAF par intérim' } } })
    const w = await monter()
    const valeurs = w.findAll('input[type="text"]').map((i) => (i.element as HTMLInputElement).value)

    // Ordre des champs par carte : prénoms, nom, fonction.
    expect(valeurs.slice(0, 3)).toEqual(['Jules', 'KPADE', ''])
    expect(valeurs.slice(3, 6)).toEqual(['Aline', 'DOSSOU', 'DAF par intérim'])
    expect(apiGet).toHaveBeenCalledWith('/api/admin/settings')
  })
})

describe('SignaturesView — téléversement et retrait', () => {
  it('téléverse le fichier pour le poste choisi et affiche le nouvel état', async () => {
    televerser.mockResolvedValue(signataires(['dg', 'daf']))
    const w = await monter()
    const fichier = new File(['PNG'], 'paraphe-daf.png', { type: 'image/png' })

    const input = await choisirFichier(w, 'daf', [fichier])

    expect(televerser).toHaveBeenCalledWith('daf', fichier)
    expect(toastSuccess).toHaveBeenCalledWith('Signature enregistrée.')
    expect(carte(w, 'daf').textContent).toContain('Signature enregistrée')
    expect(carte(w, 'controleur').textContent).toContain('Aucune signature')
    // Le champ est vidé pour pouvoir re-sélectionner le même fichier.
    expect((input.element as HTMLInputElement).value).toBe('')
  })

  it('n’appelle rien quand la sélection de fichier est annulée', async () => {
    const w = await monter()
    await choisirFichier(w, 'controleur', [])
    expect(televerser).not.toHaveBeenCalled()
  })

  it('affiche le refus du serveur et conserve l’état précédent', async () => {
    televerser.mockRejectedValue({ response: { data: { message: 'Le fichier ne doit pas dépasser 500 Ko.' } } })
    const w = await monter()

    await choisirFichier(w, 'controleur', [new File(['X'], 'lourd.png', { type: 'image/png' })])

    expect(toastError).toHaveBeenCalledWith('Le fichier ne doit pas dépasser 500 Ko.')
    expect(carte(w, 'controleur').textContent).toContain('Aucune signature')
    expect(carte(w, 'dg').textContent).toContain('Signature enregistrée')
  })

  it('retire le paraphe du poste concerné', async () => {
    supprimer.mockResolvedValue(signataires([]))
    const w = await monter()

    await w.findAll('button').find((b) => b.text() === 'Supprimer')!.trigger('click')
    await flushPromises()

    expect(supprimer).toHaveBeenCalledWith('dg')
    expect(toastSuccess).toHaveBeenCalledWith('Signature supprimée.')
    expect(carte(w, 'dg').textContent).toContain('Aucune signature')
    expect(w.findAll('button').filter((b) => b.text() === 'Supprimer')).toHaveLength(0)
  })
})

describe('SignaturesView — identités imprimées', () => {
  it('enregistre les identités des trois postes, espaces retirés, puis recharge', async () => {
    const w = await monter()
    const champs = w.findAll('input[type="text"]')
    await champs[7]!.setValue('  ATOHOUN-BIO ') // nom du DG
    await champs[8]!.setValue('Directeur Général ') // fonction du DG

    await w.findAll('button').find((b) => b.text().includes('Enregistrer les identités'))!.trigger('click')
    await flushPromises()

    expect(apiPut).toHaveBeenCalledWith('/api/admin/settings', {
      controleurNom: 'KPADE', controleurPrenoms: 'Jules', controleurFonction: '',
      dafNom: 'HOUNSOU', dafPrenoms: 'Aline', dafFonction: '',
      dgNom: 'ATOHOUN-BIO', dgPrenoms: 'Euloge D.', dgFonction: 'Directeur Général',
    })
    expect(toastSuccess).toHaveBeenCalledWith('Identités des signataires enregistrées.')
    expect(list).toHaveBeenCalledTimes(2)
  })
})
