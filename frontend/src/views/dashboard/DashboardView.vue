<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  ArcElement,
  CategoryScale,
  LinearScale
} from 'chart.js'

ChartJS.register(Title, Tooltip, Legend, BarElement, ArcElement, CategoryScale, LinearScale)

const router = useRouter()

// --- Mock data ---

const stats = ref([
  {
    label: 'Ordres de Mission',
    value: '156',
    trend: '+12%',
    trendUp: true,
    iconBg: 'bg-blue-100',
    iconColor: 'text-blue-600',
    icon: 'file',
    tags: [
      { text: '98 valides', color: 'text-anatt-600 bg-anatt-50' },
      { text: '12 en attente', color: 'text-orange-600 bg-orange-50' }
    ]
  },
  {
    label: 'Personnel Actif',
    value: '87',
    trend: 'Actif',
    trendUp: false,
    trendNeutral: true,
    iconBg: 'bg-purple-100',
    iconColor: 'text-purple-600',
    icon: 'users',
    tags: [
      { text: '52 MTN', color: 'text-purple-600 bg-purple-50' },
      { text: '35 MOOV', color: 'text-purple-600 bg-purple-50' }
    ]
  },
  {
    label: 'Total Paiements (FCFA)',
    value: '24.5M',
    trend: '+8%',
    trendUp: true,
    iconBg: 'bg-anatt-100',
    iconColor: 'text-anatt-600',
    icon: 'wallet',
    tags: [
      { text: 'MTN: 15.2M', color: 'text-yellow-700 bg-yellow-50' },
      { text: 'MOOV: 9.3M', color: 'text-blue-600 bg-blue-50' }
    ]
  },
  {
    label: 'Fichiers Generes',
    value: '28',
    trend: 'Ce mois',
    trendUp: false,
    trendNeutral: true,
    iconBg: 'bg-orange-100',
    iconColor: 'text-orange-600',
    icon: 'export',
    tags: [
      { text: '23 payes', color: 'text-anatt-600 bg-anatt-50' },
      { text: '5 en cours', color: 'text-red-600 bg-red-50' }
    ]
  }
])

const recentMissions = ref([
  { ref: 'OM-2026-000793', agent: 'MAGBE Victorin', montant: '60 000', statut: 'APPROUVE_DG', date: '04/04/2026' },
  { ref: 'OM-2026-000794', agent: 'ADJOVI Pierre', montant: '45 000', statut: 'SOUMIS', date: '03/04/2026' },
  { ref: 'OM-2026-000792', agent: 'DOSSA Marie', montant: '35 000', statut: 'PAYE', date: '02/04/2026' },
  { ref: 'OM-2026-000791', agent: 'SAOSSI Paterne', montant: '88 000', statut: 'VALIDE_N1', date: '01/04/2026' },
  { ref: 'OM-2026-000790', agent: 'KONE Jean-Baptiste', montant: '40 000', statut: 'REJETE', date: '30/03/2026' }
])

const recentPaiements = ref([
  { operateur: 'MTN', lot: 'LOT-MTN-20260404-001', montant: '1 250 000', beneficiaires: 15, statut: 'SUCCES' },
  { operateur: 'MOOV', lot: 'LOT-MOOV-20260403-002', montant: '876 000', beneficiaires: 11, statut: 'EN_ATTENTE' },
  { operateur: 'MTN', lot: 'LOT-MTN-20260402-003', montant: '2 100 000', beneficiaires: 22, statut: 'SUCCES' }
])

const operateurRepartition = ref([
  { label: 'MTN MoMo', pct: 62, color: 'bg-yellow-500' },
  { label: 'MOOV Flooz', pct: 38, color: 'bg-blue-500' }
])

// Chart data
const barChartData = ref({
  labels: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun'],
  datasets: [
    {
      label: 'Ordres de Mission',
      backgroundColor: '#1D9E4F',
      borderRadius: 6,
      data: [22, 18, 30, 25, 35, 28]
    },
    {
      label: 'Paiements',
      backgroundColor: '#D4A843',
      borderRadius: 6,
      data: [18, 15, 26, 22, 30, 24]
    }
  ]
})

const barChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: { font: { family: 'DM Sans', size: 12 }, usePointStyle: true, padding: 20 }
    }
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 11 } } },
    y: { grid: { color: '#f3f4f6' }, ticks: { font: { family: 'DM Sans', size: 11 } } }
  }
})

function statutLabel(statut: string) {
  const map: Record<string, { text: string; classes: string }> = {
    BROUILLON: { text: 'Brouillon', classes: 'bg-blue-50 text-blue-700' },
    SOUMIS: { text: 'En attente', classes: 'bg-orange-50 text-orange-700' },
    VALIDE_N1: { text: 'Valide N1', classes: 'bg-anatt-50 text-anatt-600' },
    APPROUVE_DG: { text: 'Valide', classes: 'bg-anatt-50 text-anatt-700' },
    PAYE: { text: 'Paye', classes: 'bg-blue-50 text-blue-700' },
    REJETE: { text: 'Rejete', classes: 'bg-red-50 text-red-700' }
  }
  return map[statut] || { text: statut, classes: 'bg-gray-50 text-gray-600' }
}

function paiementStatutLabel(statut: string) {
  const map: Record<string, { text: string; classes: string }> = {
    SUCCES: { text: 'Paye', classes: 'text-anatt-600' },
    EN_ATTENTE: { text: 'En cours', classes: 'text-orange-600' },
    ECHEC: { text: 'Echec', classes: 'text-red-600' }
  }
  return map[statut] || { text: statut, classes: 'text-gray-600' }
}
</script>

<template>
  <AppLayout title="Tableau de bord" subtitle="Vue d'ensemble du systeme de paiements">
    <template #header-actions>
      <div class="flex items-center gap-3">
        <div class="hidden sm:flex items-center gap-2 bg-gray-100 rounded-xl px-3 py-2">
          <!-- Calendar icon -->
          <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          <span class="text-sm font-body text-gray-600">Avr 2026</span>
        </div>
      </div>
    </template>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6">
      <div
        v-for="(stat, idx) in stats"
        :key="idx"
        class="stat-card card-hover animate-fade-in bg-white rounded-2xl p-5 border border-gray-100 cursor-pointer"
        :style="{ animationDelay: `${(idx + 1) * 0.1}s` }"
      >
        <div class="flex items-start justify-between mb-3">
          <div :class="['w-11 h-11 rounded-xl flex items-center justify-center', stat.iconBg]">
            <!-- File icon -->
            <svg v-if="stat.icon === 'file'" :class="['w-5 h-5', stat.iconColor]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
              <polyline points="10 9 9 9 8 9" />
            </svg>
            <!-- Users icon -->
            <svg v-else-if="stat.icon === 'users'" :class="['w-5 h-5', stat.iconColor]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <!-- Wallet icon -->
            <svg v-else-if="stat.icon === 'wallet'" :class="['w-5 h-5', stat.iconColor]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4" />
              <path d="M3 5v14a2 2 0 0 0 2 2h16v-5" />
              <path d="M18 12a2 2 0 0 0 0 4h4v-4z" />
            </svg>
            <!-- Export icon -->
            <svg v-else-if="stat.icon === 'export'" :class="['w-5 h-5', stat.iconColor]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="17 8 12 3 7 8" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
          </div>
          <span
            v-if="stat.trendUp"
            class="text-xs text-anatt-600 font-semibold bg-anatt-50 px-2 py-1 rounded-lg flex items-center gap-1"
          >
            <!-- Arrow up -->
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="19" x2="12" y2="5" />
              <polyline points="5 12 12 5 19 12" />
            </svg>
            {{ stat.trend }}
          </span>
          <span
            v-else-if="stat.trendNeutral"
            class="text-xs text-gray-500 font-semibold bg-gray-50 px-2 py-1 rounded-lg"
          >
            {{ stat.trend }}
          </span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-800">{{ stat.value }}</p>
        <p class="text-sm font-body text-gray-500">{{ stat.label }}</p>
        <div class="mt-3 flex gap-2 text-[11px]">
          <span
            v-for="(tag, tIdx) in stat.tags"
            :key="tIdx"
            :class="['px-2 py-0.5 rounded font-body', tag.color]"
          >
            {{ tag.text }}
          </span>
        </div>
      </div>
    </div>

    <!-- 2 columns: chart + sidebar -->
    <div class="grid lg:grid-cols-3 gap-6 mb-6">
      <!-- Chart area -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-5 animate-fade-in">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-display font-bold text-gray-800">Evolution Mensuelle</h3>
          <span class="text-xs font-body text-gray-500">6 derniers mois</span>
        </div>
        <div class="h-64">
          <Bar :data="barChartData" :options="barChartOptions" />
        </div>
      </div>

      <!-- Quick actions + operator split -->
      <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-in">
          <h3 class="font-display font-bold text-gray-800 mb-4">Actions rapides</h3>
          <div class="space-y-2">
            <!-- New OM -->
            <router-link to="/ordres/nouveau" class="flex items-center gap-3 p-3 rounded-xl hover:bg-anatt-50 transition-colors group">
              <div class="w-10 h-10 bg-anatt-100 rounded-lg flex items-center justify-center group-hover:bg-anatt-200 transition-colors">
                <svg class="w-5 h-5 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="12" y1="5" x2="12" y2="19" />
                  <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-display font-medium text-gray-800">Nouvel Ordre de Mission</p>
                <p class="text-[11px] font-body text-gray-500">Creer un OM</p>
              </div>
            </router-link>

            <!-- Generate files -->
            <router-link to="/paiements/moov" class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-50 transition-colors group">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="17 8 12 3 7 8" />
                  <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-display font-medium text-gray-800">Generer Fichiers</p>
                <p class="text-[11px] font-body text-gray-500">MTN / MOOV</p>
              </div>
            </router-link>

            <!-- Manage personnel -->
            <router-link to="/agents" class="flex items-center gap-3 p-3 rounded-xl hover:bg-purple-50 transition-colors group">
              <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                <svg class="w-5 h-5 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                  <circle cx="8.5" cy="7" r="4" />
                  <line x1="20" y1="8" x2="20" y2="14" />
                  <line x1="23" y1="11" x2="17" y2="11" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-display font-medium text-gray-800">Gerer Personnel</p>
                <p class="text-[11px] font-body text-gray-500">87 agents actifs</p>
              </div>
            </router-link>

            <!-- Reports -->
            <router-link to="/rapports" class="flex items-center gap-3 p-3 rounded-xl hover:bg-amber-50 transition-colors group">
              <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center group-hover:bg-amber-200 transition-colors">
                <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21.21 15.89A10 10 0 1 1 8 2.83" />
                  <path d="M22 12A10 10 0 0 0 12 2v10z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-display font-medium text-gray-800">Rapports</p>
                <p class="text-[11px] font-body text-gray-500">Etats & statistiques</p>
              </div>
            </router-link>
          </div>
        </div>

        <!-- Operator distribution -->
        <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-in">
          <h3 class="font-display font-bold text-gray-800 mb-4">Repartition Operateurs</h3>
          <div class="space-y-3">
            <div v-for="op in operateurRepartition" :key="op.label">
              <div class="flex justify-between text-sm mb-1">
                <span class="font-display font-medium text-gray-700 flex items-center gap-2">
                  <span :class="['w-2 h-2 rounded-full inline-block', op.color]"></span>
                  {{ op.label }}
                </span>
                <span class="font-display font-bold">{{ op.pct }}%</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2.5">
                <div
                  :class="[op.color, 'h-2.5 rounded-full transition-all duration-700']"
                  :style="{ width: op.pct + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent missions table -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-in mb-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-bold text-gray-800">Ordres de Mission Recents</h3>
        <router-link to="/ordres" class="text-sm text-anatt-600 hover:text-anatt-500 font-display font-medium flex items-center gap-1">
          Voir tout
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
          </svg>
        </router-link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-display text-gray-500 uppercase tracking-wider border-b border-gray-100">
              <th class="pb-3 pr-4">Reference</th>
              <th class="pb-3 pr-4">Missionnaire</th>
              <th class="pb-3 pr-4">Montant</th>
              <th class="pb-3 pr-4">Statut</th>
              <th class="pb-3">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="mission in recentMissions"
              :key="mission.ref"
              class="hover:bg-anatt-50/30 transition-colors cursor-pointer"
            >
              <td class="py-3 pr-4">
                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ mission.ref }}</span>
              </td>
              <td class="py-3 pr-4 font-body font-medium text-gray-900">{{ mission.agent }}</td>
              <td class="py-3 pr-4 font-display font-semibold text-gray-900">{{ mission.montant }}</td>
              <td class="py-3 pr-4">
                <span
                  :class="['inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium', statutLabel(mission.statut).classes]"
                >
                  <!-- Check icon for approved/validated -->
                  <svg v-if="mission.statut === 'APPROUVE_DG' || mission.statut === 'VALIDE_N1'" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  <!-- Clock icon for pending -->
                  <svg v-else-if="mission.statut === 'SOUMIS'" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                  <!-- Banknote icon for paid -->
                  <svg v-else-if="mission.statut === 'PAYE'" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="6" width="20" height="12" rx="2" />
                    <circle cx="12" cy="12" r="2" />
                    <path d="M6 12h.01M18 12h.01" />
                  </svg>
                  <!-- X icon for rejected -->
                  <svg v-else-if="mission.statut === 'REJETE'" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                  </svg>
                  {{ statutLabel(mission.statut).text }}
                </span>
              </td>
              <td class="py-3 font-body text-gray-500 text-xs">{{ mission.date }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent payment lots -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-in">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-display font-bold text-gray-800">Derniers Lots de Paiement</h3>
        <router-link to="/paiements" class="text-sm text-anatt-600 hover:text-anatt-500 font-display font-medium flex items-center gap-1">
          Voir tout
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12" />
            <polyline points="12 5 19 12 12 19" />
          </svg>
        </router-link>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="paiement in recentPaiements"
          :key="paiement.lot"
          class="border border-gray-100 rounded-xl p-4 card-hover cursor-pointer"
        >
          <div class="flex items-center justify-between mb-3">
            <span
              :class="[
                'text-xs font-display font-bold px-2.5 py-1 rounded-lg',
                paiement.operateur === 'MTN' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'
              ]"
            >
              {{ paiement.operateur }}
            </span>
            <span :class="['text-xs font-display font-medium flex items-center gap-1', paiementStatutLabel(paiement.statut).classes]">
              <!-- Check circle for success -->
              <svg v-if="paiement.statut === 'SUCCES'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              <!-- Clock for pending -->
              <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
              {{ paiementStatutLabel(paiement.statut).text }}
            </span>
          </div>
          <p class="font-mono text-xs font-body text-gray-500">{{ paiement.lot }}</p>
          <p class="text-lg font-display font-bold text-gray-800 mt-1">{{ paiement.montant }} FCFA</p>
          <p class="text-xs font-body text-gray-500 mt-2 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            {{ paiement.beneficiaires }} beneficiaires
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
