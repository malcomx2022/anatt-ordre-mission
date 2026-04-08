<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'

const api = useApi()
const searchQuery = ref('')
const selectedYear = ref(String(new Date().getFullYear()))
const loadingData = ref(true)

// Donnees chargees depuis l'API
const rapportAgents = ref<any[]>([])
const selectedAgent = ref<any>(null)

async function loadRapport() {
  loadingData.value = true
  try {
    const { data } = await api.get(`/api/rapports/agents?annee=${selectedYear.value}`)
    rapportAgents.value = data.data || []
    // Si recherche, filtrer
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      const found = rapportAgents.value.find((r: any) =>
        `${r.agent?.nom} ${r.agent?.prenom} ${r.agent?.matricule}`.toLowerCase().includes(q)
      )
      selectedAgent.value = found || rapportAgents.value[0] || null
    } else {
      selectedAgent.value = rapportAgents.value[0] || null
    }
  } catch {
    rapportAgents.value = []
  } finally {
    loadingData.value = false
  }
}

onMounted(loadRapport)

function searchAgent() {
  if (!searchQuery.value) {
    selectedAgent.value = rapportAgents.value[0] || null
    return
  }
  const q = searchQuery.value.toLowerCase()
  selectedAgent.value = rapportAgents.value.find((r: any) =>
    `${r.agent?.nom} ${r.agent?.prenom} ${r.agent?.matricule}`.toLowerCase().includes(q)
  ) || null
}

const agentInfo = computed(() => {
  const a = selectedAgent.value?.agent
  if (!a) return null
  return {
    initials: ((a.prenom?.[0] || '') + (a.nom?.[0] || '')).toUpperCase(),
    nom: `${a.prenom} ${a.nom}`,
    matricule: a.matricule,
    categorie: a.categorie,
    service: a.service,
  }
})

const agentStats = computed(() => {
  const d = selectedAgent.value
  if (!d) return []
  const total = Number(d.total_per_diem || 0)
  return [
    { value: String(d.total_missions || 0), label: 'Missions', bg: 'bg-blue-50', color: 'text-blue-700' },
    { value: total >= 1000000 ? (total / 1000000).toFixed(1) + 'M' : total.toLocaleString('fr-FR'), label: 'Total Per Diem', bg: 'bg-purple-50', color: 'text-purple-700' },
  ]
})

const missionsParStatut = computed(() => {
  if (!selectedAgent.value?.missions_par_statut) return []
  return Object.entries(selectedAgent.value.missions_par_statut).map(([statut, count]) => ({
    statut,
    count: count as number,
  }))
})

const yearOptions = computed(() => {
  const now = new Date().getFullYear()
  return [now, now - 1, now - 2].map(y => ({ value: String(y), label: `Annee ${y}` }))
})
</script>

<template>
  <AppLayout title="Rapport par Personnel" subtitle="Fiche individuelle des missions et paiements">
    <template #header-actions>
      <button class="px-4 py-2.5 bg-red-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-red-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        Export PDF
      </button>
    </template>

    <!-- Search & Filter -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up">
      <div class="flex items-center gap-4">
        <div class="flex-1 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher un agent par nom, matricule..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="selectedYear" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none" @change="loadRapport">
          <option v-for="opt in yearOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <button class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2" @click="searchAgent">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          Rechercher
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <!-- No agent selected -->
    <div v-else-if="!agentInfo" class="bg-white rounded-2xl border border-gray-100 p-12 text-center mb-6">
      <p class="text-sm text-gray-400 font-body">Aucun agent trouve. Recherchez par nom ou matricule.</p>
    </div>

    <!-- Agent Profile Card -->
    <div v-else class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-5">
          <div class="w-16 h-16 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-white text-xl font-display font-bold">{{ agentInfo.initials }}</div>
          <div>
            <h2 class="text-lg font-display font-bold text-gray-900">{{ agentInfo.nom }}</h2>
            <p class="text-sm text-gray-500 font-body">Matricule : {{ agentInfo.matricule }} - Categorie {{ agentInfo.categorie }}</p>
            <p class="text-xs text-gray-400 mt-1 font-body flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
              {{ agentInfo.service }}
            </p>
          </div>
        </div>
        <div class="grid grid-cols-4 gap-4">
          <div v-for="stat in agentStats" :key="stat.label" :class="[stat.bg, 'text-center px-4 py-3 rounded-xl']">
            <p :class="['text-xl font-display font-bold', stat.color]">{{ stat.value }}</p>
            <p :class="['text-[10px] font-body', stat.color]">{{ stat.label }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Mission History -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 animate-fade-up delay-200">
      <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-display font-semibold text-gray-700">Historique des Missions (2025)</h3></div>
      <table class="w-full">
        <thead><tr class="bg-gray-50 border-b border-gray-100">
          <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Ref. OM</th>
          <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Objet</th>
          <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Destination</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Periode</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Jours</th>
          <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Indemnite</th>
          <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Avance</th>
          <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Solde</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-if="missionsParStatut.length === 0">
            <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400 font-body">Aucune mission pour cet agent cette annee</td>
          </tr>
          <tr v-for="item in missionsParStatut" :key="item.statut" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ item.statut }}</td>
            <td class="px-5 py-3 text-center text-sm font-display font-bold">{{ item.count }}</td>
            <td class="px-5 py-3"></td>
            <td class="px-5 py-3 text-center"><span :class="item.statut === 'PAYE' ? 'bg-green-50 text-green-700' : item.statut === 'REJETE' ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-600'" class="px-2 py-0.5 rounded-full text-xs font-display font-medium">{{ item.statut }}</span></td>
          </tr>
        </tbody>
        <tfoot><tr class="bg-anatt-50 border-t-2 border-anatt-200">
          <td colspan="4" class="px-5 py-3 text-sm font-display font-bold text-anatt-800">TOTAL 2025</td>
          <td class="px-5 py-3 text-center text-sm font-display font-bold text-anatt-800">12 jours</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">600 000 F</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">200 000 F</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">400 000 F</td>
          <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-display font-bold">100%</span></td>
        </tr></tfoot>
      </table>
    </div>

    <!-- Payment Timeline -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-300">
      <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Chronologie des Paiements</h3>
      <div class="space-y-3">
        <div v-for="(payment, index) in payments" :key="index" class="flex items-start gap-4">
          <div class="flex flex-col items-center">
            <div class="w-3 h-3 bg-green-500 rounded-full mt-1.5"></div>
            <div v-if="index < payments.length - 1" class="w-0.5 h-12 bg-gray-200"></div>
          </div>
          <div class="flex-1 flex items-center justify-between p-3 bg-green-50/50 rounded-xl">
            <div>
              <p class="text-sm font-display font-semibold text-gray-800">{{ payment.label }}</p>
              <p class="text-xs text-gray-500 font-body">{{ payment.operator }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-display font-bold text-green-700">{{ payment.amount }}</p>
              <p class="text-xs text-gray-400 font-body">{{ payment.date }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
