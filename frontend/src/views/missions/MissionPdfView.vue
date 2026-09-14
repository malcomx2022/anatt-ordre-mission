<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { ordresService } from '@/services/ordres'
import { useToast } from '@/composables/useToast'
import { partAvance } from '@/composables/usePartAvance'
import { categorieLabel } from '@/types'
import type { OrdreMission } from '@/types'

const route = useRoute()
const toast = useToast()
const id = Number(route.params.id)
const avancePct = partAvance()
const reliquatPct = 100 - avancePct
const ordre = ref<OrdreMission | null>(null)
const loading = ref(true)
const downloading = ref(false)
const fmt = (v: number) => v.toLocaleString('fr-FR')

onMounted(async () => {
  try {
    ordre.value = await ordresService.get(id)
  } finally {
    loading.value = false
  }
})

function imprimer() {
  window.print()
}

async function telechargerPdf() {
  downloading.value = true
  try {
    await ordresService.telechargerPdf(id)
  } catch {
    toast.error('Téléchargement du PDF impossible.')
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <AppLayout title="Aperçu de l'ordre de mission" :subtitle="ordre ? ordre.reference_semo : ''">
    <template #header-actions>
      <router-link :to="`/ordres/${id}`" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Fiche</router-link>
      <AppButton variant="outline" @click="imprimer">Imprimer</AppButton>
      <AppButton :loading="downloading" @click="telechargerPdf">Télécharger PDF</AppButton>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <AppCard v-else-if="ordre" class="max-w-3xl mx-auto print:shadow-none">
      <div class="flex items-center justify-between border-b-2 border-anatt-500 pb-4 mb-4">
        <div>
          <h2 class="font-display text-xl font-extrabold text-anatt-700">ORDRE DE MISSION</h2>
          <p class="text-sm font-body text-gray-500">Réf. SEMO {{ ordre.reference_semo }}</p>
        </div>
        <span class="font-display font-bold">{{ ordre.statut }}</span>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm font-body mb-5">
        <p><span class="text-gray-400">Bénéficiaire :</span> <strong>{{ ordre.agent?.nom }} {{ ordre.agent?.prenoms }}</strong> ({{ ordre.role }})</p>
        <p><span class="text-gray-400">Matricule :</span> {{ ordre.agent?.matricule }}</p>
        <p><span class="text-gray-400">Objet :</span> {{ ordre.mission?.objet }}</p>
        <p><span class="text-gray-400">Destination :</span> {{ ordre.mission?.destination }} ({{ ordre.zone_code }})</p>
        <p><span class="text-gray-400">Période :</span> {{ ordre.mission?.date_depart }} → {{ ordre.mission?.date_retour }}</p>
        <p><span class="text-gray-400">Durée :</span> {{ ordre.nb_jours }} jour(s)</p>
        <p><span class="text-gray-400">Catégorie :</span> {{ categorieLabel(ordre.categorie) }}</p>
        <p><span class="text-gray-400">Prise en charge :</span> {{ ordre.type_prise_en_charge }}</p>
      </div>

      <table class="w-full text-sm font-body border-t border-gray-100 pt-3">
        <tbody>
          <template v-if="ordre.lignes_per_diem && ordre.lignes_per_diem.length">
            <tr v-for="(l, i) in ordre.lignes_per_diem" :key="i" class="border-b border-gray-50">
              <td class="py-2 text-gray-400">{{ l.type }} — {{ l.nb_jours }} j × {{ fmt(l.taux) }}</td>
              <td class="text-right">{{ fmt(l.montant) }} FCFA</td>
            </tr>
          </template>
          <tr v-if="ordre.montant_transport && ordre.montant_transport > 0" class="border-b border-gray-50">
            <td class="py-2 text-gray-400">Transport</td><td class="text-right">{{ fmt(ordre.montant_transport) }} FCFA</td>
          </tr>
          <tr class="border-b border-gray-50"><td class="py-2 text-gray-400">Montant total</td><td class="text-right font-display font-bold text-anatt-700">{{ fmt(ordre.montant_total) }} FCFA</td></tr>
          <tr class="border-b border-gray-50"><td class="py-2 text-gray-400">Avance ({{ avancePct }} %)</td><td class="text-right">{{ fmt(ordre.avance) }} FCFA</td></tr>
          <tr><td class="py-2 text-gray-400">Reliquat ({{ reliquatPct }} %)</td><td class="text-right">{{ fmt(ordre.reliquat) }} FCFA</td></tr>
        </tbody>
      </table>

      <div class="flag-bar mt-6"></div>
    </AppCard>
  </AppLayout>
</template>
