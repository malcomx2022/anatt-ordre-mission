import { describe, it, expect, vi, beforeEach } from 'vitest'

/*
 * Nom de l'application (Paramètres › Général) : titre de l'onglet du navigateur,
 * lu par la route publique parametres/application. Un échec garde le titre.
 */

const get = vi.fn()
vi.mock('@/services/http', () => ({ api: { get: (...a: unknown[]) => get(...a) } }))

beforeEach(() => {
  get.mockReset()
  document.title = 'ANaTT - Gestion des Missions'
})

describe('chargerApplication', () => {
  it('donne au titre de l’onglet le nom réglé', async () => {
    get.mockResolvedValue({ data: { data: { nom: 'ANaTT Missions', operateurs: { MTN: true, MOOV: true } } } })
    const { chargerApplication } = await import('@/composables/useApplication')
    await chargerApplication()
    expect(get).toHaveBeenCalledWith('/api/admin/parametres/application')
    expect(document.title).toBe('ANaTT Missions')
  })

  it('garde le titre en cas d’échec', async () => {
    get.mockRejectedValue(new Error('401'))
    const { chargerApplication } = await import('@/composables/useApplication')
    await chargerApplication()
    expect(document.title).toBe('ANaTT - Gestion des Missions')
  })
})
