<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { getValidationErrors } from '@/composables/useApi'

const router = useRouter()
const api = useApi()

const form = ref({
  matricule: '',
  nom: '',
  prenom: '',
  email: '',
  telephone: '',
  numero_mtn: '',
  numero_moov: '',
  service: '',
  categorie: 'C' as 'A1' | 'A2' | 'B' | 'C' | 'D',
})

const loading = ref(false)
const showSuccessModal = ref(false)
const errors = ref<Record<string, string[]>>({})
const createdAgentId = ref<number | null>(null)

async function handleSave() {
  loading.value = true
  errors.value = {}
  try {
    const { data } = await api.post('/api/agents', form.value)
    createdAgentId.value = data.data?.id || null
    showSuccessModal.value = true
  } catch (e) {
    errors.value = getValidationErrors(e)
  } finally {
    loading.value = false
  }
}

function goToList() {
  router.push('/agents')
}

function goToDetail() {
  if (createdAgentId.value) {
    router.push(`/agents/${createdAgentId.value}`)
  } else {
    router.push('/agents')
  }
}

function resetForm() {
  form.value = {
    matricule: '', nom: '', prenom: '', email: '', telephone: '',
    numero_mtn: '', numero_moov: '', service: '', categorie: 'C',
  }
  errors.value = {}
  showSuccessModal.value = false
}
</script>

<template>
  <AppLayout title="Nouveau Personnel" subtitle="Ajouter un agent au systeme">
    <template #header-actions>
      <router-link
        to="/agents"
        class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50"
      >
        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      </router-link>
    </template>

    <div class="max-w-4xl">
      <form @submit.prevent="handleSave">
        <!-- Informations personnelles -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
          <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Informations Personnelles
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Matricule <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.matricule"
                type="text"
                placeholder="MAT-XXXX"
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
              <p v-if="errors.matricule" class="text-xs text-red-500 mt-1">{{ errors.matricule[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Service <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.service"
                type="text"
                placeholder="Ex: Direction Technique"
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500"
              >
              <p v-if="errors.service" class="text-xs text-red-500 mt-1">{{ errors.service[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Nom <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.nom"
                type="text"
                placeholder="Nom de famille"
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Prenom(s) <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.prenom"
                type="text"
                placeholder="Prenom(s)"
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Categorie <span class="text-red-500">*</span>
              </label>
              <select
                v-model="form.categorie"
                required
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
              >
                <option value="A1">A1</option>
                <option value="A2">A2</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
              </select>
              <p v-if="errors.categorie" class="text-xs text-red-500 mt-1">{{ errors.categorie[0] }}</p>
            </div>
          </div>
        </div>

        <!-- Informations telephoniques -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
          <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Informations Telephoniques
          </h3>
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-5">
            <p class="text-sm font-body text-amber-700 flex items-center gap-2">
              <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Les numeros doivent etre au format international beninois (229XXXXXXXX)
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                <span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-0.5 rounded mr-1">MTN</span>
                Numero MTN MoMo
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-body text-gray-400">+229</span>
                <input
                  v-model="form.numero_mtn"
                  type="tel"
                  placeholder="XX XX XX XX"
                  maxlength="11"
                  class="w-full pl-14 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
                >
              </div>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                <span class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-0.5 rounded mr-1">MOOV</span>
                Numero MOOV Flooz
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-body text-gray-400">+229</span>
                <input
                  v-model="form.numero_moov"
                  type="tel"
                  placeholder="XX XX XX XX"
                  maxlength="11"
                  class="w-full pl-14 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
                >
              </div>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Numero MTN MoMo
              </label>
              <input
                v-model="form.numero_mtn"
                type="text"
                placeholder="229XXXXXXXX"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500"
              >
              <p v-if="errors.numero_mtn" class="text-xs text-red-500 mt-1">{{ errors.numero_mtn[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Numero MOOV Flooz</label>
              <input
                v-model="form.numero_moov"
                type="text"
                placeholder="229XXXXXXXX"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500"
              >
              <p v-if="errors.numero_moov" class="text-xs text-red-500 mt-1">{{ errors.numero_moov[0] }}</p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <router-link
            to="/agents"
            class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Annuler
          </router-link>
          <div class="flex gap-3">
            <button
              type="button"
              class="px-6 py-2.5 border border-anatt-200 text-anatt-700 rounded-xl text-sm font-display font-medium hover:bg-anatt-50 transition-colors"
            >
              Enregistrer brouillon
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60"
            >
              <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ loading ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
          </div>
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
          <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Agent enregistre !</h3>
          <p class="text-sm font-body text-gray-500 mb-6">L'agent a ete ajoute avec succes au systeme.</p>
          <div class="flex gap-3">
            <button
              class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
              @click="goToList"
            >
              Liste
            </button>
            <button
              class="flex-1 px-4 py-2.5 border border-anatt-200 text-anatt-600 rounded-xl text-sm font-display font-medium hover:bg-anatt-50"
              @click="goToDetail"
            >
              Voir la fiche
            </button>
            <button
              class="flex-1 px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600"
              @click="resetForm"
            >
              Ajouter un autre
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>
