import { ref } from 'vue'
import { defineStore } from 'pinia'
import { notificationsService, type Notification } from '@/services/notifications'

export type { Notification }

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const chargement = ref(false)

  /** Recharge le feed depuis le serveur (appelé au montage et périodiquement). */
  async function charger() {
    chargement.value = true
    try {
      const { items, nonLues } = await notificationsService.list()
      notifications.value = items
      unreadCount.value = nonLues
    } catch {
      // Silencieux : la cloche ne doit jamais casser l'écran.
    } finally {
      chargement.value = false
    }
  }

  /** Marque une notification lue (optimiste, puis serveur). */
  async function marquerLu(id: number) {
    const n = notifications.value.find((x) => x.id === id)
    if (!n || n.lu) return
    n.lu = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    try {
      await notificationsService.marquerLu(id)
    } catch {
      // En cas d'échec on recharge pour retrouver l'état serveur.
      void charger()
    }
  }

  /** Marque toutes les notifications lues. */
  async function marquerToutLu() {
    if (!unreadCount.value) return
    notifications.value.forEach((n) => (n.lu = true))
    unreadCount.value = 0
    try {
      await notificationsService.marquerToutLu()
    } catch {
      void charger()
    }
  }

  /** Réinitialise le feed (déconnexion). */
  function vider() {
    notifications.value = []
    unreadCount.value = 0
  }

  return { notifications, unreadCount, chargement, charger, marquerLu, marquerToutLu, vider }
})
