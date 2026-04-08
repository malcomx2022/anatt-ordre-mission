<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi, getValidationErrors } from '@/composables/useApi'
import type { Agent } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()
const agentId = Number(route.params.id)

const loadingData = ref(true)
const loading = ref(false)
const showSuccessModal = ref(false)
const errors = ref<Record<string, string[]>>({})
const agent = ref<Agent | null>(null)

const form = ref({
  nom: '',
  prenom: '',
  email: '',
  telephone: '',
  service: '',
  categorie: 'C' as string,
  numero_mtn: '',
  numero_moov: '',
})

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/agents/${agentId}`)
    agent.value = data.data
    if (agent.value) {
      form.value = {
        nom: agent.value.nom,
        prenom: agent.value.prenom,
        email: agent.value.email,
        telephone: agent.value.telephone,
        service: agent.value.service,
        categorie: agent.value.categorie,
        numero_mtn: agent.value.numero_mtn || '',
        numero_moov: agent.value.numero_moov || '',
      }
    }
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

async function handleUpdate() {
  loading.value = true
  errors.value = {}
  try {
    await api.put(`/api/agents/${agentId}`, form.value)
    showSuccessModal.value = true
  } catch (e) {
    errors.value = getValidationErrors(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppLayout :title="agent ? 'Modifier ' + agent.matricule : 'Chargement...'" :subtitle="agent ? `${agent.nom} ${agent.prenom}` : ''">
    <template #header-actions>
      <router-link
        :to="`/agents/${agentId}`"
        class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50"
      >
        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      </router-link>
    </template>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-else-if="agent" class="max-w-4xl">
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
                :value="agent.matricule"
                type="text"
                disabled
                class="w-full px-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl text-sm font-body text-gray-500 cursor-not-allowed"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">
                Service <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.service"
                type="text"
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
                Email <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.email"
                type="email"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
              <p v-if="errors.email" class="text-xs text-red-500 mt-1">{{ errors.email[0] }}</p>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Telephone</label>
              <input
                v-model="form.telephone"
                type="tel"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
              >
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Categorie</label>
              <select
                v-model="form.categorie"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
              >
                <option value="A1">A1</option>
                <option value="A2">A2</option>
                <option value="B">B</option>
                <option value="C">C</option>
                <option value="D">D</option>
              </select>
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
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">
          <router-link
            :to="`/agents/${agentId}`"
            class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Annuler
          </router-link>
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60"
          >
            <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            {{ loading ? 'Enregistrement...' : 'Sauvegarder' }}
          </button>
        </div>
      </form>

      <!-- Success modal -->
      <teleport to="body">
        <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
              <svg class="w-8 h-8 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Agent modifie !</h3>
            <p class="text-sm font-body text-gray-500 mb-6">Les modifications ont ete enregistrees.</p>
            <div class="flex gap-3">
              <button class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50" @click="router.push('/agents')">Liste</button>
              <button class="flex-1 px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600" @click="router.push(`/agents/${agentId}`)">Voir la fiche</button>
            </div>
          </div>
        </div>
      </teleport>
    </div>
  </AppLayout>
</template>
