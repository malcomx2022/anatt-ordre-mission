import { ref } from 'vue'
import { defineStore } from 'pinia'

export interface Notification {
  id: number
  titre: string
  message: string
  lu: boolean
  created_at: string
}

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)

  function setNotifications(items: Notification[]) {
    notifications.value = items
    unreadCount.value = items.filter((n) => !n.lu).length
  }

  function markAsRead(id: number) {
    const n = notifications.value.find((n) => n.id === id)
    if (n) {
      n.lu = true
      unreadCount.value = notifications.value.filter((n) => !n.lu).length
    }
  }

  return { notifications, unreadCount, setNotifications, markAsRead }
})
