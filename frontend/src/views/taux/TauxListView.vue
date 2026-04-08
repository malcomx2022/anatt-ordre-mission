<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import type { TauxPerDiem } from '@/types'

const router = useRouter()
const api = useApi()
const loadingData = ref(true)

const activeTab = ref<'locale' | 'regionale' | 'internationale'>('locale')

const tauxList = ref<TauxPerDiem[]>([])

onMounted(async () => {
  try {
    const { data } = await api.get('/api/taux')
    tauxList.value = data.data || []
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const categories = ['A1', 'A2', 'B', 'C', 'D']

const tauxLocaux = computed(() =>
  categories.map(cat => {
    const taux = tauxList.value.find(t => t.categorie === cat && t.destination_type === 'LOCALE')
    return { categorie: cat, montant: taux ? Number(taux.montant_journalier) : 0, annee: taux?.annee || '--' }
  })
)

const tauxRegionaux = computed(() =>
  categories.map(cat => {
    const taux = tauxList.value.find(t => t.categorie === cat && t.destination_type === 'REGIONALE')
    return { categorie: cat, montant: taux ? Number(taux.montant_journalier) : 0, annee: taux?.annee || '--' }
  })
)

const tauxInternationaux = computed(() =>
  categories.map(cat => {
    const taux = tauxList.value.find(t => t.categorie === cat && t.destination_type === 'INTERNATIONALE')
    return { categorie: cat, montant: taux ? Number(taux.montant_journalier) : 0, annee: taux?.annee || '--' }
  })
)

const currentTaux = computed(() => {
  switch (activeTab.value) {
    case 'locale': return tauxLocaux.value
    case 'regionale': return tauxRegionaux.value
    case 'internationale': return tauxInternationaux.value
  }
})

function formatNumber(n: number): string {
  return n.toLocaleString('fr-FR')
}
</script>

<template>
  <AppLayout title="Baremes des Missions" subtitle="Grilles tarifaires des indemnites de mission par categorie et destination">
    <template #header-actions>
      <router-link
        to="/taux/configurer"
        class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
        Modifier les Baremes
      </router-link>
      <button class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m0 0a48.103 48.103 0 0112.5 0m-12.5 0V4.875c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v1.659" /></svg>
        Imprimer
      </button>
    </template>

    <!-- Info Banner -->
    <div class="bg-gradient-to-r from-anatt-500 to-anatt-800 rounded-2xl p-5 mb-6 text-white flex items-center justify-between animate-fade-up">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
        </div>
        <div>
          <h3 class="font-display font-bold text-sm">Decret N 2024-456 du 15 Janvier 2024</h3>
          <p class="text-xs text-anatt-100 mt-0.5 font-body">Fixant les taux des indemnites de mission des agents de l'Etat</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-xs text-anatt-200 font-body">Derniere mise a jour</p>
        <p class="text-sm font-display font-bold">15 Janvier 2024</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <!-- Tabs -->
    <div v-if="!loadingData" class="flex gap-3 mb-6 animate-fade-up delay-100">
      <button
        @click="activeTab = 'locale'"
        :class="activeTab === 'locale' ? 'bg-anatt-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
        class="px-5 py-2.5 rounded-xl text-sm font-display font-semibold flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0l2.77-.693a9 9 0 016.208.682l.108.054a9 9 0 006.086.71l3.114-.732a48.524 48.524 0 01-.005-10.499l-3.11.732a9 9 0 01-6.085-.711l-.108-.054a9 9 0 00-6.208-.682L3 4.5M3 15V4.5" /></svg>
        Missions Nationales
      </button>
      <button
        @click="activeTab = 'regionale'"
        :class="activeTab === 'regionale' ? 'bg-anatt-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
        class="px-5 py-2.5 rounded-xl text-sm font-display font-semibold flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
        Missions Internationales
      </button>
      <button
        @click="activeTab = 'internationale'"
        :class="activeTab === 'internationale' ? 'bg-anatt-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
        class="px-5 py-2.5 rounded-xl text-sm font-display font-semibold flex items-center gap-2"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0H21M3.375 14.25h17.25M3.375 14.25V3.375c0-.621.504-1.125 1.125-1.125h9.75c.621 0 1.125.504 1.125 1.125v6.375" /></svg>
        Frais de Transport
      </button>
    </div>

    <!-- Taux Table -->
    <div v-if="!loadingData" class="animate-fade-up delay-200">
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-sm font-display font-semibold text-gray-700 flex items-center gap-2">
            <svg class="w-4 h-4 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
            Taux Per Diem - {{ activeTab === 'locale' ? 'Missions Locales' : activeTab === 'regionale' ? 'Missions Regionales' : 'Missions Internationales' }}
          </h3>
          <span class="text-xs text-gray-400 font-body">Montants en FCFA / jour</span>
        </div>
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase">Categorie</th>
              <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase bg-gold-50 font-bold">Montant Journalier (FCFA)</th>
              <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase">Annee</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="taux in currentTaux" :key="taux.categorie" class="hover:bg-gray-50/50">
              <td class="px-5 py-3.5">
                <span class="text-sm font-display font-bold text-gray-800">Categorie {{ taux.categorie }}</span>
              </td>
              <td class="px-5 py-3.5 text-center bg-gold-50/50">
                <span class="text-lg font-display font-bold text-anatt-700">{{ formatNumber(taux.montant) }}</span>
              </td>
              <td class="px-5 py-3.5 text-center">
                <span class="text-sm font-body text-gray-500">{{ taux.annee }}</span>
              </td>
            </tr>
            <tr v-if="currentTaux.length === 0 || currentTaux.every(t => t.montant === 0)">
              <td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400 font-body">Aucun taux defini pour ce type de destination</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
