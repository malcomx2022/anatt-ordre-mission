<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { useApi } from '@/composables/useApi'
import { useSearch } from '@/composables/useSearch'
import type { User, UserRole } from '@/types'

const api = useApi()
const { query: searchQuery, debouncedQuery } = useSearch(300)
const roleFilter = ref('')
const loading = ref(true)
const users = ref<User[]>([])
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const stats = computed(() => [
  { label: 'Utilisateurs total', value: String(totalItems.value), iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'ADMIN', value: String(users.value.filter(u => u.role === 'ADMIN').length), iconBg: 'bg-red-100', iconColor: 'text-red-600' },
  { label: 'CHEF_SERVICE', value: String(users.value.filter(u => u.role === 'CHEF_SERVICE').length), iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { label: 'AGENT', value: String(users.value.filter(u => u.role === 'AGENT').length), iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
])

async function loadUsers() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: currentPage.value }
    if (debouncedQuery.value) params.search = debouncedQuery.value
    if (roleFilter.value) params.role = roleFilter.value

    const { data } = await api.get('/api/admin/users', { params })
    users.value = data.data || []
    if (data.meta) {
      currentPage.value = data.meta.current_page
      totalPages.value = data.meta.last_page
      totalItems.value = data.meta.total
    }
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}

watch([debouncedQuery, roleFilter], () => {
  currentPage.value = 1
  loadUsers()
})

function handlePageChange(page: number) {
  currentPage.value = page
  loadUsers()
}

onMounted(loadUsers)

const roleConfig: Record<string, { bg: string; color: string }> = {
  ADMIN: { bg: 'bg-red-100', color: 'text-red-700' },
  DIRECTEUR: { bg: 'bg-purple-100', color: 'text-purple-700' },
  CHEF_SERVICE: { bg: 'bg-green-100', color: 'text-green-700' },
  DRH: { bg: 'bg-blue-100', color: 'text-blue-700' },
  AGENT: { bg: 'bg-gray-100', color: 'text-gray-600' },
}

function getInitials(name: string): string {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

async function deleteUser(user: User) {
  if (!confirm(`Supprimer ${user.name} ?`)) return
  try {
    await api.delete(`/api/admin/users/${user.id}`)
    loadUsers()
  } catch {
    // Geree par useApi
  }
}
</script>

<template>
  <AppLayout title="Gestion des Utilisateurs" subtitle="Comptes d'acces et permissions du systeme">
    <template #header-actions>
      <router-link to="/admin/utilisateurs/nouveau" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Nouvel utilisateur
      </router-link>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div :class="[stat.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center']">
            <svg :class="[stat.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
          </div>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 font-body">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4">
        <div class="flex-1 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher par nom, email, role..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="roleFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Tous les roles</option>
          <option value="ADMIN">ADMIN</option>
          <option value="DIRECTEUR">DIRECTEUR</option>
          <option value="CHEF_SERVICE">CHEF_SERVICE</option>
          <option value="DRH">DRH</option>
          <option value="AGENT">AGENT</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Utilisateur</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Role</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Derniere connexion</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Actions (30j)</th>
            <th class="px-5 py-3.5 text-center text-xs font-display font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="loading">
            <td colspan="6" class="px-5 py-12 text-center">
              <svg class="w-8 h-8 animate-spin text-anatt-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400 font-body">Aucun utilisateur trouve.</td>
          </tr>
          <tr v-for="u in users" :key="u.id" class="group hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-anatt-100 rounded-xl flex items-center justify-center text-anatt-600 text-sm font-display font-bold">{{ getInitials(u.name) }}</div>
                <div>
                  <p class="text-sm font-display font-semibold text-gray-800">{{ u.name }}</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ u.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-4">
              <span :class="[roleConfig[u.role]?.bg || 'bg-gray-100', roleConfig[u.role]?.color || 'text-gray-600', 'px-2.5 py-1 text-xs font-display font-semibold rounded-lg']">{{ u.role }}</span>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-body text-gray-600">{{ u.role }}</span>
            </td>
            <td class="px-5 py-4">
              <p class="text-sm font-body text-gray-500">{{ u.created_at ? new Date(u.created_at).toLocaleDateString('fr-FR') : '--' }}</p>
            </td>
            <td class="px-5 py-4">
              <span class="text-xs font-display text-gray-500">--</span>
            </td>
            <td class="px-5 py-4 text-center">
              <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button v-if="u.role !== 'ADMIN'" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500" @click="deleteUser(u)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <AppPagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="totalItems"
        @page-change="handlePageChange"
      />
    </div>
  </AppLayout>
</template>
