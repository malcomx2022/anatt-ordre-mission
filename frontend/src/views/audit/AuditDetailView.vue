<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'

const route = useRoute()

const auditId = 'AUD-2025-004721'

const metadata = [
  { label: 'Date & Heure', value: '15/01/2025 a 13:55:42' },
  { label: 'Utilisateur', value: 'Gislain A.', initials: 'GA' },
  { label: 'Adresse IP', value: '192.168.1.45', mono: true },
  { label: 'Navigateur', value: 'Chrome 120 - Windows 11' },
]

const beforeValues = [
  { label: 'Indemnite journaliere', value: '25 000 FCFA', changed: true },
  { label: 'Indemnite de nuitee', value: '35 000 FCFA', changed: true },
  { label: 'Indemnite de transport', value: '15 000 FCFA', changed: false },
  { label: 'Plafond mensuel', value: '500 000 FCFA', changed: false },
]

const afterValues = [
  { label: 'Indemnite journaliere', value: '28 000 FCFA', changed: true, pct: '+12.0%' },
  { label: 'Indemnite de nuitee', value: '40 000 FCFA', changed: true, pct: '+14.3%' },
  { label: 'Indemnite de transport', value: '15 000 FCFA', changed: false },
  { label: 'Plafond mensuel', value: '500 000 FCFA', changed: false },
]

const impactStats = [
  { value: '+8 000', label: 'FCFA / jour / agent Cat. A', bg: 'bg-amber-50', color: 'text-amber-700' },
  { value: '23', label: 'Agents concernes (Cat. A)', bg: 'bg-blue-50', color: 'text-blue-700' },
  { value: '+184 000', label: 'FCFA / jour impact total', bg: 'bg-purple-50', color: 'text-purple-700' },
]
</script>

<template>
  <AppLayout title="Detail de l'action" :subtitle="`Trace d'audit #${auditId}`">
    <template #header-actions>
      <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-body text-gray-600 hover:bg-gray-50 flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Precedent
      </button>
      <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-body text-gray-600 hover:bg-gray-50 flex items-center gap-1">
        Suivant
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
      </button>
    </template>

    <!-- Action Summary -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
      <div class="flex items-start justify-between mb-6">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 bg-amber-100 rounded-2xl flex items-center justify-center">
            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
          </div>
          <div>
            <h2 class="text-lg font-display font-bold text-gray-900">Modification de bareme</h2>
            <p class="text-sm text-gray-500 font-body mt-1">Modification des indemnites journalieres pour la Categorie A</p>
            <div class="flex items-center gap-3 mt-3">
              <span class="px-2.5 py-1 bg-amber-100 text-amber-700 text-xs font-display font-semibold rounded-lg">Modification</span>
              <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-xs font-display font-semibold rounded-lg">Baremes</span>
            </div>
          </div>
        </div>
        <span class="px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-display font-semibold rounded-full border border-amber-200 flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
          Impact financier
        </span>
      </div>

      <!-- Metadata Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl">
        <div v-for="meta in metadata" :key="meta.label">
          <p class="text-[10px] uppercase text-gray-400 font-display font-semibold mb-1">{{ meta.label }}</p>
          <div v-if="meta.initials" class="flex items-center gap-2">
            <div class="w-6 h-6 bg-anatt-100 rounded-md flex items-center justify-center text-[9px] font-display font-bold text-anatt-700">{{ meta.initials }}</div>
            <p class="text-sm font-display font-semibold text-gray-800">{{ meta.value }}</p>
          </div>
          <p v-else :class="meta.mono ? 'font-mono' : 'font-display font-semibold'" class="text-sm text-gray-800">{{ meta.value }}</p>
        </div>
      </div>
    </div>

    <!-- Before / After Comparison -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
        Comparaison Avant / Apres
      </h3>
      <div class="grid grid-cols-2 gap-6">
        <!-- Before -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <div class="w-6 h-6 bg-red-100 rounded-md flex items-center justify-center">
              <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" /></svg>
            </div>
            <h4 class="text-sm font-display font-bold text-red-700">Avant modification</h4>
          </div>
          <div class="space-y-2">
            <div v-for="v in beforeValues" :key="v.label" :class="v.changed ? 'bg-red-50 border-l-[3px] border-red-500' : 'bg-gray-50 border-l-[3px] border-gray-200'" class="p-3 rounded-r-lg">
              <p class="text-[10px] uppercase text-gray-400 font-display font-semibold">{{ v.label }}</p>
              <p :class="v.changed ? 'text-red-700 line-through' : 'text-gray-700'" class="text-sm font-display font-semibold">{{ v.value }}</p>
              <p v-if="!v.changed" class="text-[10px] text-gray-400 italic font-body">Inchange</p>
            </div>
          </div>
        </div>
        <!-- After -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <div class="w-6 h-6 bg-green-100 rounded-md flex items-center justify-center">
              <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            </div>
            <h4 class="text-sm font-display font-bold text-green-700">Apres modification</h4>
          </div>
          <div class="space-y-2">
            <div v-for="v in afterValues" :key="v.label" :class="v.changed ? 'bg-green-50 border-l-[3px] border-green-500' : 'bg-gray-50 border-l-[3px] border-gray-200'" class="p-3 rounded-r-lg">
              <p class="text-[10px] uppercase text-gray-400 font-display font-semibold">{{ v.label }}</p>
              <p :class="v.changed ? 'text-green-700 font-bold' : 'text-gray-700 font-semibold'" class="text-sm font-display">{{ v.value }}</p>
              <p v-if="v.pct" class="text-[10px] text-green-600 font-body">
                <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" /></svg>
                {{ v.pct }}
              </p>
              <p v-if="!v.changed" class="text-[10px] text-gray-400 italic font-body">Inchange</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Impact Analysis -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-200">
      <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" /></svg>
        Analyse d'impact
      </h3>
      <div class="grid grid-cols-3 gap-4 mb-4">
        <div v-for="stat in impactStats" :key="stat.label" :class="[stat.bg, 'p-4 rounded-xl text-center']">
          <p :class="[stat.color, 'text-2xl font-display font-bold']">{{ stat.value }}</p>
          <p :class="[stat.color, 'text-xs font-body']">{{ stat.label }}</p>
        </div>
      </div>
      <div class="p-4 bg-gray-50 rounded-xl">
        <p class="text-sm text-gray-600 font-body">
          <svg class="w-4 h-4 text-blue-500 inline mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
          Cette modification impacte retroactivement les ordres de mission non encore payes pour les agents de categorie A. <span class="font-semibold">3 ordres de mission</span> en attente seront recalcules.
        </p>
      </div>
    </div>

    <!-- Technical Details -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-300">
      <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>
        Details techniques
      </h3>
      <div class="bg-gray-900 rounded-xl p-5 font-mono text-sm overflow-x-auto">
        <div class="space-y-1">
          <p class="text-gray-500">// Requete de modification</p>
          <p><span class="text-purple-400">UPDATE</span> <span class="text-blue-400">baremes</span></p>
          <p><span class="text-purple-400">SET</span></p>
          <p class="pl-4"><span class="text-green-400">indemnite_journaliere</span> = <span class="text-amber-400">28000</span>,</p>
          <p class="pl-4"><span class="text-green-400">indemnite_nuitee</span> = <span class="text-amber-400">40000</span>,</p>
          <p class="pl-4"><span class="text-green-400">updated_at</span> = <span class="text-amber-400">'2025-01-15 13:55:42'</span>,</p>
          <p class="pl-4"><span class="text-green-400">updated_by</span> = <span class="text-amber-400">1</span></p>
          <p><span class="text-purple-400">WHERE</span> <span class="text-green-400">categorie</span> = <span class="text-amber-400">'A'</span>;</p>
          <p class="text-gray-500 mt-3">// Resultat : 1 ligne affectee</p>
          <p class="text-gray-500">// Session ID : sess_a8f3c2e1d9b4</p>
          <p class="text-gray-500">// User Agent : Mozilla/5.0 (Windows NT 10.0; Win64; x64)</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
