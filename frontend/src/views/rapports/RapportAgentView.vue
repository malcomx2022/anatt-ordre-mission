<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { rapportsService } from '@/services/rapports'
import { useToast } from '@/composables/useToast'
import type { RapportDimension, ExportFormat } from '@/types'

const toast = useToast()

const dimension = ref<RapportDimension>('agent')
const dateDebut = ref('')
const dateFin = ref('')
const loading = ref(true)
const exporting = ref<ExportFormat | null>(null)
const lignes = ref<Record<string, unknown>[]>([])

const DIMENSIONS: { value: RapportDimension; label: string }[] = [
  { value: 'agent', label: 'Par agent' },
  { value: 'operateur', label: 'Par opérateur' },
  { value: 'zone', label: 'Par zone' },
  { value: 'imputation', label: 'Par imputation' },
]

const libelleColonne = computed(() => ({
  agent: 'Agent', operateur: 'Opérateur', zone: 'Zone', imputation: 'Imputation budgétaire',
}[dimension.value]))

const fmt = (v: unknown) => Number(v ?? 0).toLocaleString('fr-FR')

// Plage de dates incohérente (Au < Du) : on prévient au lieu de renvoyer une
// liste vide silencieuse (le backend, lui, refuse l'export dans ce cas).
const plageInvalide = computed(() => !!dateDebut.value && !!dateFin.value && dateFin.value < dateDebut.value)

function principale(row: Record<string, unknown>): string {
  switch (dimension.value) {
    case 'agent': return `${row.matricule ?? ''} — ${row.nom ?? ''} ${row.prenoms ?? ''}`.trim()
    case 'operateur': return String(row.operateur ?? '—')
    case 'zone': return String(row.zone_code ?? '—')
    case 'imputation': return String(row.imputation_budgetaire ?? '(non renseignée)')
  }
}

async function load() {
  if (plageInvalide.value) { lignes.value = []; loading.value = false; return }
  loading.value = true
  const params = { date_debut: dateDebut.value || undefined, date_fin: dateFin.value || undefined }
  try {
    lignes.value = await ({
      agent: () => rapportsService.parAgent(params),
      operateur: () => rapportsService.parOperateur(params),
      zone: () => rapportsService.parZone(params),
      imputation: () => rapportsService.parImputation(params),
    }[dimension.value])()
  } catch {
    lignes.value = []
  } finally {
    loading.value = false
  }
}

watch([dimension, dateDebut, dateFin], load)
onMounted(load)

async function exporter(format: ExportFormat) {
  if (plageInvalide.value) { toast.error('La date « Au » doit être postérieure à « Du ».'); return }
  exporting.value = format
  try {
    const res = await rapportsService.export({
      dimension: dimension.value, format,
      date_debut: dateDebut.value || undefined, date_fin: dateFin.value || undefined,
    })
    await rapportsService.telechargerExport(res.fichier)
    toast.success(`Export ${format.toUpperCase()} généré.`)
  } catch {
    toast.error("Échec de l'export.")
  } finally {
    exporting.value = null
  }
}

const totalMontant = computed(() => lignes.value.reduce((s, r) => s + Number(r.montant_total ?? 0), 0))
</script>

<template>
  <AppLayout title="États par dimension" subtitle="Versements confirmés — par agent, opérateur, zone ou imputation">
    <!-- Filtres -->
    <AppCard class="mb-6">
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Dimension</label>
          <select v-model="dimension" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
            <option v-for="d in DIMENSIONS" :key="d.value" :value="d.value">{{ d.label }}</option>
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
        <div class="flex gap-2">
          <AppButton size="sm" variant="outline" :disabled="plageInvalide" :loading="exporting === 'xlsx'" @click="exporter('xlsx')">Excel</AppButton>
          <AppButton size="sm" variant="outline" :disabled="plageInvalide" :loading="exporting === 'pdf'" @click="exporter('pdf')">PDF</AppButton>
        </div>
      </div>
      <p v-if="plageInvalide" class="text-xs font-body text-red-500 mt-3">La date « Au » doit être postérieure à « Du ».</p>
    </AppCard>

    <AppCard>
      <p v-if="loading" class="text-center text-gray-400 font-body py-8">Chargement…</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
              <th class="py-2">{{ libelleColonne }}</th>
              <th class="text-right">Nb versements</th>
              <th class="text-right">Montant total (FCFA)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!lignes.length"><td colspan="3" class="py-8 text-center text-gray-400">Aucune donnée confirmée sur la période.</td></tr>
            <tr v-for="(row, i) in lignes" :key="i" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="py-2">{{ principale(row) }}</td>
              <td class="text-right text-gray-500">{{ row.nb_versements }}</td>
              <td class="text-right font-semibold">{{ fmt(row.montant_total) }}</td>
            </tr>
          </tbody>
          <tfoot v-if="lignes.length">
            <tr class="border-t-2 border-gray-100 font-display font-bold text-anatt-700">
              <td class="py-2">Total</td>
              <td></td>
              <td class="text-right">{{ fmt(totalMontant) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </AppCard>
  </AppLayout>
</template>
