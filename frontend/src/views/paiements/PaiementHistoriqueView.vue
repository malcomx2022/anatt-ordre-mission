<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import { paiementsService } from '@/services/paiements'
import type { Versement, Operateur } from '@/types'

const versements = ref<Versement[]>([])
const loading = ref(true)
const periode = ref('')
const dateDebut = ref('')
const dateFin = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const totalMontant = ref(0)

const fmt = (v: number) => v.toLocaleString('fr-FR')

// Plage incohérente (Au < Du) : on prévient au lieu de renvoyer une liste vide.
const plageInvalide = computed(() => !!dateDebut.value && !!dateFin.value && dateFin.value < dateDebut.value)

async function load() {
  if (plageInvalide.value) { versements.value = []; totalItems.value = 0; totalMontant.value = 0; loading.value = false; return }
  loading.value = true
  try {
    const { page, totalMontant: total } = await paiementsService.historique({
      page: currentPage.value,
      periode: periode.value || undefined,
      date_debut: dateDebut.value || undefined,
      date_fin: dateFin.value || undefined,
    })
    versements.value = page.items
    currentPage.value = page.meta.current_page
    totalPages.value = page.meta.last_page
    totalItems.value = page.meta.total
    totalMontant.value = total
  } finally {
    loading.value = false
  }
}

watch([periode, dateDebut, dateFin], () => { currentPage.value = 1; load() })
onMounted(load)

function changePage(p: number) { currentPage.value = p; load() }
</script>

<template>
  <AppLayout title="Historique des paiements" subtitle="Versements confirmés (date de paiement)">
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-[11px] font-body text-gray-500 mb-1">Mois</label>
          <input v-model="periode" type="month" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-body focus:outline-none focus:border-anatt-500">
        </div>
        <div>
          <label class="block text-[11px] font-body text-gray-500 mb-1">Du</label>
          <input v-model="dateDebut" type="date" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-body focus:outline-none focus:border-anatt-500">
        </div>
        <div>
          <label class="block text-[11px] font-body text-gray-500 mb-1">Au</label>
          <input v-model="dateFin" type="date" class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-body focus:outline-none focus:border-anatt-500">
        </div>
        <div class="ml-auto text-right">
          <p class="text-[11px] font-body text-gray-500">Total confirmé ({{ totalItems }} versement(s))</p>
          <p class="text-xl font-display font-bold text-anatt-700">{{ fmt(totalMontant) }} FCFA</p>
        </div>
      </div>
      <p v-if="plageInvalide" class="text-xs font-body text-red-500 mt-3">La date « Au » doit être postérieure ou égale à « Du ».</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Bénéficiaire</th>
              <th class="px-5 py-3">Tranche</th>
              <th class="px-5 py-3">Opérateur</th>
              <th class="px-5 py-3">Téléphone</th>
              <th class="px-5 py-3">Montant</th>
              <th class="px-5 py-3">Date paiement</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="6" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!versements.length"><td colspan="6" class="px-5 py-10 text-center text-gray-400 font-body">Aucun versement confirmé sur la période.</td></tr>
            <tr v-for="v in versements" :key="v.id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 font-body">{{ v.beneficiaire ?? '—' }}<span v-if="v.mission?.objet" class="block text-gray-400 text-xs">{{ v.mission.objet }}</span></td>
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
