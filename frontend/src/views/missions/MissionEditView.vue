<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { useApi } from '@/composables/useApi'
import type { OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()
const loading = ref(false)
const loadingData = ref(true)
const mission = ref<OrdreMission | null>(null)
const historiqueModifications = ref<any[]>([])

const form = ref({
  objet: '',
  destination: '',
  date_depart: '',
  date_retour: '',
  taux_id: 0,
})

onMounted(async () => {
  try {
    const [missionRes, historiqueRes] = await Promise.all([
      api.get(`/api/ordres/${route.params.id}`),
      api.get(`/api/ordres/${route.params.id}/historique`),
    ])

    mission.value = missionRes.data.data

    if (mission.value) {
      form.value = {
        objet: mission.value.objet,
        destination: mission.value.destination,
        date_depart: mission.value.date_depart,
        date_retour: mission.value.date_retour,
        taux_id: mission.value.taux_id,
      }
    }

    historiqueModifications.value = (historiqueRes.data.data || []).map((log: any) => ({
      icone: log.action?.includes('CREATE') ? 'plus' : 'edit',
      description: log.action || 'Action',
      auteur: log.user?.name || '--',
      date: log.created_at ? new Date(log.created_at).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '--',
    }))
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const dureeJours = computed(() => {
  const d1 = new Date(form.value.date_depart)
  const d2 = new Date(form.value.date_retour)
  if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return 0
  const diff = Math.ceil((d2.getTime() - d1.getTime()) / (1000 * 60 * 60 * 24)) + 1
  return diff > 0 ? diff : 0
})

async function handleSave() {
  loading.value = true
  try {
    await api.put(`/api/ordres/${route.params.id}`, form.value)
    router.push(`/ordres/${route.params.id}`)
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}

async function handleSaveAndSubmit() {
  loading.value = true
  try {
    await api.put(`/api/ordres/${route.params.id}`, form.value)
    await api.post(`/api/ordres/${route.params.id}/soumettre`)
    router.push(`/ordres/${route.params.id}`)
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppLayout :title="mission ? 'Modifier ' + mission.numero : 'Chargement...'" :subtitle="mission ? 'Statut: ' + mission.statut : ''">
    <template #header-actions>
      <AppBadge v-if="mission" :variant="mission.statut === 'BROUILLON' ? 'info' : mission.statut === 'REJETE' ? 'danger' : 'warning'">{{ mission.statut }}</AppBadge>
    </template>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-else-if="mission" class="animate-fade-up max-w-4xl space-y-6">
      <!-- Audit trail warning -->
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        <div>
          <p class="text-sm font-display font-semibold text-amber-800">Modifications tracees</p>
          <p class="text-xs text-amber-600 font-body mt-1">Toute modification sera enregistree dans le journal d'audit avec l'identite du modificateur et l'horodatage.</p>
        </div>
      </div>

      <!-- References -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          References
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Reference</label>
            <input :value="mission.numero" type="text" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body bg-gray-50 text-gray-500">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Objet de la mission <span class="text-red-500">*</span></label>
            <input v-model="form.objet" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
        </div>
      </AppCard>

      <!-- Itinerary -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          Itineraire
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Agent</label>
            <input :value="mission.agent ? mission.agent.prenom + ' ' + mission.agent.nom : '--'" type="text" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body bg-gray-50 text-gray-500">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Destination</label>
            <input v-model="form.destination" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
        </div>
      </AppCard>

      <!-- Period -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          Periode
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de debut</label>
            <input v-model="form.date_depart" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de fin</label>
            <input v-model="form.date_retour" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Duree</label>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-anatt-50 border border-anatt-200 rounded-xl">
              <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span class="text-sm font-display font-bold text-anatt-700">{{ dureeJours }} jours</span>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Agent -->
      <AppCard v-if="mission.agent">
        <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Agent
        </h2>
        <div class="border border-gray-200 rounded-xl p-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-anatt-100 rounded-lg flex items-center justify-center text-xs font-display font-bold text-anatt-600">
              {{ (mission.agent.prenom?.[0] || '') + (mission.agent.nom?.[0] || '') }}
            </div>
            <div>
              <p class="font-display font-semibold text-gray-800">{{ mission.agent.prenom }} {{ mission.agent.nom }}</p>
              <p class="text-[11px] text-gray-400 font-body">{{ mission.agent.matricule }} - {{ mission.agent.categorie }} - {{ mission.agent.service }}</p>
            </div>
          </div>
          <div class="mt-3 bg-anatt-50 rounded-lg p-3 flex items-center justify-between">
            <span class="text-sm font-body text-gray-600">{{ dureeJours }} jour(s) x {{ mission.taux?.montant_journalier ? Number(mission.taux.montant_journalier).toLocaleString() : '--' }} FCFA/jour</span>
            <span class="text-sm font-display font-bold text-anatt-700">{{ Number(mission.montant_per_diem).toLocaleString() }} FCFA</span>
          </div>
        </div>
      </AppCard>

      <!-- Modification history -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Historique des modifications
        </h2>
        <div class="space-y-3">
          <div v-for="(entry, i) in historiqueModifications" :key="i"
               class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                 :class="entry.icone === 'edit' ? 'bg-amber-100' : 'bg-gray-200'">
              <svg v-if="entry.icone === 'edit'" class="w-4 h-4 text-amber-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
              <svg v-else class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </div>
            <div>
              <p class="text-sm font-body font-medium text-gray-800">{{ entry.description }}</p>
              <p class="text-[11px] text-gray-400 font-body">Par {{ entry.auteur }} - {{ entry.date }}</p>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Actions -->
      <div class="flex items-center justify-between">
        <AppButton variant="outline" @click="router.back()">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          Annuler
        </AppButton>
        <div class="flex gap-3">
          <AppButton variant="outline" :loading="loading" @click="handleSave">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
            Sauvegarder
          </AppButton>
          <AppButton :loading="loading" @click="handleSaveAndSubmit">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Sauvegarder et resoumettre
          </AppButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
