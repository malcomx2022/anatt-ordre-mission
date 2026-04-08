<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { usePaiements } from '@/composables/usePaiements'
import { useApi } from '@/composables/useApi'
import { useSearch } from '@/composables/useSearch'
import type { Paiement, OperateurPaiement } from '@/types'

const router = useRouter()
const api = useApi()
const { paiements, loading, fetchPaiements } = usePaiements()
const { query: searchQuery, debouncedQuery } = useSearch(300)

const filterOperateur = ref('')
const filterStatut = ref('')
const filterMois = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const kpis = computed(() => ({
  total: totalItems.value,
  enAttente: paiements.value.filter(p => p.statut === 'EN_ATTENTE').length,
  succes: paiements.value.filter(p => p.statut === 'SUCCES').length,
  echecs: paiements.value.filter(p => p.statut === 'ECHEC').length,
}))

async function loadPaiements() {
  try {
    const params: Record<string, string | number> = { page: currentPage.value }
    if (filterOperateur.value) params.operateur = filterOperateur.value
    if (filterStatut.value) params.statut = filterStatut.value

    const response = await fetchPaiements(params)
    if (response?.meta) {
      currentPage.value = response.meta.current_page
      totalPages.value = response.meta.last_page
      totalItems.value = response.meta.total
    }
  } catch {
    // Geree par useApi
  }
}

watch([debouncedQuery, filterOperateur, filterStatut], () => {
  currentPage.value = 1
  loadPaiements()
})

function handlePageChange(page: number) {
  currentPage.value = page
  loadPaiements()
}

onMounted(loadPaiements)

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR') + ' F'
}

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statutVariant(statut: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
  switch (statut) {
    case 'SUCCES': return 'success'
    case 'EN_ATTENTE': return 'warning'
    case 'INITIE': return 'info'
    case 'ECHEC': return 'danger'
    default: return 'neutral'
  }
}

function statutLabel(statut: string): string {
  switch (statut) {
    case 'SUCCES': return 'Succes'
    case 'EN_ATTENTE': return 'En attente'
    case 'INITIE': return 'Initie'
    case 'ECHEC': return 'Echoue'
    default: return statut
  }
}
</script>

<template>
  <AppLayout title="Lots de Paiement" subtitle="Suivi et gestion des fichiers de paiement generes">
    <template #header-actions>
      <AppButton @click="router.push('/paiements/moov')">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Nouveau Lot
      </AppButton>
    </template>

    <!-- KPI Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8l-2 4h12z"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Total</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.total }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Lots generes</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-amber-600 bg-amber-50 px-2 py-1 rounded-full">En attente</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.enAttente }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">A soumettre</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-anatt-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-anatt-600 bg-anatt-50 px-2 py-1 rounded-full">Succes</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.succes }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Paiements effectues</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-red-500 bg-red-50 px-2 py-1 rounded-full">Echecs</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.echecs }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Lots avec erreurs</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher par reference, operateur..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="filterOperateur" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les operateurs</option>
          <option value="MTN">MTN MoMo</option>
          <option value="MOOV">MOOV Flooz</option>
        </select>
        <select v-model="filterStatut" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les statuts</option>
          <option value="EN_ATTENTE">En attente</option>
          <option value="EN_COURS">En cours</option>
          <option value="TRAITE">Traite</option>
          <option value="ECHEC">Echoue</option>
        </select>
        <select v-model="filterType" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Type paiement</option>
          <option value="avance">Avance (1/3)</option>
          <option value="solde">Solde (2/3)</option>
        </select>
        <input v-model="filterMois" type="month" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Reference OM</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Agent</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Operateur</th>
            <th class="text-right px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Montant</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="loading" class="text-center">
            <td colspan="7" class="px-5 py-12">
              <svg class="w-8 h-8 animate-spin text-anatt-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </td>
          </tr>
          <tr v-else-if="paiements.length === 0">
            <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-400 font-body">Aucun paiement trouve.</td>
          </tr>
          <tr v-for="p in paiements" :key="p.id" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-4">
              <span class="text-sm font-display font-bold text-anatt-500">{{ p.ordre_mission?.numero || '--' }}</span>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-body text-gray-700">{{ p.agent ? p.agent.prenom + ' ' + p.agent.nom : '--' }}</span>
            </td>
            <td class="px-5 py-4">
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-display font-semibold"
                :class="p.operateur === 'MTN' ? 'bg-yellow-50 text-yellow-800' : 'bg-blue-50 text-blue-800'"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                {{ p.operateur === 'MTN' ? 'MTN MoMo' : 'MOOV Flooz' }}
              </span>
            </td>
            <td class="px-5 py-4 text-right"><span class="text-sm font-display font-bold text-gray-900">{{ formatMontant(Number(p.montant)) }}</span></td>
            <td class="px-5 py-4 text-center">
              <AppBadge :variant="statutVariant(p.statut)">{{ statutLabel(p.statut) }}</AppBadge>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-body text-gray-600">{{ formatDate(p.created_at) }}</span>
            </td>
            <td class="px-5 py-4 text-center">
              <div class="flex items-center justify-center gap-1">
                <button @click="router.push('/paiements/statuts')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-anatt-500 transition-colors" title="Details">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button v-if="p.statut === 'ECHEC'" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-orange-50 text-gray-400 hover:text-orange-600 transition-colors" title="Relancer">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <AppPagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="totalItems"
        @page-change="handlePageChange"
      />
      </div>
    </div>
  </AppLayout>
</template>
