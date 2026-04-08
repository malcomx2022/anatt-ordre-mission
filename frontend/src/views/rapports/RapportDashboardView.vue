<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'

const api = useApi()
const loadingData = ref(true)

const reportTypes = [
  { title: 'Rapport Mensuel', description: 'Synthese mensuelle des missions et paiements', route: '/rapports/mensuel', color: 'from-blue-500 to-blue-600', textColor: 'text-blue-600' },
  { title: 'Rapport Personnel', description: 'Detail des missions par agent', route: '/rapports/agents', color: 'from-purple-500 to-purple-600', textColor: 'text-purple-600' },
  { title: 'Export Avance', description: 'Export Excel/CSV personnalise', route: '/rapports/export', color: 'from-green-500 to-green-600', textColor: 'text-green-600' },
  { title: 'Rapport Statistique', description: 'Analyses croisees et tendances', route: '/rapports/mensuel', color: 'from-amber-500 to-amber-600', textColor: 'text-amber-600' },
]

// Donnees chargees depuis l'API
const mensuelData = ref<any>(null)

onMounted(async () => {
  try {
    const mois = new Date().toISOString().slice(0, 7)
    const { data } = await api.get(`/api/rapports/mensuel?mois=${mois}`)
    mensuelData.value = data.data || null
  } catch {
    // Pas de donnees
  } finally {
    loadingData.value = false
  }
})

const stats = computed(() => [
  { label: 'Missions ce mois', value: String(mensuelData.value?.total_missions || 0) },
  { label: 'Paiements ce mois', value: String(mensuelData.value?.total_paiements || 0) },
  { label: 'Montant total', value: mensuelData.value?.montant_total ? Number(mensuelData.value.montant_total).toLocaleString('fr-FR') + ' F' : '0 F' },
  { label: 'Mois en cours', value: mensuelData.value?.mois || '--' },
])

const scheduledReports = computed(() => {
  const now = new Date()
  const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1)
  const nextQuarter = new Date(now.getFullYear(), Math.ceil((now.getMonth() + 1) / 3) * 3, 1)
  return [
    { name: `Rapport Mensuel ${nextMonth.toLocaleDateString('fr-FR', { month: 'long' })}`, date: `Generation auto le ${nextMonth.toLocaleDateString('fr-FR')}`, bg: 'bg-blue-50', textColor: 'text-blue-800', subColor: 'text-blue-600' },
    { name: `Export Trimestriel`, date: `Generation auto le ${nextQuarter.toLocaleDateString('fr-FR')}`, bg: 'bg-purple-50', textColor: 'text-purple-800', subColor: 'text-purple-600' },
  ]
})
</script>

<template>
  <AppLayout title="Centre de Rapports" subtitle="Generation et consultation des rapports d'activite et financiers">
    <template #header-actions>
      <router-link to="/rapports/export" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
        Export Avance
      </router-link>
    </template>

    <!-- Report Type Cards -->
    <div class="grid grid-cols-4 gap-5 mb-8">
      <router-link
        v-for="report in reportTypes"
        :key="report.title"
        :to="report.route"
        class="bg-white rounded-2xl border border-gray-100 p-6 card-hover animate-fade-up"
      >
        <div :class="['w-14 h-14 bg-gradient-to-br rounded-2xl flex items-center justify-center mb-4', report.color]">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        </div>
        <h3 class="text-sm font-display font-bold text-gray-800 mb-1">{{ report.title }}</h3>
        <p class="text-xs text-gray-500 font-body mb-4">{{ report.description }}</p>
        <div :class="['flex items-center gap-2 text-xs font-display font-semibold', report.textColor]">
          <span>Generer</span>
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
        </div>
      </router-link>
    </div>

    <div class="grid grid-cols-3 gap-6">
      <!-- Recent Reports -->
      <div class="col-span-2 bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-display font-semibold text-gray-700">Rapports Recents</h3>
          <button class="text-xs text-anatt-500 font-display font-semibold hover:underline">Voir tout</button>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-if="loadingData" class="px-6 py-12 text-center">
            <svg class="w-8 h-8 animate-spin text-anatt-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          </div>
          <template v-else-if="mensuelData">
            <div v-if="mensuelData.missions_par_statut" class="px-6 py-4">
              <p class="text-sm font-display font-semibold text-gray-800 mb-3">Missions par statut ({{ mensuelData.mois }})</p>
              <div class="grid grid-cols-3 gap-3">
                <div v-for="(count, statut) in mensuelData.missions_par_statut" :key="statut" class="bg-gray-50 rounded-xl p-3 text-center">
                  <p class="text-lg font-display font-bold text-gray-800">{{ count }}</p>
                  <p class="text-[10px] text-gray-500 font-body">{{ statut }}</p>
                </div>
              </div>
            </div>
            <div v-if="mensuelData.paiements_par_operateur" class="px-6 py-4">
              <p class="text-sm font-display font-semibold text-gray-800 mb-3">Paiements par operateur</p>
              <div class="grid grid-cols-2 gap-3">
                <div v-for="(montant, op) in mensuelData.paiements_par_operateur" :key="op" class="bg-gray-50 rounded-xl p-3 text-center">
                  <p class="text-lg font-display font-bold text-gray-800">{{ Number(montant).toLocaleString('fr-FR') }} F</p>
                  <p class="text-[10px] text-gray-500 font-body">{{ op }}</p>
                </div>
              </div>
            </div>
          </template>
          <div v-else class="px-6 py-12 text-center text-sm text-gray-400 font-body">Aucune donnee pour ce mois.</div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="space-y-5 animate-fade-up delay-300">
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
          <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Rapports Planifies</h3>
          <div class="space-y-3">
            <div v-for="s in scheduledReports" :key="s.name" :class="[s.bg, 'flex items-center justify-between p-3 rounded-xl']">
              <div>
                <p :class="['text-xs font-display font-semibold', s.textColor]">{{ s.name }}</p>
                <p :class="['text-[10px] font-body', s.subColor]">{{ s.date }}</p>
              </div>
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-5">
          <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Statistiques de Generation</h3>
          <div class="space-y-3">
            <div v-for="stat in stats" :key="stat.label" class="flex justify-between items-center">
              <span class="text-xs text-gray-500 font-body">{{ stat.label }}</span>
              <span class="text-sm font-display font-bold text-gray-800">{{ stat.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
