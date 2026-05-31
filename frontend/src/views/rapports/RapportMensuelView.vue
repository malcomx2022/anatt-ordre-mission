<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { rapportsService } from '@/services/rapports'
import type { RapportMensuel } from '@/types'

const mois = ref(new Date().toISOString().slice(0, 7))
const loading = ref(true)
const rapport = ref<RapportMensuel | null>(null)

const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

const moisOptions = computed(() => {
  const opts: { value: string; label: string }[] = []
  const now = new Date()
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const val = d.toISOString().slice(0, 7)
    const label = d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
    opts.push({ value: val, label: label.charAt(0).toUpperCase() + label.slice(1) })
  }
  return opts
})

async function load() {
  loading.value = true
  try {
    rapport.value = await rapportsService.mensuel(mois.value)
  } catch {
    rapport.value = null
  } finally {
    loading.value = false
  }
}

watch(mois, load)
onMounted(load)
</script>

<template>
  <AppLayout title="État mensuel" subtitle="Synthèse des versements confirmés du mois">
    <template #header-actions>
      <select v-model="mois" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
        <option v-for="o in moisOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else-if="rapport" class="space-y-6">
      <!-- Synthèse -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <AppCard><p class="text-2xl font-display font-bold text-gray-800">{{ rapport.synthese.nb_versements }}</p><p class="text-xs font-body text-gray-500">Versements confirmés</p></AppCard>
        <AppCard><p class="text-2xl font-display font-bold text-anatt-600">{{ fmt(rapport.synthese.montant_total) }}</p><p class="text-xs font-body text-gray-500">Montant total (FCFA)</p></AppCard>
        <AppCard><p class="text-sm font-display font-bold text-gray-700">{{ rapport.periode.debut }} → {{ rapport.periode.fin }}</p><p class="text-xs font-body text-gray-500">Période</p></AppCard>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <!-- Par opérateur -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Par opérateur</h3>
          <table class="w-full text-sm font-body">
            <tbody>
              <tr v-for="(o, i) in rapport.par_operateur" :key="i" class="border-b border-gray-50">
                <td class="py-2">{{ o.operateur }}</td>
                <td class="text-right text-gray-500">{{ o.nb_versements }}</td>
                <td class="text-right font-semibold">{{ fmt(o.montant_total) }}</td>
              </tr>
              <tr v-if="!rapport.par_operateur.length"><td class="py-3 text-gray-400">Aucune donnée</td></tr>
            </tbody>
          </table>
        </AppCard>

        <!-- Par tranche -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Par tranche</h3>
          <table class="w-full text-sm font-body">
            <tbody>
              <tr v-for="(t, i) in rapport.par_tranche" :key="i" class="border-b border-gray-50">
                <td class="py-2">{{ t.tranche }}</td>
                <td class="text-right text-gray-500">{{ t.nb }}</td>
                <td class="text-right font-semibold">{{ fmt(t.montant) }}</td>
              </tr>
              <tr v-if="!rapport.par_tranche.length"><td class="py-3 text-gray-400">Aucune donnée</td></tr>
            </tbody>
          </table>
        </AppCard>

        <!-- Pipeline par statut (global) -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Pipeline (tous statuts)</h3>
          <table class="w-full text-sm font-body">
            <tbody>
              <tr v-for="(s, i) in rapport.par_statut" :key="i" class="border-b border-gray-50">
                <td class="py-2">{{ s.statut }}</td>
                <td class="text-right text-gray-500">{{ s.nb }}</td>
                <td class="text-right font-semibold">{{ fmt(s.montant) }}</td>
              </tr>
            </tbody>
          </table>
        </AppCard>
      </div>
    </div>
  </AppLayout>
</template>
