import { describe, it, expect, vi } from 'vitest'

// useApi importe le routeur et le store d'auth qui, via les services, réimportent
// useApi : on les neutralise pour éviter le cycle d'initialisation dans le test.
vi.mock('@/router', () => ({ default: {} }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: () => {} }) }))

import { normaliserErreurBlob, getErrorMessage } from '../composables/useApi'

/*
 * Un téléchargement (responseType: 'blob') qui échoue renvoie le corps d'erreur
 * en Blob : sans re-lecture, error.response.data.message est undefined et la
 * vraie cause du « impossible de télécharger » reste invisible.
 */

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const erreurAvec = (data: unknown, status = 500): any =>
  ({ isAxiosError: true, response: { data, status } })

describe('normaliserErreurBlob', () => {
  it('reconvertit un blob JSON d’erreur en objet exploitable', async () => {
    const blob = new Blob([JSON.stringify({ message: 'Barème introuvable.' })], {
      type: 'application/json',
    })
    const error = erreurAvec(blob)

    await normaliserErreurBlob(error)

    expect((error.response!.data as { message: string }).message).toBe('Barème introuvable.')
    // getErrorMessage voit désormais le vrai message.
    expect(getErrorMessage(error)).toBe('Barème introuvable.')
  })

  it('laisse intacte une réponse JSON déjà décodée', async () => {
    const error = erreurAvec({ message: 'Déjà lisible.' })

    await normaliserErreurBlob(error)

    expect(getErrorMessage(error)).toBe('Déjà lisible.')
  })

  it('n’échoue pas sur un blob non-JSON', async () => {
    const blob = new Blob(['%PDF-1.4 …'], { type: 'application/pdf' })
    const error = erreurAvec(blob)

    await normaliserErreurBlob(error)

    // Blob non-JSON laissé tel quel : message générique de repli.
    expect(getErrorMessage(error)).toBe('Une erreur est survenue.')
  })
})
