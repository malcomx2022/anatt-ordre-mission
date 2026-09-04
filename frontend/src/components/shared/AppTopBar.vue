<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

defineProps<{
  title: string
  subtitle?: string
}>()

const auth = useAuthStore()
const notifs = useNotificationsStore()

const userInitials = computed(() => {
  if (!auth.user) return '?'
  return (auth.user.name || '').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

// ── Cloche : panneau déroulant + rafraîchissement périodique ──────────
const ouvert = ref(false)
const cloche = ref<HTMLElement | null>(null)
let minuteur: ReturnType<typeof setInterval> | undefined

function basculer() {
  ouvert.value = !ouvert.value
  if (ouvert.value) void notifs.charger()
}

function surClicExterieur(e: MouseEvent) {
  if (ouvert.value && cloche.value && !cloche.value.contains(e.target as Node)) ouvert.value = false
}

/** « à l'instant », « il y a 5 min », « il y a 2 h », sinon la date. */
function tempsRelatif(iso: string): string {
  const d = new Date(iso)
  const s = Math.floor((Date.now() - d.getTime()) / 1000)
  if (s < 60) return 'à l’instant'
  if (s < 3600) return `il y a ${Math.floor(s / 60)} min`
  if (s < 86400) return `il y a ${Math.floor(s / 3600)} h`
  if (s < 604800) return `il y a ${Math.floor(s / 86400)} j`
  return d.toLocaleDateString('fr-FR')
}

onMounted(() => {
  void notifs.charger()
  minuteur = setInterval(() => void notifs.charger(), 60000)
  document.addEventListener('click', surClicExterieur)
})
onUnmounted(() => {
  if (minuteur) clearInterval(minuteur)
  document.removeEventListener('click', surClicExterieur)
})
</script>

<template>
  <header class="bg-white border-b border-gray-200 px-6 py-4 sticky top-1 z-30">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-display font-bold text-gray-800">{{ title }}</h1>
        <p v-if="subtitle" class="text-xs font-body text-gray-500">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-3">
        <slot name="actions" />

        <!-- Cloche de notifications -->
        <div ref="cloche" class="relative">
          <button
            type="button"
            aria-label="Notifications"
            :aria-expanded="ouvert"
            class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-colors relative"
            @click.stop="basculer"
          >
            <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span v-if="notifs.unreadCount > 0" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-red-500 text-white text-[10px] rounded-full flex items-center justify-center font-display font-bold">
              {{ notifs.unreadCount > 99 ? '99+' : notifs.unreadCount }}
            </span>
          </button>

          <!-- Panneau -->
          <div
            v-if="ouvert"
            class="absolute right-0 mt-2 w-[360px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-gray-200 shadow-[0_16px_40px_-10px_rgba(0,0,0,0.15)] overflow-hidden z-40"
          >
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
              <h3 class="font-display font-bold text-sm text-gray-800">Notifications</h3>
              <button
                v-if="notifs.unreadCount > 0"
                type="button"
                class="text-[11px] font-display font-semibold text-anatt-600 hover:text-anatt-700"
                @click="notifs.marquerToutLu()"
              >
                Tout marquer comme lu
              </button>
            </div>

            <div class="max-h-[380px] overflow-y-auto">
              <p v-if="notifs.chargement && !notifs.notifications.length" class="px-4 py-8 text-center text-xs text-gray-400 font-body">
                Chargement…
              </p>
              <p v-else-if="!notifs.notifications.length" class="px-4 py-10 text-center text-sm text-gray-400 font-body">
                Aucune notification.
              </p>
              <ul v-else class="divide-y divide-gray-50">
                <li
                  v-for="n in notifs.notifications"
                  :key="n.id"
                  class="px-4 py-3 flex gap-3 cursor-pointer hover:bg-gray-50 transition-colors"
                  :class="n.lu ? '' : 'bg-anatt-50/40'"
                  @click="notifs.marquerLu(n.id)"
                >
                  <span class="mt-1.5 shrink-0 w-2 h-2 rounded-full" :class="n.lu ? 'bg-transparent' : 'bg-anatt-500'"></span>
                  <div class="min-w-0">
                    <p class="text-[13px] font-display font-semibold text-gray-800 leading-snug">{{ n.titre }}</p>
                    <p class="text-[12px] font-body text-gray-500 leading-snug mt-0.5">{{ n.message }}</p>
                    <p class="text-[10px] font-body text-gray-400 mt-1">{{ tempsRelatif(n.created_at) }}</p>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="hidden sm:flex items-center gap-2 bg-gray-100 rounded-xl px-3 py-2">
          <div class="w-7 h-7 bg-anatt-500 rounded-lg flex items-center justify-center text-white text-[10px] font-display font-bold">
            {{ userInitials }}
          </div>
          <span class="text-sm font-body text-gray-600">{{ auth.user?.name || '' }}</span>
        </div>
      </div>
    </div>
  </header>
</template>
