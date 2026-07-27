<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppModal from '@/components/shared/AppModal.vue'
import MissionStatusBadge from '@/components/missions/MissionStatusBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { ordresService } from '@/services/ordres'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import type { OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { canValiderOM } = useAuth()
const id = Number(route.params.id)

const ordre = ref<OrdreMission | null>(null)
const loading = ref(true)
const action = ref(false)
const showRejet = ref(false)
const motif = ref('')

const fmt = (v: number) => v.toLocaleString('fr-FR')
const peutValider = computed(() => canValiderOM() && ordre.value?.statut === 'IMPORTE')

// Le brouillon reste proposé tant que le circuit de validation n'est pas achevé :
// c'est précisément l'épreuve que l'on relit AVANT de valider.
const peutTelechargerBrouillon = computed(
  () => !!ordre.value && ['BROUILLON', 'IMPORTE', 'VISA_DAF'].includes(ordre.value.statut),
)

async function telechargerBrouillon() {
  action.value = true
  try {
    await ordresService.telechargerBrouillon(id)
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function load() {
  loading.value = true
  try {
    ordre.value = await ordresService.get(id)
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function valider() {
  action.value = true
  try {
    ordre.value = await ordresService.valider(id)
    toast.success('Ordre de mission validé.')
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function rejeter() {
  if (motif.value.trim().length < 10) {
    toast.warning('Le motif doit comporter au moins 10 caractères.')
    return
  }
  action.value = true
  try {
    ordre.value = await ordresService.rejeter(id, motif.value.trim())
    toast.success('Ordre de mission rejeté.')
    showRejet.value = false
    motif.value = ''
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}
</script>

<template>
  <AppLayout title="Ordre de mission" :subtitle="ordre ? ordre.reference_semo : 'Chargement…'">
    <template #header-actions>
      <router-link to="/ordres" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Ordres</router-link>
      <router-link v-if="ordre" :to="`/ordres/${id}/historique`" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">Historique</router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else-if="ordre" class="space-y-6">
      <!-- En-tête + actions -->
      <AppCard>
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-display font-bold text-gray-800">{{ ordre.reference_semo }}</h2>
            <MissionStatusBadge :statut="ordre.statut" />
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ ordre.role }}</span>
          </div>
          <div class="flex flex-wrap gap-3">
            <AppButton
              v-if="peutTelechargerBrouillon"
              variant="outline"
              :loading="action"
              @click="telechargerBrouillon"
            >
              <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Télécharger le brouillon
              </span>
            </AppButton>
            <template v-if="peutValider">
              <AppButton variant="danger" :loading="action" @click="showRejet = true">Rejeter</AppButton>
              <AppButton :loading="action" @click="valider">Valider</AppButton>
            </template>
          </div>
        </div>
        <p v-if="ordre.statut === 'REJETE' && ordre.commentaire_rejet" class="mt-3 text-sm text-red-600 font-body">Motif du rejet : {{ ordre.commentaire_rejet }}</p>
      </AppCard>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Mission -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Mission</h3>
          <dl class="grid grid-cols-2 gap-2 text-sm font-body">
            <dt class="text-gray-400">Objet</dt><dd>{{ ordre.mission?.objet ?? '—' }}</dd>
            <dt class="text-gray-400">Destination</dt><dd>{{ ordre.mission?.destination ?? '—' }}</dd>
            <dt class="text-gray-400">Zone</dt><dd>{{ ordre.zone_code }}</dd>
            <dt class="text-gray-400">Période</dt><dd>{{ ordre.mission?.date_depart }} → {{ ordre.mission?.date_retour }}</dd>
            <dt class="text-gray-400">Imputation</dt><dd>{{ ordre.mission?.imputation_budgetaire ?? '—' }}</dd>
            <dt class="text-gray-400">Moyen transport</dt><dd>{{ ordre.moyen_transport ?? '—' }}</dd>
            <dt class="text-gray-400">Conducteur à bord</dt><dd>{{ ordre.conducteur_a_bord ?? '—' }}</dd>
          </dl>
        </AppCard>

        <!-- Bénéficiaire + indemnité -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Bénéficiaire & indemnité</h3>
          <dl class="grid grid-cols-2 gap-2 text-sm font-body">
            <dt class="text-gray-400">Agent</dt><dd>{{ ordre.agent ? `${ordre.agent.nom} ${ordre.agent.prenoms}` : '—' }}</dd>
            <dt class="text-gray-400">Matricule</dt><dd>{{ ordre.agent?.matricule }}</dd>
            <dt class="text-gray-400">Catégorie</dt><dd class="font-semibold text-anatt-600">{{ ordre.categorie }}</dd>
            <dt class="text-gray-400">Type</dt><dd>{{ ordre.type_prise_en_charge }}</dd>
            <dt class="text-gray-400">Nb jours</dt><dd>{{ ordre.nb_jours }}</dd>
            <template v-if="ordre.lignes_per_diem && ordre.lignes_per_diem.length > 1">
              <dt class="text-gray-400">Répartition</dt>
              <dd class="text-[12px] text-gray-600">
                <span v-for="(l, i) in ordre.lignes_per_diem" :key="i" class="block">{{ l.type }} : {{ l.nb_jours }} j × {{ fmt(l.taux) }} = {{ fmt(l.montant) }}</span>
              </dd>
            </template>
            <template v-if="ordre.montant_transport && ordre.montant_transport > 0">
              <dt class="text-gray-400">Transport</dt><dd>{{ fmt(ordre.montant_transport) }} FCFA</dd>
            </template>
            <dt class="text-gray-400">Total</dt><dd class="font-display font-bold text-anatt-700">{{ fmt(ordre.montant_total) }} FCFA</dd>
            <dt class="text-gray-400">Avance (70 %)</dt><dd>{{ fmt(ordre.avance) }}</dd>
            <dt class="text-gray-400">Reliquat (30 %)</dt><dd>{{ fmt(ordre.reliquat) }}</dd>
          </dl>
        </AppCard>
      </div>

      <!-- Versements -->
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-3">Versements</h3>
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
              <th class="py-2">Tranche</th><th>Opérateur</th><th>Téléphone</th><th class="text-right">Montant</th><th>Statut</th><th>Date paiement</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ordre.versements?.length"><td colspan="6" class="py-6 text-center text-gray-400">Aucun versement.</td></tr>
            <tr v-for="v in ordre.versements" :key="v.id" class="border-b border-gray-50">
              <td class="py-2 font-display">{{ v.type }}</td>
              <td>{{ v.operateur }}</td>
              <td class="font-mono text-xs">{{ v.telephone }}</td>
              <td class="text-right font-semibold">{{ fmt(v.montant) }}</td>
              <td><PaiementStatusBadge :statut="v.statut" /></td>
              <td class="text-gray-500 text-xs">{{ v.date_paiement ? new Date(v.date_paiement).toLocaleString('fr-FR') : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </AppCard>
    </div>

    <!-- Modal rejet -->
    <AppModal :show="showRejet" title="Rejeter l'ordre de mission" @close="showRejet = false">
      <label class="text-[11px] text-gray-500 font-body block mb-1">Motif (≥ 10 caractères)</label>
      <textarea v-model="motif" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500"></textarea>
      <template #footer>
        <AppButton variant="ghost" @click="showRejet = false">Annuler</AppButton>
        <AppButton variant="danger" :loading="action" @click="rejeter">Confirmer le rejet</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
