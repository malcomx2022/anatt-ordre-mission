<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuth, type Permission } from '@/composables/useAuth'

import AppLogo from '@/components/shared/AppLogo.vue'

const route = useRoute()
const auth = useAuthStore()
const { can } = useAuth()

const userInitials = computed(() => {
  if (!auth.user) return '?'
  return (auth.user.name || '').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

interface NavItem {
  label: string
  icon: string
  to: string
  badge?: number
  permission?: Permission
}

interface NavGroup {
  title: string
  items: NavItem[]
}

const allNavigation: NavGroup[] = [
  {
    title: 'Principal',
    items: [
      { label: 'Tableau de bord', icon: 'grid', to: '/', permission: 'dashboard.consulter' },
    ],
  },
  {
    title: 'Gestion',
    items: [
      { label: 'Personnel', icon: 'users', to: '/agents', permission: 'personnel.consulter' },
      { label: 'Ordres de Mission', icon: 'file-text', to: '/ordres', permission: 'om.consulter' },
      { label: 'Etats de paiement', icon: 'check-square', to: '/etats', permission: 'om.consulter' },
    ],
  },
  {
    title: 'Finances',
    items: [
      { label: 'Paiements', icon: 'credit-card', to: '/paiements', permission: 'paiements.consulter' },
      { label: 'Baremes', icon: 'calculator', to: '/taux', permission: 'baremes.consulter' },
    ],
  },
  {
    title: 'Analyses',
    items: [
      { label: 'Rapports', icon: 'bar-chart-2', to: '/rapports', permission: 'rapports.consulter' },
      { label: 'Audit', icon: 'clock', to: '/audit', permission: 'audit.consulter' },
    ],
  },
  {
    title: 'Systeme',
    items: [
      { label: 'Utilisateurs', icon: 'shield', to: '/admin/utilisateurs', permission: 'utilisateurs.gerer' },
      { label: 'Signatures', icon: 'edit-3', to: '/admin/signatures', permission: 'parametres.gerer' },
      { label: 'Parametres', icon: 'settings', to: '/admin/parametres', permission: 'parametres.gerer' },
    ],
  },
]

const navigation = computed(() => {
  return allNavigation
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.permission || can(item.permission)),
    }))
    .filter((group) => group.items.length > 0)
})

function isActive(to: string): boolean {
  if (to === '/') return route.path === '/'
  return route.path.startsWith(to)
}
</script>

<template>
  <aside class="fixed top-1 left-0 bottom-0 w-64 bg-gradient-to-b from-anatt-800 to-anatt-900 text-white z-40 overflow-y-auto flex flex-col">
    <!-- Flag bar -->
    <div class="fixed top-0 left-0 right-0 h-1 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>

    <!-- Logo ANaTT (logo officiel couleur sur badge blanc) -->
    <div class="p-4 border-b border-white/10">
      <div class="bg-white rounded-xl p-3 flex justify-center shadow-sm">
        <AppLogo size="md" />
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-3 space-y-1 flex-1">
      <template v-for="group in navigation" :key="group.title">
        <p class="text-[10px] uppercase tracking-wider text-anatt-200/60 px-3 py-2 mt-3 first:mt-0 font-display font-semibold">
          {{ group.title }}
        </p>
        <router-link
          v-for="item in group.items"
          :key="item.to"
          :to="item.to"
          class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm"
          :class="isActive(item.to) ? 'active text-white' : 'text-anatt-100'"
        >
          <component :is="'span'" class="w-5 text-center">
            <svg class="w-4 h-4 inline-block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <template v-if="item.icon === 'grid'"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></template>
              <template v-if="item.icon === 'users'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></template>
              <template v-if="item.icon === 'file-text'"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></template>
              <template v-if="item.icon === 'check-square'"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></template>
              <template v-if="item.icon === 'edit-3'"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></template>
              <template v-if="item.icon === 'credit-card'"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></template>
              <template v-if="item.icon === 'calculator'"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><line x1="8" x2="8.01" y1="10" y2="10"/><line x1="12" x2="12.01" y1="10" y2="10"/><line x1="16" x2="16.01" y1="10" y2="10"/><line x1="8" x2="8.01" y1="14" y2="14"/><line x1="12" x2="12.01" y1="14" y2="14"/><line x1="8" x2="8.01" y1="18" y2="18"/><line x1="12" x2="12.01" y1="18" y2="18"/></template>
              <template v-if="item.icon === 'bar-chart-2'"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></template>
              <template v-if="item.icon === 'clock'"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></template>
              <template v-if="item.icon === 'shield'"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></template>
              <template v-if="item.icon === 'settings'"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></template>
            </svg>
          </component>
          {{ item.label }}
          <span v-if="item.badge" class="ml-auto bg-orange-500 text-[10px] px-1.5 py-0.5 rounded-full">
            {{ item.badge }}
          </span>
        </router-link>
      </template>
    </nav>

    <!-- User Profile -->
    <div class="p-4 border-t border-white/10 bg-anatt-900/50">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-anatt-600 rounded-lg flex items-center justify-center text-sm font-display font-bold">
          {{ userInitials }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium truncate">{{ auth.user?.name || 'Utilisateur' }}</p>
          <p class="text-[10px] text-anatt-200">{{ auth.user?.role || '' }}</p>
        </div>
        <button class="text-anatt-200 hover:text-white" @click="auth.logout(); $router.push('/login')">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
        </button>
      </div>
    </div>
  </aside>
</template>
