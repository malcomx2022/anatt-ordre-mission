<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'

const router = useRouter()

const decretNumero = ref('2024-456')
const decretDate = ref('2024-01-15')
const applicationDate = ref('2024-02-01')

interface RateRow {
  categorie: string
  description: string
  hebergement: number
  restauration: number
  divers: number
  origHebergement: number
  origRestauration: number
  origDivers: number
}

const rates = ref<RateRow[]>([
  { categorie: 'Cat. A', description: 'DG, DGA, SG', hebergement: 35000, restauration: 15000, divers: 10000, origHebergement: 35000, origRestauration: 15000, origDivers: 10000 },
  { categorie: 'Cat. B', description: 'Directeurs, CS, A1', hebergement: 25000, restauration: 12000, divers: 8000, origHebergement: 25000, origRestauration: 12000, origDivers: 8000 },
  { categorie: 'Cat. C', description: 'A2, B1, Maitrise', hebergement: 20000, restauration: 10000, divers: 5000, origHebergement: 20000, origRestauration: 10000, origDivers: 5000 },
  { categorie: 'Cat. D', description: 'B2, C, D, Appui', hebergement: 15000, restauration: 7500, divers: 2500, origHebergement: 15000, origRestauration: 7500, origDivers: 2500 },
])

const changeCount = computed(() => {
  let count = 0
  for (const r of rates.value) {
    if (r.hebergement !== r.origHebergement) count++
    if (r.restauration !== r.origRestauration) count++
    if (r.divers !== r.origDivers) count++
  }
  return count
})

function isModified(value: number, orig: number): boolean {
  return value !== orig
}

function total(row: RateRow): string {
  return (row.hebergement + row.restauration + row.divers).toLocaleString('fr-FR')
}

const history = [
  { title: 'Mise a jour Decret 2024-456', date: '15/01/2024', detail: 'Cat. A hebergement : 30 000 -> 35 000 . Cat. B hebergement : 22 000 -> 25 000', author: 'Admin Systeme', validator: 'DG HOUNSOU' },
  { title: 'Ajout zones de destination', date: '01/06/2023', detail: 'Creation des zones 1, 2, 3 avec supplements differencies', author: 'Admin Systeme', validator: 'DG HOUNSOU' },
]

function saveChanges() {
  if (changeCount.value === 0) {
    alert('Aucune modification detectee.')
    return
  }
  alert(`Modifications enregistrees avec succes !\n\n${changeCount.value} valeur(s) modifiee(s).\nEn attente de validation par le Directeur General.`)
}
</script>

<template>
  <AppLayout title="Modification des Baremes" subtitle="Edition des grilles tarifaires - Les modifications necessitent une validation">
    <template #header-actions>
      <span
        v-if="changeCount > 0"
        class="text-xs text-amber-600 font-display font-semibold bg-amber-50 px-3 py-1.5 rounded-lg flex items-center gap-1"
      >
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
        {{ changeCount }} modification(s)
      </span>
      <router-link to="/taux" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">Annuler</router-link>
      <button @click="saveChanges" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        Enregistrer
      </button>
    </template>

    <!-- Warning -->
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-3 animate-fade-up">
      <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
      </div>
      <div>
        <h4 class="text-sm font-display font-bold text-amber-800">Attention : Zone sensible</h4>
        <p class="text-xs text-amber-700 mt-1 font-body">Toute modification des baremes impactera le calcul automatique des indemnites pour les ordres de mission futurs. Les modifications sont enregistrees avec tracabilite complete et necessitent la validation du Directeur General.</p>
      </div>
    </div>

    <!-- Decree Reference -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Reference Reglementaire</h3>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-body text-gray-500 mb-1">N du Decret</label>
          <input v-model="decretNumero" type="text" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body text-gray-500 mb-1">Date du Decret</label>
          <input v-model="decretDate" type="date" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body text-gray-500 mb-1">Date d'Application</label>
          <input v-model="applicationDate" type="date" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
      </div>
    </div>

    <!-- Editable Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 animate-fade-up delay-200">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-display font-semibold text-gray-700 flex items-center gap-2">
          <svg class="w-4 h-4 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
          Per Diem - Missions Nationales (FCFA / jour)
        </h3>
      </div>
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase w-48">Categorie</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Hebergement</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Restauration</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Frais Divers</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase bg-gold-50">Total Calcule</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="row in rates" :key="row.categorie">
            <td class="px-5 py-3">
              <span class="text-sm font-display font-bold text-gray-800">{{ row.categorie }}</span>
              <br>
              <span class="text-[10px] text-gray-400 font-body">{{ row.description }}</span>
            </td>
            <td class="px-3 py-2 text-center">
              <input
                v-model.number="row.hebergement"
                type="number"
                :class="isModified(row.hebergement, row.origHebergement) ? 'bg-yellow-50 border-yellow-400' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50'"
                class="w-28 text-center text-sm font-display font-semibold border rounded-lg px-2 py-1.5 focus:border-anatt-500 focus:bg-anatt-50 focus:ring-2 focus:ring-anatt-500/20 outline-none transition-all duration-200"
              >
            </td>
            <td class="px-3 py-2 text-center">
              <input
                v-model.number="row.restauration"
                type="number"
                :class="isModified(row.restauration, row.origRestauration) ? 'bg-yellow-50 border-yellow-400' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50'"
                class="w-28 text-center text-sm font-display font-semibold border rounded-lg px-2 py-1.5 focus:border-anatt-500 focus:bg-anatt-50 focus:ring-2 focus:ring-anatt-500/20 outline-none transition-all duration-200"
              >
            </td>
            <td class="px-3 py-2 text-center">
              <input
                v-model.number="row.divers"
                type="number"
                :class="isModified(row.divers, row.origDivers) ? 'bg-yellow-50 border-yellow-400' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50'"
                class="w-28 text-center text-sm font-display font-semibold border rounded-lg px-2 py-1.5 focus:border-anatt-500 focus:bg-anatt-50 focus:ring-2 focus:ring-anatt-500/20 outline-none transition-all duration-200"
              >
            </td>
            <td class="px-5 py-3 text-center bg-gold-50/50">
              <span class="text-sm font-display font-bold text-anatt-700">{{ total(row) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Change History -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-300">
      <h3 class="text-sm font-display font-semibold text-gray-700 mb-4 flex items-center gap-2">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        Historique des Modifications
      </h3>
      <div class="space-y-3">
        <div v-for="item in history" :key="item.date" class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl">
          <div class="w-8 h-8 bg-anatt-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-anatt-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
          </div>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <p class="text-sm font-display font-semibold text-gray-800">{{ item.title }}</p>
              <span class="text-xs text-gray-400 font-body">{{ item.date }}</span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5 font-body">{{ item.detail }}</p>
            <p class="text-xs text-gray-400 mt-1 font-body">Par : {{ item.author }} - Valide par : {{ item.validator }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
