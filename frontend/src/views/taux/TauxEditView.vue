<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import type { TauxPerDiem } from '@/types'

const router = useRouter()
const api = useApi()
const { success: toastSuccess } = useToast()
const loadingData = ref(true)
const saving = ref(false)

interface EditableTaux {
  id: number
  categorie: string
  destination_type: string
  montant: number
  origMontant: number
  annee: number
}

const taux = ref<EditableTaux[]>([])

onMounted(async () => {
  try {
    const { data } = await api.get('/api/taux')
    taux.value = (data.data || []).map((t: TauxPerDiem) => ({
      id: t.id,
      categorie: t.categorie,
      destination_type: t.destination_type,
      montant: Number(t.montant_journalier),
      origMontant: Number(t.montant_journalier),
      annee: t.annee,
    }))
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const changeCount = computed(() => taux.value.filter(t => t.montant !== t.origMontant).length)

function isModified(t: EditableTaux): boolean {
  return t.montant !== t.origMontant
}

async function saveChanges() {
  if (changeCount.value === 0) return
  saving.value = true
  try {
    const modified = taux.value.filter(t => t.montant !== t.origMontant)
    for (const t of modified) {
      await api.put(`/api/taux/${t.id}`, { montant_journalier: t.montant })
      t.origMontant = t.montant
    }
    toastSuccess(`${modified.length} taux mis a jour avec succes.`)
  } catch {
    // Geree par useApi
  } finally {
    saving.value = false
  }
}

function formatNumber(n: number): string {
  return n.toLocaleString('fr-FR')
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
      <button :disabled="saving || changeCount === 0" @click="saveChanges" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-50">
        <svg v-if="saving" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
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

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <!-- Editable Table -->
    <div v-else class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 animate-fade-up delay-200">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-display font-semibold text-gray-700">Taux Per Diem (FCFA / jour) - Cliquez sur un montant pour le modifier</h3>
      </div>
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Categorie</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Type destination</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase bg-gold-50 font-bold">Montant Journalier</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Annee</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="t in taux" :key="t.id" class="hover:bg-gray-50/50">
            <td class="px-5 py-3">
              <span class="text-sm font-display font-bold text-gray-800">{{ t.categorie }}</span>
            </td>
            <td class="px-5 py-3 text-center">
              <span class="text-xs font-display px-2 py-1 rounded-lg" :class="t.destination_type === 'LOCALE' ? 'bg-blue-50 text-blue-700' : t.destination_type === 'REGIONALE' ? 'bg-green-50 text-green-700' : 'bg-purple-50 text-purple-700'">{{ t.destination_type }}</span>
            </td>
            <td class="px-3 py-2 text-center bg-gold-50/50">
              <input
                v-model.number="t.montant"
                type="number"
                :class="isModified(t) ? 'bg-yellow-50 border-yellow-400' : 'bg-transparent border-transparent hover:border-gray-300 hover:bg-gray-50'"
                class="w-32 text-center text-sm font-display font-bold border rounded-lg px-2 py-1.5 focus:border-anatt-500 focus:bg-anatt-50 focus:ring-2 focus:ring-anatt-500/20 outline-none transition-all duration-200"
              >
            </td>
            <td class="px-5 py-3 text-center text-sm font-body text-gray-500">{{ t.annee }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
