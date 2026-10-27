<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { ordresService } from '@/services/ordres'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { usePartAvance } from '@/composables/usePartAvance'
import { libellesRepartition } from '@/utils/repartition'
import { categorieLabel } from '@/types'
import type { OrdreMission } from '@/types'

const route = useRoute()
const toast = useToast()
const id = Number(route.params.id)
// Part d'avance RÉACTIVE : se met à jour dès que la valeur serveur arrive.
const { partAvance: avancePct } = usePartAvance()
const ordre = ref<OrdreMission | null>(null)
// Mêmes libellés que le PDF officiel : pourcentage réel de l'OM, puis avance engagée / trop-perçu après amendement.
const libelles = computed(() => libellesRepartition(ordre.value ?? { montant_total: 0, avance: 0, reliquat: 0 }, avancePct.value))
const loading = ref(true)
const downloading = ref(false)
/** Message serveur en cas d'échec du chargement (404, 403…) : la page ne reste pas blanche. */
const erreur = ref<string | null>(null)
const fmt = (v: number) => v.toLocaleString('fr-FR')

async function load() {
  loading.value = true
  erreur.value = null
  try {
    ordre.value = await ordresService.get(id)
  } catch (e) {
    ordre.value = null
    erreur.value = getErrorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)

function imprimer() {
  window.print()
}

async function telechargerPdf() {
  downloading.value = true
  try {
    await ordresService.telechargerPdf(id)
  } catch (e) {
    // Message serveur (le corps d'erreur blob est relu par l'intercepteur useApi).
    toast.error(getErrorMessage(e))
  } finally {
    downloading.value = false
  }
}
</script>

<template>
  <AppLayout title="Aperçu de l'ordre de mission" :subtitle="ordre ? ordre.reference_semo : ''">
    <template #header-actions>
      <router-link :to="`/ordres/${id}`" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Fiche</router-link>
      <template v-if="ordre">
        <AppButton variant="outline" @click="imprimer">Imprimer</AppButton>
        <AppButton :loading="downloading" @click="telechargerPdf">Télécharger PDF</AppButton>
      </template>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <AppCard v-else-if="erreur" class="max-w-xl mx-auto text-center">
      <div role="alert" class="space-y-3 py-4">
        <svg class="w-10 h-10 mx-auto text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <h2 class="font-display font-bold text-gray-800">Aperçu indisponible</h2>
        <p class="text-sm text-gray-500 font-body" data-test="erreur-chargement">{{ erreur }}</p>
        <div class="flex justify-center gap-3 pt-2">
          <AppButton variant="outline" @click="load">Réessayer</AppButton>
          <router-link to="/ordres" class="px-5 py-2.5 rounded-xl bg-anatt-500 text-white font-display font-bold text-sm hover:bg-anatt-600 transition-all duration-200">Retour aux ordres</router-link>
        </div>
      </div>
    </AppCard>

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
          <tr class="border-b border-gray-50"><td class="py-2 text-gray-400">{{ libelles.avance }}</td><td class="text-right">{{ fmt(ordre.avance) }} FCFA</td></tr>
          <tr><td class="py-2 text-gray-400">{{ libelles.reliquat }}</td><td class="text-right">{{ fmt(ordre.reliquat) }} FCFA</td></tr>
        </tbody>
      </table>

      <div class="flag-bar mt-6"></div>
    </AppCard>
  </AppLayout>
</template>
