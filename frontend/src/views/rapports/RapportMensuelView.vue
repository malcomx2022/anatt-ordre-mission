<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const selectedMonth = ref('2025-01')

const kpis = [
  { label: 'Ordres de Mission', value: '67', trend: '+12% vs Dec.', up: true },
  { label: 'Personnel en Mission', value: '42', trend: '+8% vs Dec.', up: true },
  { label: 'Montant Total Paye', value: '14.2M', trend: '-3% vs Dec.', up: false, highlight: true },
  { label: 'Taux de Paiement', value: '94.3%', trend: '+2.1 pts', up: true, green: true },
  { label: 'Jours Mission Cumules', value: '312', trend: '+18 jours', up: true },
]

const weeklyData = [
  { label: 'S1', value: 14, height: '65%' },
  { label: 'S2', value: 19, height: '85%' },
  { label: 'S3', value: 16, height: '75%' },
  { label: 'S4', value: 18, height: '95%' },
]

const directions = [
  { name: 'Direction Generale', missions: 8, agents: 5, jours: 42, montant: '3 240 000 F', budget: '22.8%' },
  { name: 'Direction Technique', missions: 22, agents: 15, jours: 108, montant: '4 860 000 F', budget: '34.2%' },
  { name: 'Direction Administrative', missions: 18, agents: 12, jours: 86, montant: '3 100 000 F', budget: '21.8%' },
  { name: 'Direction Financiere', missions: 12, agents: 7, jours: 52, montant: '2 100 000 F', budget: '14.8%' },
  { name: 'Cellule Informatique', missions: 7, agents: 3, jours: 24, montant: '900 000 F', budget: '6.4%' },
]
</script>

<template>
  <AppLayout title="Rapport Mensuel" subtitle="Synthese des missions et paiements">
    <template #header-actions>
      <select v-model="selectedMonth" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
        <option value="2025-02">Fevrier 2025</option>
        <option value="2025-01">Janvier 2025</option>
        <option value="2024-12">Decembre 2024</option>
        <option value="2024-11">Novembre 2024</option>
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
          <p class="text-2xl font-display font-bold text-anatt-700">Janvier 2025</p>
          <p class="text-xs text-gray-400 font-body">Periode : 01/01/2025 - 31/01/2025</p>
        </div>
      </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-5 gap-4 mb-6 animate-fade-up delay-100">
      <div v-for="kpi in kpis" :key="kpi.label" class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
        <p class="text-xs text-gray-500 font-body mb-1">{{ kpi.label }}</p>
        <p :class="['text-2xl font-display font-bold', kpi.highlight ? 'text-anatt-700' : kpi.green ? 'text-green-600' : 'text-gray-900']">{{ kpi.value }}</p>
        <p :class="['text-[10px] font-display font-semibold mt-1', kpi.up ? 'text-green-600' : 'text-red-500']">
          <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path v-if="kpi.up" stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25" />
          </svg>
          {{ kpi.trend }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-6">
      <!-- Chart: Missions per Week -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-6">Missions par Semaine</h3>
        <div class="flex items-end justify-between gap-3 h-40">
          <div v-for="week in weeklyData" :key="week.label" class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full bg-anatt-500 rounded-t-lg transition-all duration-700" :style="{ height: week.height }"></div>
            <span class="text-[10px] text-gray-400 font-body">{{ week.label }}</span>
            <span class="text-xs font-display font-bold text-gray-700">{{ week.value }}</span>
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
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><span class="w-3 h-3 bg-yellow-400 rounded-full"></span><span class="text-sm font-body font-medium text-gray-700">MTN MoMo</span></div>
              <div class="text-right"><p class="text-sm font-display font-bold text-gray-800">8 736 000 F</p><p class="text-[10px] text-gray-400 font-body">65% - 38 transactions</p></div>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2"><span class="w-3 h-3 bg-blue-500 rounded-full"></span><span class="text-sm font-body font-medium text-gray-700">MOOV Flooz</span></div>
              <div class="text-right"><p class="text-sm font-display font-bold text-gray-800">5 464 000 F</p><p class="text-[10px] text-gray-400 font-body">35% - 29 transactions</p></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-300">
      <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-display font-semibold text-gray-700">Repartition par Direction</h3></div>
      <table class="w-full">
        <thead><tr class="bg-gray-50 border-b border-gray-100">
          <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Direction</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Missions</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Agents</th>
          <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Jours</th>
          <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant</th>
          <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">% Budget</th>
        </tr></thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="dir in directions" :key="dir.name" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ dir.name }}</td>
            <td class="px-5 py-3 text-center text-sm font-body">{{ dir.missions }}</td>
            <td class="px-5 py-3 text-center text-sm font-body">{{ dir.agents }}</td>
            <td class="px-5 py-3 text-center text-sm font-body">{{ dir.jours }}</td>
            <td class="px-5 py-3 text-right text-sm font-display font-semibold">{{ dir.montant }}</td>
            <td class="px-5 py-3 text-right"><span class="text-sm font-display font-bold text-anatt-700">{{ dir.budget }}</span></td>
          </tr>
        </tbody>
        <tfoot><tr class="bg-anatt-50 border-t-2 border-anatt-200">
          <td class="px-5 py-3 text-sm font-display font-bold text-anatt-800">TOTAL</td>
          <td class="px-5 py-3 text-center text-sm font-display font-bold text-anatt-800">67</td>
          <td class="px-5 py-3 text-center text-sm font-display font-bold text-anatt-800">42</td>
          <td class="px-5 py-3 text-center text-sm font-display font-bold text-anatt-800">312</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">14 200 000 F</td>
          <td class="px-5 py-3 text-right text-sm font-display font-bold text-anatt-800">100%</td>
        </tr></tfoot>
      </table>
    </div>
  </AppLayout>
</template>
