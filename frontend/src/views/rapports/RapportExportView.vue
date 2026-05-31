<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import { rapportsService } from '@/services/rapports'
import { useToast } from '@/composables/useToast'
import type { RapportDimension, ExportFormat } from '@/types'

const toast = useToast()

const dimension = ref<RapportDimension>('agent')
const format = ref<ExportFormat>('xlsx')
const dateDebut = ref('')
const dateFin = ref('')
const generating = ref(false)
const dernierFichier = ref<string | null>(null)

const DIMENSIONS: { value: RapportDimension; label: string }[] = [
  { value: 'agent', label: 'Par agent' },
  { value: 'operateur', label: 'Par opérateur' },
  { value: 'zone', label: 'Par zone' },
  { value: 'imputation', label: 'Par imputation budgétaire' },
]

async function generer() {
  generating.value = true
  dernierFichier.value = null
  try {
    const res = await rapportsService.export({
      dimension: dimension.value, format: format.value,
      date_debut: dateDebut.value || undefined, date_fin: dateFin.value || undefined,
    })
    dernierFichier.value = res.fichier
    await rapportsService.telechargerExport(res.fichier)
    toast.success(`Export ${format.value.toUpperCase()} généré (${res.nb_lignes} ligne(s)).`)
  } catch {
    toast.error("Échec de la génération de l'export.")
  } finally {
    generating.value = false
  }
}

async function retelecharger() {
  if (dernierFichier.value) await rapportsService.telechargerExport(dernierFichier.value)
}
</script>

<template>
  <AppLayout title="Export personnalisé" subtitle="États de paiement — Excel / PDF">
    <div class="max-w-2xl mx-auto space-y-5">
      <AppCard>
        <h2 class="font-display font-bold text-gray-800 mb-4">Paramètres de l'export</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Dimension</label>
            <select v-model="dimension" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
              <option v-for="d in DIMENSIONS" :key="d.value" :value="d.value">{{ d.label }}</option>
            </select>
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Format</label>
            <select v-model="format" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
              <option value="xlsx">Excel (.xlsx)</option>
              <option value="pdf">PDF</option>
            </select>
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Du</label>
            <input v-model="dateDebut" type="date" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Au</label>
            <input v-model="dateFin" type="date" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
          </div>
        </div>
        <div class="flex justify-end mt-5">
          <AppButton :loading="generating" @click="generer">Générer & télécharger</AppButton>
        </div>
      </AppCard>

      <AppAlert v-if="dernierFichier" type="success">
        <div class="flex items-center justify-between w-full">
          <span class="text-sm">Export généré : <strong>{{ dernierFichier.split('/').pop() }}</strong></span>
          <button class="underline font-display font-semibold text-sm" @click="retelecharger">Re-télécharger</button>
        </div>
      </AppAlert>
    </div>
  </AppLayout>
</template>
