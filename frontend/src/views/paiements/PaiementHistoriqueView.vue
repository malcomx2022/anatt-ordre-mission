<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import { paiementsService } from '@/services/paiements'
import type { Versement, Operateur } from '@/types'

const versements = ref<Versement[]>([])
const loading = ref(true)
const periode = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const fmt = (v: number) => v.toLocaleString('fr-FR')

async function load() {
  loading.value = true
  try {
    const { items, meta } = await paiementsService.historique({
      page: currentPage.value,
      periode: periode.value || undefined,
    })
    versements.value = items
    currentPage.value = meta.current_page
    totalPages.value = meta.last_page
    totalItems.value = meta.total
  } finally {
    loading.value = false
  }
}

watch(periode, () => { currentPage.value = 1; load() })
onMounted(load)

function changePage(p: number) { currentPage.value = p; load() }
</script>

<template>
  <AppLayout title="Historique des paiements" subtitle="Versements confirmés (date de paiement)">
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 flex items-center gap-3">
      <label class="text-sm font-body text-gray-500">Période (mois)</label>
      <input v-model="periode" type="month" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-body focus:outline-none focus:border-anatt-500">
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Tranche</th>
              <th class="px-5 py-3">Opérateur</th>
              <th class="px-5 py-3">Téléphone</th>
              <th class="px-5 py-3">Montant</th>
              <th class="px-5 py-3">Date paiement</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="5" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!versements.length"><td colspan="5" class="px-5 py-10 text-center text-gray-400 font-body">Aucun versement confirmé sur la période.</td></tr>
            <tr v-for="v in versements" :key="v.id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 font-display">{{ v.type }}</td>
              <td class="px-5 py-3"><OperateurBadge :operateur="(v.operateur as Operateur)" /></td>
              <td class="px-5 py-3 font-mono text-xs">{{ v.telephone }}</td>
              <td class="px-5 py-3 font-display font-semibold">{{ fmt(v.montant) }}</td>
              <td class="px-5 py-3 font-body text-gray-500 text-xs">{{ v.date_paiement ? new Date(v.date_paiement).toLocaleString('fr-FR') : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="changePage" />
    </div>
  </AppLayout>
</template>
