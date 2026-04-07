<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'

const route = useRoute()
const router = useRouter()
const agentId = Number(route.params.id) || 1

const form = ref({
  matricule: 'MAT-0045',
  grade: 'Cadre',
  nom: 'MAGBE',
  prenom: 'Victorin',
  salaire: 850000,
  numero_mtn: '99707480',
  numero_moov: '01234567',
  operateur: 'MTN',
  statut: 'Actif'
})

const showSuccessModal = ref(false)

const modifications = ref([
  { text: 'Numero MTN modifie: 229 99 XX XX XX -> 229 99 70 74 80', date: '28/11/2024' },
  { text: 'Salaire mis a jour: 780 000 -> 850 000 FCFA', date: '15/10/2024' }
])

const categorieDisplay = computed(() => {
  const s = form.value.salaire || 0
  if (s < 400000) return { text: '< 400 000 FCFA', class: 'bg-blue-50 border-blue-200 text-blue-700 font-medium' }
  if (s < 800000) return { text: '400 000 - 800 000 FCFA', class: 'bg-amber-50 border-amber-200 text-amber-700 font-medium' }
  return { text: '>= 800 000 FCFA', class: 'bg-green-50 border-green-200 text-green-700 font-medium' }
})

function handleUpdate() {
  showSuccessModal.value = true
}
</script>

<template>
  <AppLayout title="Modifier l'Agent" :subtitle="`${form.matricule} - ${form.nom} ${form.prenom}`">
    <template #header-actions>
      <router-link
        :to="`/agents/${agentId}`"
        class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50"
      >
        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      </router-link>
      <span class="text-xs font-body text-gray-400">Derniere modification: 28/11/2024</span>
    </template>

    <div class="max-w-4xl">
      <!-- Alerte modification -->
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>
          <p class="text-sm font-display font-medium text-amber-800">Mode edition</p>
          <p class="text-xs font-body text-amber-700">Les modifications seront tracees dans l'historique d'audit. Les champs grises ne sont pas modifiables.</p>
        </div>
      </div>

      <form @submit.prevent="handleUpdate">
        <!-- Informations personnelles -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
          <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Informations Personnelles
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Matricule</label>
              <input
                :value="form.matricule"
                type="text"
                disabled
                class="w-full px-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm font-body text-gray-500 cursor-not-allowed"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Grade <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.grade"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
              >
                <option>Directeur General</option>
                <option>Directeur</option>
                <option>Chef Service</option>
                <option>Chef Division</option>
                <option>Cadre</option>
                <option>Agent</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Nom <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.nom"
                type="text"
                class="w-full px-4 py-2.5 border border-amber-300 bg-amber-50 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Prenom(s) <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.prenom"
                type="text"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Salaire de base (FCFA) <span class="text-red-500">*</span>
              </label>
              <input
                v-model.number="form.salaire"
                type="number"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Categorie</label>
              <div class="px-4 py-2.5 border rounded-xl text-sm" :class="categorieDisplay.class">
                {{ categorieDisplay.text }}
              </div>
            </div>
          </div>
        </div>

        <!-- Informations telephoniques -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
          <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Informations Telephoniques
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                <span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-0.5 rounded mr-1">MTN</span>
                Numero MTN
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-body text-gray-400">+229</span>
                <input
                  v-model="form.numero_mtn"
                  type="tel"
                  class="w-full pl-14 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500"
                >
              </div>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                <span class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-0.5 rounded mr-1">MOOV</span>
                Numero MOOV
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-body text-gray-400">+229</span>
                <input
                  v-model="form.numero_moov"
                  type="tel"
                  class="w-full pl-14 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500"
                >
              </div>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Operateur principal</label>
              <div class="flex gap-4">
                <label
                  class="flex items-center gap-2 px-4 py-3 rounded-xl cursor-pointer flex-1 transition-colors"
                  :class="form.operateur === 'MTN' ? 'border-2 border-yellow-400 bg-yellow-50' : 'border border-gray-200 hover:bg-yellow-50'"
                >
                  <input v-model="form.operateur" type="radio" value="MTN" class="text-yellow-600">
                  <span class="text-sm font-display font-medium">MTN MoMo</span>
                </label>
                <label
                  class="flex items-center gap-2 px-4 py-3 rounded-xl cursor-pointer flex-1 transition-colors"
                  :class="form.operateur === 'MOOV' ? 'border-2 border-blue-400 bg-blue-50' : 'border border-gray-200 hover:bg-blue-50'"
                >
                  <input v-model="form.operateur" type="radio" value="MOOV" class="text-blue-600">
                  <span class="text-sm font-display font-medium">MOOV Flooz</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Statut</label>
              <select
                v-model="form.statut"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
              >
                <option>Actif</option>
                <option>Inactif</option>
                <option>Suspendu</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Historique des modifications -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
          <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
            Dernieres Modifications
          </h3>
          <div class="space-y-3 text-sm">
            <div
              v-for="(mod, idx) in modifications"
              :key="idx"
              class="flex gap-3 items-center p-3 bg-gray-50 rounded-xl"
            >
              <svg class="w-4 h-4 text-amber-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
              <span class="font-body text-gray-600">{{ mod.text }}</span>
              <span class="ml-auto text-xs font-body text-gray-400 flex-shrink-0">{{ mod.date }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <router-link
            :to="`/agents/${agentId}`"
            class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
          >
            Annuler
          </router-link>
          <button
            type="submit"
            class="px-8 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2 shadow-lg shadow-anatt-500/30"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Sauvegarder les modifications
          </button>
        </div>
      </form>
    </div>

    <!-- Modal succes -->
    <teleport to="body">
      <div
        v-if="showSuccessModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      >
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl text-center">
          <div class="w-16 h-16 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-8 h-8 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Modifications enregistrees</h3>
          <p class="text-sm font-body text-gray-500 mb-6">La fiche de {{ form.nom }} {{ form.prenom }} a ete mise a jour.</p>
          <router-link
            :to="`/agents/${agentId}`"
            class="inline-block px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600"
          >
            Voir la fiche
          </router-link>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>
