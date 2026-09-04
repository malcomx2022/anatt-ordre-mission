import { api } from './http'

/** Notification in-app (feed de la cloche). */
export interface Notification {
  id: number
  type: string
  titre: string
  message: string
  lu: boolean
  created_at: string
}

export interface NotificationsFeed {
  items: Notification[]
  nonLues: number
}

export const notificationsService = {
  /** Les dernières notifications de l'utilisateur + compteur de non-lues. */
  async list(): Promise<NotificationsFeed> {
    const { data } = await api.get('/api/notifications')
    return { items: (data.data ?? []) as Notification[], nonLues: data.meta?.non_lues ?? 0 }
  },

  /** Marque une notification comme lue. */
  async marquerLu(id: number): Promise<void> {
    await api.post(`/api/notifications/${id}/lu`)
  },

  /** Marque toutes les notifications comme lues. */
  async marquerToutLu(): Promise<void> {
    await api.post('/api/notifications/lues')
  },
}
