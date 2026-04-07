<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'

const route = useRoute()
const router = useRouter()

const activeTab = ref<'missions' | 'paiements' | 'historique'>('missions')

const agent = ref({
  id: Number(route.params.id) || 1,
  matricule: 'MAT-0045',
  nom: 'MAGBE',
  prenom: 'Victorin',
  email: 'magbe.v@anatt.bj',
  grade: 'Cadre',
  service: 'Direction des Transports Routiers',
  salaire: 850000,
  categorie: '>= 800 000 FCFA',
  numero_mtn: '229 99 70 74 80',
  numero_moov: '229 01 23 45 67',
  operateur_principal: 'MTN',
  actif: true
})

const missions = ref([
  { ref: 'OM-2024-000793', destination: 'Parakou', duree: '3 jours', montant: 60000, avance: 20000, avancePaye: true, reliquat: 40000, reliquatPaye: true, statut: 'Paye' },
  { ref: 'OM-2024-000785', destination: 'Natitingou', duree: '5 jours', montant: 100000, avance: 33333, avancePaye: true, reliquat: 66667, reliquatPaye: false, statut: 'Avance payee' },
  { ref: 'OM-2024-000770', destination: 'Abomey', duree: '2 jours', montant: 40000, avance: 13333, avancePaye: true, reliquat: 26667, reliquatPaye: true, statut: 'Paye' }
])

const paiements = ref([
  { ref: 'LOT-MTN-20241204', type: 'Reliquat', operateur: 'MTN', montant: 40000, date: '04/12/2024', statut: 'Paye' },
  { ref: 'LOT-MTN-20241201', type: 'Avance', operateur: 'MTN', montant: 20000, date: '01/12/2024', statut: 'Paye' }
])

const historique = ref([
  { icon: 'money', color: 'green', text: 'Paiement reliquat effectue -- 40 000 FCFA', date: '04/12/2024 a 14:30 par Admin Finances' },
  { icon: 'file', color: 'blue', text: 'OM-2024-000793 valide', date: '02/12/2024 a 09:15 par Directeur' },
  { icon: 'edit', color: 'purple', text: 'Numero MTN mis a jour', date: '28/11/2024 a 16:45 par Operateur Saisie' },
  { icon: 'user', color: 'gray', text: 'Agent cree dans le systeme', date: '15/10/2024 a 10:00 par Super Admin' }
])

const missionStats = computed(() => ({
  total: 12,
  totalFcfa: 720000,
  payesIntegralement: 8
}))

function formatMontant(n: number) {
  return n.toLocaleString('fr-FR')
}

function getHistoryIcon(icon: string) {
  switch (icon) {
    case 'money': return 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'
    case 'file': return 'M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z'
    case 'edit': return 'M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z'
    case 'user': return 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'
    default: return ''
  }
}

function getHistoryColorClasses(color: string) {
  const map: Record<string, string> = {
    green: 'bg-green-100 text-green-600',
    blue: 'bg-blue-100 text-blue-600',
    purple: 'bg-purple-100 text-purple-600',
    gray: 'bg-gray-100 text-gray-600'
  }
  return map[color] || map.gray
}
</script>

<template>
  <AppLayout title="Fiche Agent" :subtitle="`${agent.matricule} - ${agent.nom} ${agent.prenom}`">
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
          <p class="text-sm font-body text-gray-500 mb-3">{{ agent.grade }} -- {{ agent.service }}</p>
          <div class="flex flex-wrap gap-3 text-sm font-body">
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              {{ agent.matricule }}
            </span>
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              {{ formatMontant(agent.salaire) }} FCFA
            </span>
            <span class="flex items-center gap-1.5 text-gray-600">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
              {{ agent.categorie }}
            </span>
          </div>
        </div>
        <div class="flex gap-3">
          <div class="text-center px-4 py-3 bg-yellow-50 rounded-xl border border-yellow-200">
            <span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-0.5 rounded">MTN</span>
            <p class="font-mono text-sm mt-1 font-medium text-gray-800">{{ agent.numero_mtn }}</p>
            <p v-if="agent.operateur_principal === 'MTN'" class="text-[10px] text-anatt-500 mt-0.5 flex items-center justify-center gap-0.5">
              <svg class="w-2 h-2" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Principal
            </p>
          </div>
          <div class="text-center px-4 py-3 bg-blue-50 rounded-xl border border-blue-200">
            <span class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-0.5 rounded">MOOV</span>
            <p class="font-mono text-sm mt-1 font-medium text-gray-800">{{ agent.numero_moov }}</p>
            <p v-if="agent.operateur_principal !== 'MTN'" class="text-[10px] text-anatt-500 mt-0.5 flex items-center justify-center gap-0.5">
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
            <tr v-for="m in missions" :key="m.ref" class="hover:bg-gray-50">
              <td class="py-3 pr-4">
                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ m.ref }}</span>
              </td>
              <td class="py-3 pr-4 font-body">{{ m.destination }}</td>
              <td class="py-3 pr-4 font-body">{{ m.duree }}</td>
              <td class="py-3 pr-4 font-display font-semibold">{{ formatMontant(m.montant) }}</td>
              <td class="py-3 pr-4" :class="m.avancePaye ? 'text-green-600' : 'text-orange-600'">
                {{ formatMontant(m.avance) }} {{ m.avancePaye ? '&#10003;' : '&#9203;' }}
              </td>
              <td class="py-3 pr-4" :class="m.reliquatPaye ? 'text-green-600' : 'text-orange-600'">
                {{ formatMontant(m.reliquat) }} {{ m.reliquatPaye ? '&#10003;' : '&#9203;' }}
              </td>
              <td class="py-3">
                <span
                  class="text-xs px-2 py-1 rounded-lg font-display"
                  :class="m.statut === 'Paye' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'"
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
            <tr v-for="p in paiements" :key="p.ref" class="hover:bg-gray-50">
              <td class="py-3 pr-4 font-mono text-xs">{{ p.ref }}</td>
              <td class="py-3 pr-4 font-body">{{ p.type }}</td>
              <td class="py-3 pr-4">
                <span
                  class="text-xs font-display font-bold px-2 py-0.5 rounded"
                  :class="p.operateur === 'MTN' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'"
                >
                  {{ p.operateur }}
                </span>
              </td>
              <td class="py-3 pr-4 font-display font-semibold">{{ formatMontant(p.montant) }}</td>
              <td class="py-3 pr-4 font-body text-gray-500">{{ p.date }}</td>
              <td class="py-3">
                <span class="bg-green-50 text-green-700 text-xs px-2 py-1 rounded-lg font-display">{{ p.statut }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tab Historique -->
      <div v-if="activeTab === 'historique'" class="p-6">
        <div class="space-y-4">
          <div
            v-for="(item, idx) in historique"
            :key="idx"
            class="flex gap-4 items-start"
          >
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
              :class="getHistoryColorClasses(item.color)"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path :d="getHistoryIcon(item.icon)" />
                <circle v-if="item.icon === 'user'" cx="9" cy="7" r="4" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-display font-medium text-gray-800">{{ item.text }}</p>
              <p class="text-xs font-body text-gray-500">{{ item.date }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
