<script setup lang="ts">
import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import type { Permission } from '@/utils/permissions'

const { can } = useAuth()

interface QuickAction {
  label: string
  to: string
  color: string
  permission: Permission
  icon: string
}

// Icônes Lucide-style (path SVG inline, conforme au design system ANaTT).
const ACTIONS: QuickAction[] = [
  { label: 'Nouvelle mission', to: '/ordres/nouveau', color: 'bg-anatt-50 text-anatt-600', permission: 'om.retranscrire', icon: 'M12 4.5v15m7.5-7.5h-15' },
  { label: 'Générer les fichiers', to: '/paiements/mtn', color: 'bg-blue-50 text-blue-600', permission: 'paiements.generer', icon: 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3' },
  { label: 'Personnel', to: '/agents', color: 'bg-purple-50 text-purple-600', permission: 'personnel.consulter', icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z' },
  { label: 'Rapports', to: '/rapports', color: 'bg-amber-50 text-amber-600', permission: 'rapports.consulter', icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
]

const actions = computed(() => ACTIONS.filter((a) => can(a.permission)))
</script>

<template>
  <div v-if="actions.length" class="bg-white rounded-2xl border border-gray-100 p-5">
    <h3 class="font-display font-bold text-gray-800 text-sm mb-4">Actions rapides</h3>
    <div class="grid grid-cols-2 gap-2">
      <router-link
        v-for="action in actions"
        :key="action.label"
        :to="action.to"
        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors"
      >
        <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="action.color">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" /></svg>
        </div>
        <span class="text-sm font-display font-medium text-gray-700">{{ action.label }}</span>
      </router-link>
    </div>
  </div>
</template>
