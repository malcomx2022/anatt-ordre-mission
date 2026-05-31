<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'

const { can } = useAuth()

const actions = [
  { label: 'Nouvelle Mission', to: '/ordres/nouveau', color: 'bg-anatt-50 text-anatt-600', permission: 'missions.create' as const },
  { label: 'Generer Fichiers', to: '/paiements/mtn', color: 'bg-blue-50 text-blue-600', permission: 'paiements.generate' as const },
  { label: 'Personnel', to: '/agents', color: 'bg-purple-50 text-purple-600', permission: 'agents.read' as const },
  { label: 'Rapports', to: '/rapports', color: 'bg-amber-50 text-amber-600', permission: 'rapports.read' as const },
]
</script>

<template>
  <div class="bg-white rounded-2xl border border-gray-100 p-5">
    <h3 class="font-display font-bold text-gray-800 text-sm mb-4">Actions rapides</h3>
    <div class="grid grid-cols-2 gap-2">
      <router-link
        v-for="action in actions.filter(a => can(a.permission))"
        :key="action.label"
        :to="action.to"
        class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors"
      >
        <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="action.color">
          <slot :name="action.label" />
        </div>
        <span class="text-sm font-display font-medium text-gray-700">{{ action.label }}</span>
      </router-link>
    </div>
  </div>
</template>
