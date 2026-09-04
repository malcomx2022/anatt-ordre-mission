import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

/*
 * Store des notifications (feed de la cloche) : chargement, compteur de non-lues,
 * marquage optimiste (une / toutes), purge à la déconnexion.
 */

const list = vi.fn()
const marquerLu = vi.fn()
const marquerToutLu = vi.fn()

vi.mock('../services/notifications', () => ({
  notificationsService: {
    list: (...a: unknown[]) => list(...a),
    marquerLu: (...a: unknown[]) => marquerLu(...a),
    marquerToutLu: (...a: unknown[]) => marquerToutLu(...a),
  },
}))

import { useNotificationsStore } from '../stores/notifications'

const FEED = {
  items: [
    { id: 1, type: 'om_valide', titre: 'A', message: 'a', lu: false, created_at: '2026-09-04T10:00:00Z' },
    { id: 2, type: 'om_rejete', titre: 'B', message: 'b', lu: false, created_at: '2026-09-04T09:00:00Z' },
    { id: 3, type: 'lot_confirme', titre: 'C', message: 'c', lu: true, created_at: '2026-09-04T08:00:00Z' },
  ],
  nonLues: 2,
}

beforeEach(() => {
  setActivePinia(createPinia())
  list.mockReset(); marquerLu.mockReset(); marquerToutLu.mockReset()
  list.mockResolvedValue(FEED)
  marquerLu.mockResolvedValue(undefined)
  marquerToutLu.mockResolvedValue(undefined)
})

describe('store notifications', () => {
  it('charge le feed et le compteur de non-lues', async () => {
    const store = useNotificationsStore()
    await store.charger()
    expect(store.notifications).toHaveLength(3)
    expect(store.unreadCount).toBe(2)
  })

  it('marque une notification lue (optimiste) et décrémente le compteur', async () => {
    const store = useNotificationsStore()
    await store.charger()
    await store.marquerLu(1)
    expect(store.notifications.find((n) => n.id === 1)!.lu).toBe(true)
    expect(store.unreadCount).toBe(1)
    expect(marquerLu).toHaveBeenCalledWith(1)
  })

  it('ne redemande pas le serveur pour une notification déjà lue', async () => {
    const store = useNotificationsStore()
    await store.charger()
    await store.marquerLu(3) // déjà lue
    expect(marquerLu).not.toHaveBeenCalled()
    expect(store.unreadCount).toBe(2)
  })

  it('marque tout lu et met le compteur à zéro', async () => {
    const store = useNotificationsStore()
    await store.charger()
    await store.marquerToutLu()
    expect(store.unreadCount).toBe(0)
    expect(store.notifications.every((n) => n.lu)).toBe(true)
    expect(marquerToutLu).toHaveBeenCalledOnce()
  })

  it('vide le feed (déconnexion)', async () => {
    const store = useNotificationsStore()
    await store.charger()
    store.vider()
    expect(store.notifications).toHaveLength(0)
    expect(store.unreadCount).toBe(0)
  })
})
