<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import MissionStatusBadge from '@/components/missions/MissionStatusBadge.vue'
import { ordresService } from '@/services/ordres'
import { useSearch } from '@/composables/useSearch'
import { useAuth } from '@/composables/useAuth'
import type { OrdreMission } from '@/types'

const { query: searchQuery, debouncedQuery } = useSearch(300)
const { canCreerMissions } = useAuth()

const ordres = ref<OrdreMission[]>([])
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const filterStatut = ref('')
const filterZone = ref('')
const filterOperateur = ref('')

const fmt = (v: number) => v.toLocaleString('fr-FR')

async function load() {
  loading.value = true
  try {
    const { items, meta } = await ordresService.list({
      page: currentPage.value,
      search: debouncedQuery.value || undefined,
      statut: filterStatut.value || undefined,
      zone: filterZone.value || undefined,
      operateur: filterOperateur.value || undefined,
    })
    ordres.value = items
    currentPage.value = meta.current_page
    totalPages.value = meta.last_page
    totalItems.value = meta.total
  } finally {
    loading.value = false
  }
}

watch([debouncedQuery, filterStatut, filterZone, filterOperateur], () => { currentPage.value = 1; load() })
onMounted(load)

function changePage(p: number) { currentPage.value = p; load() }
</script>

<template>
  <AppLayout title="Ordres de mission" :subtitle="`${totalItems} OM`">
    <template #header-actions>
      <router-link v-if="canCreerMissions()" to="/ordres/nouveau" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600">+ Nouvelle mission</router-link>
    </template>

    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input v-model="searchQuery" type="text" placeholder="Réf. SEMO, agent…" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500">
        <select v-model="filterStatut" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Tous statuts</option>
          <option value="IMPORTE">Importé</option>
          <option value="VALIDE">Validé</option>
          <option value="REJETE">Rejeté</option>
          <option value="SOLDE">Soldé</option>
          <option value="ANNULE">Annulé</option>
        </select>
        <select v-model="filterZone" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Toutes zones</option>
          <option value="INTERIEUR">Intérieur</option>
          <option value="EXT_ZONE_I">Ext. Zone I</option>
          <option value="EXT_ZONE_II">Ext. Zone II</option>
          <option value="EXT_ZONE_III">Ext. Zone III</option>
        </select>
        <select v-model="filterOperateur" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Tous opérateurs</option>
          <option value="MTN">MTN</option>
          <option value="MOOV">MOOV</option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Réf. SEMO</th>
              <th class="px-5 py-3">Bénéficiaire</th>
              <th class="px-5 py-3">Rôle</th>
              <th class="px-5 py-3">Destination</th>
              <th class="px-5 py-3">Cat.</th>
              <th class="px-5 py-3">Total</th>
              <th class="px-5 py-3">Statut</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="7" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!ordres.length"><td colspan="7" class="px-5 py-10 text-center text-gray-400 font-body">Aucun ordre de mission.</td></tr>
            <tr v-for="o in ordres" :key="o.id" class="hover:bg-anatt-50/30 transition-colors cursor-pointer" @click="$router.push(`/ordres/${o.id}`)">
              <td class="px-5 py-3"><span class="font-mono text-xs text-anatt-600">{{ o.reference_semo }}</span></td>
              <td class="px-5 py-3 font-body">{{ o.agent ? `${o.agent.nom} ${o.agent.prenoms}` : '—' }}</td>
              <td class="px-5 py-3">{{ o.role }}</td>
              <td class="px-5 py-3 font-body">{{ o.mission?.destination ?? '—' }}</td>
              <td class="px-5 py-3 font-display font-semibold text-anatt-600">{{ o.categorie }}</td>
              <td class="px-5 py-3 font-display font-semibold">{{ fmt(o.montant_total) }}</td>
              <td class="px-5 py-3"><MissionStatusBadge :statut="o.statut" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="changePage" />
    </div>
  </AppLayout>
</template>
