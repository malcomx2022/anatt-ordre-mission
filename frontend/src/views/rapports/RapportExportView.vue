<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const api = useApi()
const { success: toastSuccess } = useToast()

const reportType = ref('complet')
const exportFormat = ref('xlsx')
const periodeDebut = ref('')
const periodeFin = ref('')
const showModal = ref(false)
const exportDone = ref(false)
const exporting = ref(false)
const downloadUrl = ref('')

const reportTypes = [
  { value: 'complet', label: 'Rapport Complet', description: 'Missions + Paiements + Personnel', iconBg: 'bg-anatt-100', iconColor: 'text-anatt-600' },
  { value: 'missions', label: 'Ordres de Mission', description: 'Liste des ordres avec statuts', iconBg: 'bg-blue-50', iconColor: 'text-blue-600' },
  { value: 'paiements', label: 'Paiements', description: 'Historique des virements', iconBg: 'bg-purple-50', iconColor: 'text-purple-600' },
  { value: 'agents', label: 'Personnel', description: 'Liste des agents', iconBg: 'bg-green-50', iconColor: 'text-green-600' },
]

const formats = [
  { value: 'xlsx', label: 'Excel (.xlsx)', description: 'Feuilles de calcul avec formules', iconBg: 'bg-green-100', iconColor: 'text-green-600', recommended: true },
  { value: 'csv', label: 'CSV', description: 'Donnees brutes', iconBg: 'bg-blue-100', iconColor: 'text-blue-500', recommended: false },
]

async function startExport() {
  showModal.value = true
  exportDone.value = false
  exporting.value = true
  try {
    const params: Record<string, string> = { type: reportType.value, format: exportFormat.value }
    if (periodeDebut.value) params.date_debut = periodeDebut.value
    if (periodeFin.value) params.date_fin = periodeFin.value

    const { data } = await api.get('/api/rapports/export', { params })
    downloadUrl.value = data.data?.url || ''
    exportDone.value = true
    toastSuccess('Export genere avec succes.')
  } catch {
    showModal.value = false
  } finally {
    exporting.value = false
  }
}

async function downloadFile() {
  if (!downloadUrl.value) return
  try {
    const response = await api.get(downloadUrl.value, { responseType: 'blob' })
    const blob = new Blob([response.data])
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `rapport_${reportType.value}_${new Date().toISOString().slice(0, 10)}.${exportFormat.value}`
    a.click()
    window.URL.revokeObjectURL(url)
  } catch {
    // Geree par useApi
  }
}
</script>

<template>
  <AppLayout title="Export des Rapports" subtitle="Generer et telecharger les rapports dans differents formats">

    <div class="max-w-4xl mx-auto">
      <!-- Step 1: Report Type -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 bg-anatt-500 text-white rounded-lg flex items-center justify-center text-sm font-display font-bold">1</div>
          <h2 class="text-base font-display font-bold text-gray-800">Type de rapport</h2>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <label
            v-for="rt in reportTypes" :key="rt.value"
            :class="reportType === rt.value ? 'border-anatt-500 bg-anatt-50' : 'border-gray-200'"
            class="border-2 rounded-2xl p-5 text-center cursor-pointer card-hover"
            @click="reportType = rt.value"
          >
            <div :class="[rt.iconBg, 'w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3']">
              <svg :class="[rt.iconColor, 'w-6 h-6']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            </div>
            <p class="font-display font-semibold text-sm text-gray-800">{{ rt.label }}</p>
            <p class="text-[11px] text-gray-500 font-body mt-1">{{ rt.description }}</p>
          </label>
        </div>
      </div>

      <!-- Step 2: Period & Filters -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 bg-anatt-500 text-white rounded-lg flex items-center justify-center text-sm font-display font-bold">2</div>
          <h2 class="text-base font-display font-bold text-gray-800">Periode et filtres</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Periode de debut</label>
            <input v-model="periodeDebut" type="month" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
          </div>
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Periode de fin</label>
            <input v-model="periodeFin" type="month" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Direction</label>
            <select v-model="direction" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
              <option value="">Toutes les directions</option>
              <option>Direction Generale</option>
              <option>Direction Technique</option>
              <option>Direction Administrative</option>
              <option>Direction Financiere</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Statut paiement</label>
            <select v-model="statutPaiement" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
              <option value="">Tous les statuts</option>
              <option>Paye</option>
              <option>En attente</option>
              <option>Echoue</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Step 3: Format Selection -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-200">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 bg-anatt-500 text-white rounded-lg flex items-center justify-center text-sm font-display font-bold">3</div>
          <h2 class="text-base font-display font-bold text-gray-800">Format d'export</h2>
        </div>
        <div class="grid grid-cols-3 gap-4">
          <label
            v-for="fmt in formats" :key="fmt.value"
            :class="exportFormat === fmt.value ? 'border-anatt-500 bg-anatt-50' : 'border-gray-200'"
            class="border-2 rounded-2xl p-5 text-center cursor-pointer card-hover"
            @click="exportFormat = fmt.value"
          >
            <div :class="[fmt.iconBg, 'w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-3']">
              <svg :class="[fmt.iconColor, 'w-7 h-7']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            </div>
            <p class="font-display font-bold text-sm text-gray-800">{{ fmt.label }}</p>
            <p class="text-[11px] text-gray-500 font-body mt-1">{{ fmt.description }}</p>
            <span v-if="fmt.recommended" class="inline-block mt-2 px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-display font-semibold rounded-full">Recommande</span>
          </label>
        </div>
      </div>

      <!-- Options -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-300">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 bg-gray-200 text-gray-600 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
          </div>
          <h2 class="text-base font-display font-bold text-gray-800">Options supplementaires</h2>
        </div>
        <div class="space-y-3">
          <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 cursor-pointer">
            <input v-model="includeGraphiques" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <div><p class="text-sm font-body font-medium text-gray-800">Inclure les graphiques</p><p class="text-[11px] text-gray-500 font-body">Ajouter les visualisations statistiques au rapport</p></div>
          </label>
          <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 cursor-pointer">
            <input v-model="includePageGarde" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <div><p class="text-sm font-body font-medium text-gray-800">Page de garde</p><p class="text-[11px] text-gray-500 font-body">Ajouter une page de titre avec le logo ANaTT</p></div>
          </label>
          <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 cursor-pointer">
            <input v-model="includeDetailAgent" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <div><p class="text-sm font-body font-medium text-gray-800">Detail par agent</p><p class="text-[11px] text-gray-500 font-body">Ajouter une annexe avec le detail individuel</p></div>
          </label>
          <label class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 cursor-pointer">
            <input v-model="sendEmail" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <div><p class="text-sm font-body font-medium text-gray-800">Envoyer par email</p><p class="text-[11px] text-gray-500 font-body">Envoyer une copie aux destinataires selectionnes</p></div>
          </label>
        </div>
      </div>

      <!-- Action Bar -->
      <div class="bg-gradient-to-r from-anatt-500 to-anatt-700 rounded-2xl p-6">
        <div class="flex items-center justify-between">
          <div class="text-white">
            <h3 class="font-display font-bold text-base">Pret a exporter</h3>
            <p class="text-sm text-anatt-100 mt-1 font-body">Rapport mensuel - Janvier 2025 - Format PDF - ~45 pages estimees</p>
          </div>
          <div class="flex items-center gap-3">
            <button class="px-5 py-3 bg-white/20 text-white rounded-xl text-sm font-display font-semibold hover:bg-white/30 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              Apercu
            </button>
            <button @click="startExport" class="px-5 py-3 bg-gold-400 text-anatt-900 rounded-xl text-sm font-display font-bold hover:bg-gold-300 flex items-center gap-2 shadow-lg">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              Exporter
            </button>
          </div>
        </div>
      </div>

      <!-- Export History -->
      <div class="mt-8">
        <h3 class="text-sm font-display font-bold text-gray-700 mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          Exports recents
        </h3>
        <div class="space-y-2">
          <div v-for="exp in recentExports" :key="exp.name" class="bg-white rounded-xl border border-gray-100 p-4 flex items-center justify-between hover:shadow-sm transition-shadow">
            <div class="flex items-center gap-4">
              <div :class="[exp.iconBg, 'w-10 h-10 rounded-lg flex items-center justify-center']">
                <svg :class="[exp.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-gray-800">{{ exp.name }}</p>
                <p class="text-[11px] text-gray-500 font-body">Exporte le {{ exp.date }} - {{ exp.size }}</p>
              </div>
            </div>
            <button class="px-3 py-1.5 text-anatt-500 border border-anatt-200 rounded-lg text-xs font-display font-semibold hover:bg-anatt-50 flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
              Retelecharger
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Export Progress Modal -->
    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 text-center">
          <template v-if="!exportDone">
            <div class="w-16 h-16 bg-anatt-50 rounded-full flex items-center justify-center mx-auto mb-4 animate-spin">
              <svg class="w-8 h-8 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0015 0m-15 0a7.5 7.5 0 1115 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077l1.41-.513m14.095-5.13l1.41-.513M5.106 17.785l1.15-.964m11.49-9.642l1.149-.964M7.501 19.795l.75-1.3m7.5-12.99l.75-1.3m-6.063 16.658l.26-1.477m2.605-14.772l.26-1.477m-2.084 15.969l-.26-1.477m5.158-11.523l-.26-1.477" /></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Generation en cours...</h3>
            <p class="text-sm text-gray-500 font-body mb-6">Preparation du rapport mensuel au format PDF</p>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
              <div class="bg-gradient-to-r from-anatt-500 to-anatt-400 h-2 rounded-full transition-all duration-1000" :style="{ width: progressWidth }"></div>
            </div>
          </template>
          <template v-else>
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Export termine !</h3>
            <p class="text-sm text-gray-500 font-body mb-6">Rapport_Mensuel_Jan2025.pdf - 2.8 MB</p>
            <div class="flex gap-3 justify-center">
              <button @click="showModal = false" class="px-5 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-semibold text-gray-700 hover:bg-gray-50">Fermer</button>
              <button class="px-5 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Telecharger
              </button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
