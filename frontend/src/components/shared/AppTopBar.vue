<script setup lang="ts">
import { computed } from 'vue'
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
        <button class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-gray-200 transition-colors relative">
          <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
          <span v-if="notifs.unreadCount > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-danger-500 text-white text-[10px] rounded-full flex items-center justify-center font-display font-bold">
            {{ notifs.unreadCount }}
          </span>
        </button>
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
