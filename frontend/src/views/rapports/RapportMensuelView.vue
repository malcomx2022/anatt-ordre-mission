<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'

const api = useApi()
const loadingData = ref(true)
const selectedMonth = ref(new Date().toISOString().slice(0, 7))

// Generer les 6 derniers mois pour le select
const monthOptions = computed(() => {
  const opts = []
  const now = new Date()
  for (let i = 0; i < 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const val = d.toISOString().slice(0, 7)
    const label = d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
    opts.push({ value: val, label: label.charAt(0).toUpperCase() + label.slice(1) })
  }
  return opts
})

const rapportData = ref<any>(null)

async function loadRapport() {
  loadingData.value = true
  try {
    const { data } = await api.get(`/api/rapports/mensuel?mois=${selectedMonth.value}`)
    rapportData.value = data.data || null
  } catch {
    rapportData.value = null
  } finally {
    loadingData.value = false
  }
}

watch(selectedMonth, loadRapport)
onMounted(loadRapport)

const kpis = computed(() => {
  const d = rapportData.value
  if (!d) return []
  const montant = Number(d.montant_total || 0)
  return [
    { label: 'Ordres de Mission', value: String(d.total_missions || 0) },
    { label: 'Total Paiements', value: String(d.total_paiements || 0) },
    { label: 'Montant Total', value: montant >= 1000000 ? (montant / 1000000).toFixed(1) + 'M' : montant.toLocaleString('fr-FR'), highlight: true },
    { label: 'Mois', value: d.mois || '--' },
  ]
})

const missionsParStatut = computed(() => {
  if (!rapportData.value?.missions_par_statut) return []
  return Object.entries(rapportData.value.missions_par_statut).map(([statut, count]) => ({
    statut,
    count: count as number,
  }))
})

const paiementsParOperateur = computed(() => {
  if (!rapportData.value?.paiements_par_operateur) return []
  return Object.entries(rapportData.value.paiements_par_operateur).map(([op, montant]) => ({
    operateur: op,
    montant: Number(montant),
  }))
})

const selectedMonthLabel = computed(() => {
  const opt = monthOptions.value.find(o => o.value === selectedMonth.value)
  return opt?.label || selectedMonth.value
})
</script>

<template>
  <AppLayout title="Rapport Mensuel" subtitle="Synthese des missions et paiements">
    <template #header-actions>
      <select v-model="selectedMonth" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
        <option v-for="opt in monthOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <button class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m0 0a48.103 48.103 0 0112.5 0m-12.5 0V4.875c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v1.659" /></svg>
        Imprimer
      </button>
      <button class="px-4 py-2.5 bg-red-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-red-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        Export PDF
      </button>
    </template>

    <!-- Report Header -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 bg-gold-400 rounded-2xl flex items-center justify-center">
            <svg class="w-7 h-7 text-anatt-900" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0H21" /></svg>
          </div>
          <div>
            <h2 class="text-xl font-display font-bold text-gray-900">ANaTT - Rapport Mensuel</h2>
            <p class="text-sm text-gray-500 font-body">Agence Nationale des Transports Terrestres</p>
          </div>
        </div>
        <div class="text-right">
          <p class="text-2xl font-display font-bold text-anatt-700">{{ selectedMonthLabel }}</p>
          <p class="text-xs text-gray-400 font-body">Periode : {{ selectedMonth }}</p>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <!-- KPIs -->
    <div v-if="!loadingData" class="grid grid-cols-4 gap-4 mb-6 animate-fade-up delay-100">
      <div v-for="kpi in kpis" :key="kpi.label" class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
        <p class="text-xs text-gray-500 font-body mb-1">{{ kpi.label }}</p>
        <p :class="['text-2xl font-display font-bold', kpi.highlight ? 'text-anatt-700' : 'text-gray-900']">{{ kpi.value }}</p>
      </div>
    </div>

    <div v-if="!loadingData" class="grid grid-cols-2 gap-6 mb-6">
      <!-- Missions par statut -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-6">Missions par Semaine</h3>
        <div class="flex items-end justify-between gap-3 h-40">
          <div v-for="item in missionsParStatut" :key="item.statut" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full bg-anatt-500 rounded-t-lg transition-all duration-700" :style="{ height: (item.count * 20) + '%' }"></div>
            <span class="text-[10px] text-gray-400 font-body">{{ item.statut }}</span>
            <span class="text-xs font-display font-bold text-gray-700">{{ item.count }}</span>
          </div>
        </div>
      </div>

      <!-- Chart: Payment by Operator -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-200">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-6">Paiements par Operateur</h3>
        <div class="flex items-center gap-8">
          <div class="relative w-32 h-32">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="52" stroke="#f3f4f6" stroke-width="12" fill="none"/>
              <circle cx="60" cy="60" r="52" stroke="#facc15" stroke-width="12" fill="none" stroke-dasharray="326.7" stroke-dashoffset="114" stroke-linecap="round"/>
              <circle cx="60" cy="60" r="52" stroke="#3b82f6" stroke-width="12" fill="none" stroke-dasharray="326.7" stroke-dashoffset="213" stroke-linecap="round" transform="rotate(234 60 60)"/>
            </svg>
          </div>
          <div class="space-y-4 flex-1">
            <div v-for="op in paiementsParOperateur" :key="op.operateur" class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span :class="op.operateur === 'MTN' ? 'bg-yellow-400' : 'bg-blue-500'" class="w-3 h-3 rounded-full"></span>
                <span class="text-sm font-body font-medium text-gray-700">{{ op.operateur === 'MTN' ? 'MTN MoMo' : 'MOOV Flooz' }}</span>
              </div>
              <div class="text-right"><p class="text-sm font-display font-bold text-gray-800">{{ op.montant.toLocaleString('fr-FR') }} F</p></div>
            </div>
            <div v-if="paiementsParOperateur.length === 0" class="text-center text-sm text-gray-400 font-body py-4">Aucun paiement ce mois</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-300">
      <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-display font-semibold text-gray-700">Repartition par Direction</h3></div>
      <table class="w-full">
        <thead><tr class="bg-gray-50 border-b border-gray-100">
          <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Nombre</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="item in missionsParStatut" :key="item.statut" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ item.statut }}</td>
            <td class="px-5 py-3 text-center text-sm font-display font-bold">{{ item.count }}</td>
          </tr>
          <tr v-if="missionsParStatut.length === 0">
            <td colspan="2" class="px-5 py-8 text-center text-sm text-gray-400 font-body">Aucune mission ce mois</td>
          </tr>
        </tbody>
        <tfoot><tr class="bg-anatt-50 border-t-2 border-anatt-200">
          <td class="px-5 py-3 text-sm font-display font-bold text-anatt-800">TOTAL</td>
          <td class="px-5 py-3 text-center text-sm font-display font-bold text-anatt-800">{{ rapportData?.total_missions || 0 }}</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">100%</td>
        </tr></tfoot>
      </table>
    </div>
  </AppLayout>
</template>
