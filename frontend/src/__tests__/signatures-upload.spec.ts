import { describe, it, expect, vi } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'

// Même neutralisation que useApi.spec : évite le cycle routeur ↔ store ↔ services.
vi.mock('@/router', () => ({ default: {} }))
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ logout: () => {} }) }))

import { api } from '../services/http'
import { signaturesService } from '../services/etats'

/*
 * Téléversement d'un paraphe : l'instance axios porte un Content-Type JSON par
 * défaut. Sans surcharge, axios convertit le FormData en JSON et le fichier
 * arrive vide (« {} ») — aucun paraphe n'était jamais archivé.
 */
describe('signaturesService.televerser', () => {
  it('envoie le fichier en multipart, pas sérialisé en JSON', async () => {
    let envoye: unknown
    const adapter = async (config: InternalAxiosRequestConfig) => {
      envoye = config.data
      return { data: { data: {} }, status: 200, statusText: 'OK', headers: {}, config }
    }
    const precedent = api.defaults.adapter
    api.defaults.adapter = adapter
    try {
      const fichier = new File(['PNG'], 'paraphe.png', { type: 'image/png' })
      await signaturesService.televerser('dg', fichier)
    } finally {
      api.defaults.adapter = precedent
    }

    expect(envoye).toBeInstanceOf(FormData)
    expect((envoye as FormData).get('signature')).toBeInstanceOf(File)
  })
})
