<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { useApi } from '@/composables/useApi'
import type { OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()

const mission = ref<OrdreMission | null>(null)
const loading = ref(true)
const generating = ref(false)
const pdfUrl = ref('')
const pdfError = ref('')

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/ordres/${route.params.id}`)
    mission.value = data.data
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
})

async function generatePdf() {
  generating.value = true
  pdfError.value = ''
  try {
    const { data } = await api.get(`/api/ordres/${route.params.id}/pdf`)
    pdfUrl.value = data.data?.url || ''
  } catch {
    pdfError.value = 'Erreur lors de la generation du PDF.'
  } finally {
    generating.value = false
  }
}

async function downloadPdf() {
  generating.value = true
  try {
    const response = await api.get(`/api/ordres/${route.params.id}/pdf/download`, {
      responseType: 'blob',
    })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = mission.value ? `${mission.value.numero}.pdf` : 'ordre-mission.pdf'
    a.click()
    window.URL.revokeObjectURL(url)
  } catch {
    pdfError.value = 'Erreur lors du telechargement.'
  } finally {
    generating.value = false
  }
}

function handlePrint() {
  window.print()
}

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
  <AppLayout :title="'Apercu PDF'" :subtitle="mission ? mission.numero : 'Chargement...'">
    <template #header-actions>
      <AppButton :loading="generating" @click="downloadPdf">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
        Telecharger PDF
      </AppButton>
      <AppButton variant="outline" @click="handlePrint">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        Imprimer
      </AppButton>
    </template>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-else-if="mission" class="max-w-4xl mx-auto space-y-6">
      <!-- Error -->
      <div v-if="pdfError" class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
        <p class="text-sm text-red-700 font-body">{{ pdfError }}</p>
      </div>

      <!-- Mission summary card -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display font-bold text-gray-800">{{ mission.numero }}</h2>
          <span class="px-3 py-1 rounded-full text-[11px] font-display font-bold" :class="mission.statut === 'PAYE' ? 'bg-green-50 text-green-700' : mission.statut === 'REJETE' ? 'bg-red-50 text-red-600' : 'bg-anatt-50 text-anatt-600'">{{ mission.statut }}</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm font-body">
          <div><p class="text-xs text-gray-400 mb-1">Agent</p><p class="font-semibold">{{ mission.agent ? mission.agent.prenom + ' ' + mission.agent.nom : '--' }}</p></div>
          <div><p class="text-xs text-gray-400 mb-1">Objet</p><p class="font-semibold">{{ mission.objet }}</p></div>
          <div><p class="text-xs text-gray-400 mb-1">Destination</p><p class="font-semibold">{{ mission.destination }}</p></div>
          <div><p class="text-xs text-gray-400 mb-1">Periode</p><p class="font-semibold">{{ formatDate(mission.date_depart) }} - {{ formatDate(mission.date_retour) }}</p></div>
          <div><p class="text-xs text-gray-400 mb-1">Duree</p><p class="font-semibold text-anatt-600">{{ mission.nb_jours }} jour(s)</p></div>
          <div><p class="text-xs text-gray-400 mb-1">Montant per diem</p><p class="font-semibold text-anatt-600">{{ Number(mission.montant_per_diem).toLocaleString() }} FCFA</p></div>
        </div>
      </div>

      <!-- PDF generation + preview -->
      <div class="bg-white rounded-2xl border border-gray-100 p-8 shadow-sm">
        <!-- If PDF URL available, show iframe -->
        <div v-if="pdfUrl" class="rounded-xl overflow-hidden border border-gray-200">
          <iframe :src="pdfUrl" class="w-full" style="height: 600px;" />
        </div>

        <!-- Otherwise show generate button -->
        <div v-else class="text-center py-12">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
          <p class="font-display font-bold text-gray-500 mb-2">Document PDF</p>
          <p class="text-sm text-gray-400 font-body mb-6">Generez le PDF de l'ordre de mission pour le visualiser ou le telecharger.</p>
          <AppButton :loading="generating" @click="generatePdf">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
            Generer le PDF
          </AppButton>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-between">
        <AppButton variant="outline" @click="router.push(`/ordres/${route.params.id}`)">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
          Retour au detail
        </AppButton>
        <AppButton variant="outline" @click="router.push(`/ordres/${route.params.id}/historique`)">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Historique
        </AppButton>
      </div>
    </div>
  </AppLayout>
</template>
