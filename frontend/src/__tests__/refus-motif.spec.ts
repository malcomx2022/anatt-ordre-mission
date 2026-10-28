import { describe, it, expect, beforeEach, vi } from 'vitest'
import type { AxiosAdapter } from 'axios'

/*
 * Refus du serveur (422) : l'API répond « Donnees invalides. » et met le motif
 * par champ dans `errors`. Les écrans du circuit (état de paiement, fiche OM…)
 * affichaient ce message générique — « Donnees invalides. » au DAF qui avait
 * contrôlé l'état, au DG sans numéro de registre — au lieu du motif : « Vous
 * avez contrôlé cet état : le visa DAF revient à une autre personne. »
 * getErrorMessage et l'avertissement global du 422 donnent désormais le motif.
 */

vi.mock('@/router', () => ({ default: { push: vi.fn(), currentRoute: { value: { name: 'etat', fullPath: '/' } } } }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: vi.fn() }) }))

const refus = (errors?: Record<string, string[]>) => ({
  isAxiosError: true,
  response: { status: 422, data: { message: 'Donnees invalides.', ...(errors ? { errors } : {}) } },
})

const SEPARATION = 'Vous avez contrôlé cet état : le visa DAF revient à une autre personne.'

describe('getErrorMessage — refus 422', () => {
  it('donne le motif du refus, pas « Donnees invalides. »', async () => {
    const { getErrorMessage } = await import('@/composables/useApi')
    expect(getErrorMessage(refus({ signataire: [SEPARATION] }))).toBe(SEPARATION)
  })

  it('donne le motif d’un amendement refusé (fiche OM)', async () => {
    const { getErrorMessage } = await import('@/composables/useApi')
    const motif = 'Un fichier de paiement contenant cet ordre de mission est en attente de dépôt : confirmez le lot ou passez-le en erreur avant d\'amender.'
    expect(getErrorMessage(refus({ versement: [motif] }))).toBe(motif)
  })

  it('garde le message quand le refus ne porte aucun motif par champ', async () => {
    const { getErrorMessage } = await import('@/composables/useApi')
    expect(getErrorMessage(refus())).toBe('Donnees invalides.')
  })
})

describe('Avertissement global d’un 422', () => {
  beforeEach(async () => {
    const { useToast } = await import('@/composables/useToast')
    useToast().toasts.value = []
  })

  it('affiche le motif, une seule fois même si l’écran le reprend', async () => {
    const { useApi, getErrorMessage } = await import('@/composables/useApi')
    const { useToast } = await import('@/composables/useToast')
    const adapter: AxiosAdapter = (config) => Promise.reject(Object.assign(new Error('422'), {
      isAxiosError: true, config,
      response: { status: 422, data: { message: 'Donnees invalides.', errors: { numero: ['Renseignez le numéro de l\'état avant la validation finale.'] } }, headers: {}, config },
    }))

    const e = await useApi().post('/api/etats/1/approuver', {}, { adapter }).catch((err) => err)
    useToast().error(getErrorMessage(e)) // l'écran reprend l'erreur, comme EtatDetailView

    const messages = useToast().toasts.value.map((t) => t.message)
    expect(messages).toEqual(["Renseignez le numéro de l'état avant la validation finale."])
  })
})
