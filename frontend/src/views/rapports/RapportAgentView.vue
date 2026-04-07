<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const searchQuery = ref('ADJOVI Kossi Pierre')
const selectedYear = ref('2025')

const agent = {
  initials: 'AK',
  nom: 'ADJOVI Kossi Pierre',
  matricule: 'ANT-2019-0045',
  categorie: 'B',
  direction: 'Direction Technique',
  service: 'Chef de Service Controle',
}

const agentStats = [
  { value: '12', label: 'Missions', bg: 'bg-blue-50', color: 'text-blue-700' },
  { value: '54', label: 'Jours', bg: 'bg-green-50', color: 'text-green-700' },
  { value: '2.43M', label: 'Total Paye', bg: 'bg-purple-50', color: 'text-purple-700' },
  { value: '100%', label: 'Taux Paie', bg: 'bg-amber-50', color: 'text-amber-700' },
]

const missions = [
  { ref: 'OM-2025-0089', objet: 'Controle routier corridor Cotonou-Parakou', destination: 'Parakou', periode: '05-09/02', jours: 5, indemnite: '250 000 F', avance: '83 333 F', solde: '166 667 F', statut: 'Paye' },
  { ref: 'OM-2025-0072', objet: 'Inspection postes de peage Bohicon', destination: 'Bohicon', periode: '20-23/01', jours: 4, indemnite: '200 000 F', avance: '66 667 F', solde: '133 333 F', statut: 'Paye' },
  { ref: 'OM-2025-0058', objet: 'Formation agents gare routiere Natitingou', destination: 'Natitingou', periode: '12-14/01', jours: 3, indemnite: '150 000 F', avance: '50 000 F', solde: '100 000 F', statut: 'Paye' },
]

const payments = [
  { label: 'Solde OM-2025-0089', operator: 'MTN MoMo - 95 12 34 56', amount: '+166 667 F', date: '12/02/2025' },
  { label: 'Avance OM-2025-0089', operator: 'MTN MoMo - 95 12 34 56', amount: '+83 333 F', date: '04/02/2025' },
  { label: 'Solde OM-2025-0072', operator: 'MTN MoMo - 95 12 34 56', amount: '+133 333 F', date: '27/01/2025' },
]
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
        <select v-model="selectedYear" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="2025">Annee 2025</option>
          <option value="2024">Annee 2024</option>
        </select>
        <button class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          Generer
        </button>
      </div>
    </div>

    <!-- Agent Profile Card -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-5">
          <div class="w-16 h-16 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-white text-xl font-display font-bold">{{ agent.initials }}</div>
          <div>
            <h2 class="text-lg font-display font-bold text-gray-900">{{ agent.nom }}</h2>
            <p class="text-sm text-gray-500 font-body">Matricule : {{ agent.matricule }} - Categorie {{ agent.categorie }}</p>
            <p class="text-xs text-gray-400 mt-1 font-body flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
              {{ agent.direction }} - {{ agent.service }}
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
          <tr v-for="m in missions" :key="m.ref" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-3"><span class="text-sm font-body font-medium text-anatt-500 hover:underline cursor-pointer">{{ m.ref }}</span></td>
            <td class="px-5 py-3 text-sm font-body text-gray-700">{{ m.objet }}</td>
            <td class="px-5 py-3 text-sm font-body text-gray-600">{{ m.destination }}</td>
            <td class="px-5 py-3 text-center text-xs font-body text-gray-600">{{ m.periode }}</td>
            <td class="px-5 py-3 text-center text-sm font-display font-semibold">{{ m.jours }}</td>
            <td class="px-5 py-3 text-right text-sm font-display font-semibold text-gray-800">{{ m.indemnite }}</td>
            <td class="px-5 py-3 text-right text-xs font-body text-green-600">{{ m.avance }}</td>
            <td class="px-5 py-3 text-right text-xs font-body text-green-600">{{ m.solde }}</td>
            <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 bg-green-50 text-green-700 rounded-full text-xs font-display font-medium">{{ m.statut }}</span></td>
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
