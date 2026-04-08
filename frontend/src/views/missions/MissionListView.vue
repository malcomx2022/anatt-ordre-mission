<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import AppModal from '@/components/shared/AppModal.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { useMissions } from '@/composables/useMissions'
import { useApi } from '@/composables/useApi'
import { useSearch } from '@/composables/useSearch'
import type { OrdreMission, StatutMission } from '@/types'

const router = useRouter()
const api = useApi()
const { missions, loading, fetchMissions } = useMissions()
const { query: searchQuery, debouncedQuery } = useSearch(300)

const filterStatus = ref('')
const filterMonth = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const stats = ref({
  total: 0,
  brouillons: 0,
  en_attente: 0,
  valides: 0,
  payes: 0,
  rejetes: 0,
})

const deleteModalOpen = ref(false)
const deleteRef = ref('')
const deleteId = ref<number | null>(null)

async function loadMissions() {
  try {
    const params: Record<string, string | number> = { page: currentPage.value }
    if (debouncedQuery.value) params.search = debouncedQuery.value
    if (filterStatus.value) params.statut = filterStatus.value

    const response = await fetchMissions(params)
    if (response?.meta) {
      currentPage.value = response.meta.current_page
      totalPages.value = response.meta.last_page
      totalItems.value = response.meta.total
    }

    computeStats()
  } catch {
    // Erreurs gerees par l'intercepteur useApi
  }
}

function computeStats() {
  stats.value = {
    total: totalItems.value,
    brouillons: missions.value.filter(m => m.statut === 'BROUILLON').length,
    en_attente: missions.value.filter(m => m.statut === 'SOUMIS').length,
    valides: missions.value.filter(m => m.statut === 'VALIDE_N1' || m.statut === 'APPROUVE_DG').length,
    payes: missions.value.filter(m => m.statut === 'PAYE').length,
    rejetes: missions.value.filter(m => m.statut === 'REJETE').length,
  }
}

// Recharger quand les filtres changent
watch([debouncedQuery, filterStatus], () => {
  currentPage.value = 1
  loadMissions()
})

function handlePageChange(page: number) {
  currentPage.value = page
  loadMissions()
}

onMounted(loadMissions)

// Helper: construire les initiales depuis l'agent API
function getInitiales(agent: any): string {
  if (!agent) return '??'
  return ((agent.prenom?.[0] || '') + (agent.nom?.[0] || '')).toUpperCase()
}

// Helper: formater la periode
function formatPeriode(depart: string, retour: string): string {
  if (!depart || !retour) return '--'
  const d = new Date(depart)
  const r = new Date(retour)
  const mois = d.toLocaleDateString('fr-FR', { month: 'short' })
  return `${d.getDate()}-${r.getDate()} ${mois} ${d.getFullYear()}`
}

function getStatutBadge(statut: StatutMission) {
  switch (statut) {
    case 'BROUILLON':
      return { label: 'Brouillon', bg: 'bg-blue-50', text: 'text-blue-600' }
    case 'SOUMIS':
      return { label: 'En attente', bg: 'bg-gold-50', text: 'text-gold-600' }
    case 'VALIDE_N1':
      return { label: 'Valide', bg: 'bg-anatt-50', text: 'text-anatt-600' }
    case 'APPROUVE_DG':
      return { label: 'Approuve DG', bg: 'bg-green-50', text: 'text-green-700' }
    case 'PAYE':
      return { label: 'Paye', bg: 'bg-green-50', text: 'text-green-700' }
    case 'REJETE':
      return { label: 'Rejete', bg: 'bg-red-50', text: 'text-red-600' }
  }
}

function formatMontant(n: number) {
  return new Intl.NumberFormat('fr-FR').format(n)
}

function confirmDelete(numero: string, id: number) {
  deleteRef.value = numero
  deleteId.value = id
  deleteModalOpen.value = true
}

async function handleDelete() {
  if (deleteId.value) {
    try {
      await api.delete(`/api/ordres/${deleteId.value}`)
      loadMissions()
    } catch {
      // Erreurs gerees par l'intercepteur
    }
  }
  deleteModalOpen.value = false
}
</script>

<template>
  <AppLayout title="Ordres de Mission" subtitle="Gestion et suivi des ordres de mission">
    <template #header-actions>
      <router-link
        to="/ordres?attente=1"
        class="px-4 py-2.5 border border-orange-200 bg-orange-50 rounded-xl text-sm font-display font-medium text-orange-700 hover:bg-orange-100 transition-colors flex items-center gap-2"
      >
        <!-- clock icon -->
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        En attente
        <span class="bg-orange-600 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-1">{{ stats.en_attente }}</span>
      </router-link>
      <router-link
        to="/ordres/nouveau"
        class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2"
      >
        <!-- plus icon -->
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvel OM
      </router-link>
    </template>

    <!-- Stats mini -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-gray-800">{{ stats.total }}</p>
        <p class="text-[11px] font-body text-gray-500">Total OMs</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-gray-400">{{ stats.brouillons }}</p>
        <p class="text-[11px] font-body text-gray-500">Brouillons</p>
      </div>
      <div class="bg-white rounded-xl border border-orange-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-orange-500">{{ stats.en_attente }}</p>
        <p class="text-[11px] font-body text-gray-500">En attente</p>
      </div>
      <div class="bg-white rounded-xl border border-green-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-green-600">{{ stats.valides }}</p>
        <p class="text-[11px] font-body text-gray-500">Valides</p>
      </div>
      <div class="bg-white rounded-xl border border-blue-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-blue-600">{{ stats.payes }}</p>
        <p class="text-[11px] font-body text-gray-500">Payes</p>
      </div>
      <div class="bg-white rounded-xl border border-red-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-red-500">{{ stats.rejetes }}</p>
        <p class="text-[11px] font-body text-gray-500">Rejetes</p>
      </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par reference, missionnaire..."
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
          />
        </div>
        <select
          v-model="filterStatus"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        >
          <option value="">Tous les statuts</option>
          <option value="BROUILLON">Brouillon</option>
          <option value="SOUMIS">En attente</option>
          <option value="VALIDE_N1">Valide</option>
          <option value="PAYE">Paye</option>
          <option value="REJETE">Rejete</option>
        </select>
        <select class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500">
          <option value="">Tous types paiement</option>
          <option value="avance">Avance</option>
          <option value="reliquat">Reliquat</option>
        </select>
        <input
          v-model="filterMonth"
          type="month"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        />
      </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
              <th class="px-4 py-3"><input type="checkbox" class="rounded border-gray-300" /></th>
              <th class="px-4 py-3">Reference</th>
              <th class="px-4 py-3">Ref. SEMO</th>
              <th class="px-4 py-3">Missionnaire(s)</th>
              <th class="px-4 py-3">Destination</th>
              <th class="px-4 py-3">Periode</th>
              <th class="px-4 py-3">Montant</th>
              <th class="px-4 py-3">Avance / Reliquat</th>
              <th class="px-4 py-3">Statut</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="m in missions"
              :key="m.id"
              class="hover:bg-anatt-50/30 transition-colors"
            >
              <td class="px-4 py-3"><input type="checkbox" class="rounded border-gray-300" /></td>
              <td class="px-4 py-3">
                <router-link :to="`/ordres/${m.id}`" class="font-display font-semibold text-anatt-500 hover:underline">
                  {{ m.numero }}
                </router-link>
              </td>
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ m.numero }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-display font-bold bg-anatt-100 text-anatt-600">
                    {{ m.agent ? getInitiales(m.agent) : '??' }}
                  </div>
                  <div>
                    <p class="font-display font-medium text-gray-800">{{ m.agent ? `${m.agent.nom} ${m.agent.prenom}` : '--' }}</p>
                    <p class="text-[11px] font-body text-gray-400">{{ m.agent?.categorie || '--' }} &bull; {{ m.agent?.service || '' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-gray-700 flex items-center gap-1">
                  <svg class="w-3 h-3 text-anatt-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                  {{ m.destination }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs text-gray-600">{{ formatPeriode(m.date_depart, m.date_retour) }}</span><br />
                <span class="text-[10px] text-gray-400">{{ m.nb_jours }} jours</span>
              </td>
              <td class="px-4 py-3 font-display font-semibold text-gray-800">{{ formatMontant(Number(m.montant_per_diem)) }} F</td>
              <td class="px-4 py-3">
                <template v-if="m.statut === 'PAYE'">
                  <span class="inline-flex items-center text-[10px] px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                    <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                    Paye: {{ formatMontant(Number(m.montant_per_diem)) }}
                  </span>
                </template>
                <template v-else-if="m.statut === 'APPROUVE_DG'">
                  <span class="text-[11px] text-anatt-600 font-body">Paiement en attente</span>
                </template>
                <template v-else-if="m.statut === 'SOUMIS' || m.statut === 'VALIDE_N1'">
                  <span class="text-[11px] text-gray-400 italic font-body">En cours de validation</span>
                </template>
                <template v-else-if="m.statut === 'BROUILLON'">
                  <span class="text-[11px] text-gray-400 italic font-body">Brouillon</span>
                </template>
                <template v-else-if="m.statut === 'REJETE'">
                  <span class="text-[11px] text-red-500 italic font-body">Motif: {{ m.commentaire_rejet || '--' }}</span>
                </template>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium"
                  :class="[getStatutBadge(m.statut).bg, getStatutBadge(m.statut).text]"
                >
                  <svg class="w-1.5 h-1.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                  {{ getStatutBadge(m.statut).label }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-1">
                  <router-link
                    :to="`/ordres/${m.id}`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600"
                    title="Voir"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </router-link>
                  <router-link
                    v-if="m.statut === 'BROUILLON' || m.statut === 'SOUMIS' || m.statut === 'REJETE'"
                    :to="`/ordres/${m.id}/modifier`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-600"
                    title="Modifier"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </router-link>
                  <button
                    v-if="m.statut === 'BROUILLON'"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500"
                    title="Supprimer"
                    @click="confirmDelete(m.numero, m.id)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                  </button>
                  <router-link
                    v-if="m.statut === 'VALIDE_N1' || m.statut === 'APPROUVE_DG'"
                    to="/paiements"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-green-600"
                    title="Generer paiement"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <AppPagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="totalItems"
        @page-change="handlePageChange"
      />
    </div>

    <!-- Modal suppression -->
    <AppModal :show="deleteModalOpen" title="Supprimer cet OM ?" @close="deleteModalOpen = false">
      <div class="text-center">
        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h3 class="text-lg font-display font-bold text-gray-800">Supprimer cet OM ?</h3>
        <p class="text-sm font-body text-gray-500 mt-2">
          L'ordre de mission <strong>{{ deleteRef }}</strong> sera supprime definitivement. Seuls les brouillons peuvent etre supprimes.
        </p>
        <div class="flex gap-3 mt-6">
          <button
            class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
            @click="deleteModalOpen = false"
          >
            Annuler
          </button>
          <button
            class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl text-sm font-display font-medium hover:bg-red-600"
            @click="handleDelete"
          >
            Supprimer
          </button>
        </div>
      </div>
    </AppModal>
  </AppLayout>
</template>
