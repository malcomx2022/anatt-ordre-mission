<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import type { Agent, OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()
const agentId = Number(route.params.id)

const loadingData = ref(true)
const activeTab = ref<'missions' | 'paiements' | 'historique'>('missions')

const agent = ref<Agent | null>(null)
const missions = ref<OrdreMission[]>([])

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/agents/${agentId}`)
    agent.value = data.data

    // Charger les missions de cet agent
    const missionsRes = await api.get(`/api/ordres?agent_id=${agentId}&per_page=100`)
    missions.value = missionsRes.data.data || []
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const missionStats = computed(() => ({
  total: missions.value.length,
  totalFcfa: missions.value.reduce((s, m) => s + Number(m.montant_per_diem), 0),
  payesIntegralement: missions.value.filter(m => m.statut === 'PAYE').length,
}))

function formatMontant(n: number) {
  return n.toLocaleString('fr-FR')
}

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <AppLayout :title="agent ? 'Fiche Agent' : 'Chargement...'" :subtitle="agent ? `${agent.matricule} - ${agent.nom} ${agent.prenom}` : ''">
    <template #header-actions>
      <router-link
        to="/agents"
        class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50"
      >
        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      </router-link>
      <router-link
        :to="`/agents/${agent.id}/modifier`"
        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2"
      >
        <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
        Modifier
      </router-link>
      <button class="px-4 py-2.5 border border-red-200 rounded-xl text-sm font-display font-medium text-red-600 hover:bg-red-50 flex items-center gap-2">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="23" y2="8"/></svg>
        Desactiver
      </button>
    </template>

    <!-- En-tete profil -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
      <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
        <div class="w-20 h-20 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-2xl font-display font-bold text-white shadow-lg shadow-anatt-500/20">
          {{ agent.nom[0] }}{{ agent.prenom[0] }}
        </div>
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-1">
            <h2 class="text-xl font-display font-bold text-gray-800">{{ agent.nom }} {{ agent.prenom }}</h2>
            <span
              class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium"
              :class="agent.actif ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
            >
              <svg class="w-1.5 h-1.5 fill-current" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              {{ agent.actif ? 'Actif' : 'Inactif' }}
            </span>
          </div>
          <p class="text-sm font-body text-gray-500 mb-3">{{ agent.categorie }} -- {{ agent.service }}</p>
          <div class="flex flex-wrap gap-3 text-sm font-body">
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              {{ agent.matricule }}
            </span>
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              {{ agent.telephone }}
            </span>
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
              {{ agent.email }}
            </span>
          </div>
        </div>
        <div class="flex gap-3">
          <div class="text-center px-4 py-3 bg-yellow-50 rounded-xl border border-yellow-200">
            <span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-0.5 rounded">MTN</span>
            <p class="font-mono text-sm mt-1 font-medium text-gray-800">{{ agent.numero_mtn }}</p>
            <p v-if="agent.numero_mtn" class="text-[10px] text-anatt-500 mt-0.5 flex items-center justify-center gap-0.5">
              <svg class="w-2 h-2" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Principal
            </p>
          </div>
          <div class="text-center px-4 py-3 bg-blue-50 rounded-xl border border-blue-200">
            <span class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-0.5 rounded">MOOV</span>
            <p class="font-mono text-sm mt-1 font-medium text-gray-800">{{ agent.numero_moov }}</p>
            <p v-if="agent.numero_moov" class="text-[10px] text-anatt-500 mt-0.5 flex items-center justify-center gap-0.5">
              <svg class="w-2 h-2" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Principal
            </p>
            <p v-else class="text-[10px] text-gray-400 mt-0.5">Secondaire</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="border-b border-gray-100 px-6 flex gap-6">
        <button
          class="py-4 text-sm font-display font-medium border-b-2 transition-colors"
          :class="activeTab === 'missions' ? 'text-anatt-500 border-anatt-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
          @click="activeTab = 'missions'"
        >
          Missions ({{ missionStats.total }})
        </button>
        <button
          class="py-4 text-sm font-display font-medium border-b-2 transition-colors"
          :class="activeTab === 'paiements' ? 'text-anatt-500 border-anatt-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
          @click="activeTab = 'paiements'"
        >
          Paiements ({{ paiements.length }})
        </button>
        <button
          class="py-4 text-sm font-display font-medium border-b-2 transition-colors"
          :class="activeTab === 'historique' ? 'text-anatt-500 border-anatt-500' : 'text-gray-500 border-transparent hover:text-gray-700'"
          @click="activeTab = 'historique'"
        >
          Historique
        </button>
      </div>

      <!-- Tab Missions -->
      <div v-if="activeTab === 'missions'" class="p-6">
        <div class="grid sm:grid-cols-3 gap-4 mb-6">
          <div class="bg-blue-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-blue-700">{{ missionStats.total }}</p>
            <p class="text-xs font-body text-blue-600">Total missions</p>
          </div>
          <div class="bg-green-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-green-700">{{ formatMontant(missionStats.totalFcfa) }}</p>
            <p class="text-xs font-body text-green-600">Total FCFA</p>
          </div>
          <div class="bg-purple-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-purple-700">{{ missionStats.payesIntegralement }}</p>
            <p class="text-xs font-body text-purple-600">Payes integralement</p>
          </div>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-display text-gray-500 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pr-4">Ref OM</th>
              <th class="pb-3 pr-4">Destination</th>
              <th class="pb-3 pr-4">Duree</th>
              <th class="pb-3 pr-4">Montant</th>
              <th class="pb-3 pr-4">Avance</th>
              <th class="pb-3 pr-4">Reliquat</th>
              <th class="pb-3">Statut</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="m in missions" :key="m.id" class="hover:bg-gray-50">
              <td class="py-3 pr-4">
                <router-link :to="`/ordres/${m.id}`" class="font-mono text-xs bg-gray-100 px-2 py-1 rounded text-anatt-500 hover:underline">{{ m.numero }}</router-link>
              </td>
              <td class="py-3 pr-4 font-body">{{ m.destination }}</td>
              <td class="py-3 pr-4 font-body">{{ m.nb_jours }} jour(s)</td>
              <td class="py-3 pr-4 font-display font-semibold">{{ formatMontant(Number(m.montant_per_diem)) }}</td>
              <td class="py-3 pr-4 font-body text-gray-500">{{ formatDate(m.date_depart) }}</td>
              <td class="py-3">
                <span
                  class="text-xs px-2 py-1 rounded-lg font-display"
                  :class="m.statut === 'PAYE' ? 'bg-green-50 text-green-700' : m.statut === 'REJETE' ? 'bg-red-50 text-red-600' : m.statut === 'SOUMIS' ? 'bg-gold-50 text-gold-600' : 'bg-blue-50 text-blue-600'"
                >
                  {{ m.statut }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab Paiements -->
      <div v-if="activeTab === 'paiements'" class="p-6">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-display text-gray-500 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pr-4">Ref Lot</th>
              <th class="pb-3 pr-4">Type</th>
              <th class="pb-3 pr-4">Operateur</th>
              <th class="pb-3 pr-4">Montant</th>
              <th class="pb-3 pr-4">Date</th>
              <th class="pb-3">Statut</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="missions.filter(m => m.statut === 'PAYE').length === 0">
              <td colspan="6" class="py-8 text-center text-sm text-gray-400 font-body">Aucun paiement pour cet agent</td>
            </tr>
            <tr v-for="m in missions.filter(m => m.statut === 'PAYE' || m.statut === 'APPROUVE_DG')" :key="'p-' + m.id" class="hover:bg-gray-50">
              <td class="py-3 pr-4 font-mono text-xs">{{ m.numero }}</td>
              <td class="py-3 pr-4 font-body">{{ m.statut === 'PAYE' ? 'Paye' : 'En attente' }}</td>
              <td class="py-3 pr-4">
                <span
                  class="text-xs font-display font-bold px-2 py-0.5 rounded"
                  :class="agent?.numero_mtn ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'"
                >
                  {{ agent?.numero_mtn ? 'MTN' : 'MOOV' }}
                </span>
              </td>
              <td class="py-3 pr-4 font-display font-semibold">{{ formatMontant(Number(m.montant_per_diem)) }}</td>
              <td class="py-3 pr-4 font-body text-gray-500">{{ formatDate(m.date_depart) }}</td>
              <td class="py-3">
                <span :class="m.statut === 'PAYE' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'" class="text-xs px-2 py-1 rounded-lg font-display">{{ m.statut === 'PAYE' ? 'Paye' : 'En attente' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab Historique -->
      <div v-if="activeTab === 'historique'" class="p-6">
        <div class="space-y-4">
          <div v-if="missions.length === 0" class="text-center py-8">
            <p class="text-sm text-gray-400 font-body">Aucune activite enregistree</p>
          </div>
          <div
            v-for="m in missions.slice(0, 5)"
            :key="'h-' + m.id"
            class="flex gap-4 items-start"
          >
            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
              :class="m.statut === 'PAYE' ? 'bg-green-100 text-green-600' : m.statut === 'REJETE' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-display font-medium text-gray-800">{{ m.numero }} - {{ m.destination }} ({{ m.statut }})</p>
              <p class="text-xs font-body text-gray-500">{{ formatDate(m.date_depart) }} - {{ formatMontant(Number(m.montant_per_diem)) }} FCFA</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
