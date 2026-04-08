<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { useApi } from '@/composables/useApi'
import { useSearch } from '@/composables/useSearch'
import type { AuditLog } from '@/types'

const api = useApi()
const { query: searchQuery, debouncedQuery } = useSearch(300)
const actionFilter = ref('')
const dateFilter = ref('')
const loadingData = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const entries = ref<AuditLog[]>([])

async function loadAudit() {
  loadingData.value = true
  try {
    const params: Record<string, string | number> = { page: currentPage.value }
    if (actionFilter.value) params.action = actionFilter.value
    if (dateFilter.value) params.date_debut = dateFilter.value

    const { data } = await api.get('/api/audit', { params })
    entries.value = data.data || []
    if (data.meta) {
      currentPage.value = data.meta.current_page
      totalPages.value = data.meta.last_page
      totalItems.value = data.meta.total
    }
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
}

watch([debouncedQuery, actionFilter, dateFilter], () => {
  currentPage.value = 1
  loadAudit()
})

function handlePageChange(page: number) {
  currentPage.value = page
  loadAudit()
}

onMounted(loadAudit)

const stats = computed(() => [
  { label: 'Actions totales', value: String(totalItems.value), iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'Creations', value: String(entries.value.filter(e => e.action?.includes('CREATE')).length), iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { label: 'Validations', value: String(entries.value.filter(e => e.action?.includes('VALIDATE') || e.action?.includes('APPROVE')).length), iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
  { label: 'Connexions', value: String(entries.value.filter(e => e.action === 'LOGIN').length), iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
])

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatTime(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function getInitials(name: string): string {
  return name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '??'
}

function getActionColor(action: string): { bg: string; color: string } {
  if (action?.includes('CREATE')) return { bg: 'bg-blue-100', color: 'text-blue-700' }
  if (action?.includes('VALIDATE') || action?.includes('APPROVE')) return { bg: 'bg-green-100', color: 'text-green-700' }
  if (action?.includes('REJECT') || action?.includes('DELETE')) return { bg: 'bg-red-100', color: 'text-red-700' }
  if (action?.includes('UPDATE')) return { bg: 'bg-amber-100', color: 'text-amber-700' }
  if (action === 'LOGIN') return { bg: 'bg-cyan-100', color: 'text-cyan-700' }
  if (action?.includes('GENERATE') || action?.includes('EXPORT')) return { bg: 'bg-indigo-100', color: 'text-indigo-700' }
  return { bg: 'bg-gray-100', color: 'text-gray-600' }
}
</script>

<template>
  <AppLayout title="Journal d'Audit" subtitle="Historique complet des actions sur le systeme">
    <template #header-actions>
      <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-display font-semibold text-gray-700 hover:bg-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
        Exporter
      </button>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div :class="[stat.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center']">
            <svg :class="[stat.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
          </div>
          <span v-if="stat.badge" :class="[stat.badgeBg, stat.badgeColor, 'text-xs font-display font-medium px-2 py-1 rounded-lg']">{{ stat.badge }}</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 font-body">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher une action, un utilisateur..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="actionFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Toutes les actions</option>
          <option>Creation</option><option>Modification</option><option>Suppression</option><option>Validation</option><option>Connexion</option><option>Export</option>
        </select>
        <select v-model="moduleFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Tous les modules</option>
          <option>Personnel</option><option>Ordres de Mission</option><option>Paiements</option><option>Baremes</option><option>Utilisateurs</option><option>Systeme</option>
        </select>
        <input v-model="dateFilter" type="date" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
        <button class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" /></svg>
          Filtrer
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Horodatage</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Utilisateur</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Action</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Module</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Details</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">IP</th>
            <th class="px-5 py-3.5 text-center text-xs font-display font-semibold text-gray-500 uppercase w-12"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="loadingData">
            <td colspan="6" class="px-5 py-12 text-center">
              <svg class="w-8 h-8 animate-spin text-anatt-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </td>
          </tr>
          <tr v-else-if="entries.length === 0">
            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400 font-body">Aucun log d'audit trouve.</td>
          </tr>
          <tr v-for="entry in entries" :key="entry.id" class="group hover:bg-anatt-50/30 transition-colors" :class="entry.action?.includes('DELETE') || entry.action?.includes('REJECT') ? 'bg-red-50/30' : ''">
            <td class="px-5 py-4"><p class="text-sm font-body font-medium text-gray-800">{{ formatDate(entry.created_at) }}</p><p class="text-[11px] text-gray-400 font-body">{{ formatTime(entry.created_at) }}</p></td>
            <td class="px-5 py-4">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-display font-bold bg-anatt-100 text-anatt-700">{{ entry.utilisateur ? getInitials(entry.utilisateur) : '??' }}</div>
                <span class="text-sm font-body text-gray-700">{{ entry.utilisateur || '--' }}</span>
              </div>
            </td>
            <td class="px-5 py-4"><span :class="[getActionColor(entry.action).bg, getActionColor(entry.action).color, 'px-2.5 py-1 text-xs font-display font-semibold rounded-lg']">{{ entry.action }}</span></td>
            <td class="px-5 py-4"><span class="text-sm font-body text-gray-600">{{ entry.module }}</span></td>
            <td class="px-5 py-4">
              <p class="text-sm font-body text-gray-700">{{ entry.cible }}</p>
              <p class="text-[11px] text-gray-400 font-body">
                <template v-if="entry.details">
                  <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                </template>
                {{ entry.subDetail }}
              </p>
            </td>
            <td class="px-5 py-4"><span class="text-xs text-gray-400 font-mono">{{ entry.ip_address || '--' }}</span></td>
            <td class="px-5 py-4 text-center">
              <router-link :to="`/audit/1`" class="text-anatt-500 hover:text-anatt-600 text-sm opacity-0 group-hover:opacity-100 transition-opacity">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              </router-link>
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
