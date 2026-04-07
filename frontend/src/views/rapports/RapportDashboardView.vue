<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const reportTypes = [
  { title: 'Rapport Mensuel', description: 'Synthese mensuelle des missions et paiements', route: '/rapports/mensuel', color: 'from-blue-500 to-blue-600', textColor: 'text-blue-600' },
  { title: 'Rapport Personnel', description: 'Detail des missions par agent', route: '/rapports/agents', color: 'from-purple-500 to-purple-600', textColor: 'text-purple-600' },
  { title: 'Rapport Financier', description: 'Suivi budgetaire et execution financiere', route: '#', color: 'from-green-500 to-green-600', textColor: 'text-green-600' },
  { title: 'Rapport Statistique', description: 'Analyses croisees et tendances', route: '#', color: 'from-amber-500 to-amber-600', textColor: 'text-amber-600' },
]

const recentReports = [
  { name: 'Rapport Mensuel - Janvier 2025', date: '01/02/2025', pages: '12 pages', format: 'PDF', iconBg: 'bg-red-50', iconColor: 'text-red-500' },
  { name: 'Export Personnel - Q4 2024', date: '15/01/2025', pages: '156 lignes', format: 'Excel', iconBg: 'bg-green-50', iconColor: 'text-green-600' },
  { name: 'Rapport Mensuel - Decembre 2024', date: '02/01/2025', pages: '10 pages', format: 'PDF', iconBg: 'bg-red-50', iconColor: 'text-red-500' },
  { name: 'Rapport Annuel 2024', date: '31/12/2024', pages: '45 pages', format: 'PDF', iconBg: 'bg-blue-50', iconColor: 'text-blue-600' },
]

const scheduledReports = [
  { name: 'Rapport Mensuel Fevrier', date: 'Generation auto le 01/03/2025', bg: 'bg-blue-50', textColor: 'text-blue-800', subColor: 'text-blue-600' },
  { name: 'Export Trimestriel Q1', date: 'Generation auto le 01/04/2025', bg: 'bg-purple-50', textColor: 'text-purple-800', subColor: 'text-purple-600' },
]

const stats = [
  { label: 'Rapports generes (2025)', value: '23' },
  { label: 'Exports realises', value: '47' },
  { label: 'Taille totale', value: '128 MB' },
  { label: 'Dernier rapport', value: '01/02/2025' },
]
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
          <div v-for="report in recentReports" :key="report.name" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50">
            <div class="flex items-center gap-4">
              <div :class="[report.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center']">
                <svg :class="[report.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-gray-800">{{ report.name }}</p>
                <p class="text-xs text-gray-400 font-body">Genere le {{ report.date }} - {{ report.pages }} - {{ report.format }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-blue-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              </button>
              <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              </button>
            </div>
          </div>
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
