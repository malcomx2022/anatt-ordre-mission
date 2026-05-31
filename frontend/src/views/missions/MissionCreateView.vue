<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import AppModal from '@/components/shared/AppModal.vue'
import WizardStepper from '@/components/missions/WizardStepper.vue'
import BeneficiaireCard from '@/components/missions/BeneficiaireCard.vue'
import { useOrdresMissionStore } from '@/stores/ordres-mission'
import { useToast } from '@/composables/useToast'
import { getValidationErrors, getErrorMessage } from '@/composables/useApi'
import type { ZoneCode } from '@/types'

const router = useRouter()
const toast = useToast()
const store = useOrdresMissionStore()

const step = ref(1)
const erreursApi = ref<string[]>([])
const showSuccess = ref(false)
const missionCreeeId = ref<number | null>(null)

const ZONES: { value: ZoneCode; label: string }[] = [
  { value: 'INTERIEUR', label: 'Intérieur (Bénin)' },
  { value: 'EXT_ZONE_I', label: 'Extérieur — Zone I' },
  { value: 'EXT_ZONE_II', label: 'Extérieur — Zone II' },
  { value: 'EXT_ZONE_III', label: 'Extérieur — Zone III' },
]

const steps = [
  { label: 'Contexte', subLabel: 'Mission commune' },
  { label: 'Bénéficiaires', subLabel: 'Participants & CAV' },
  { label: 'Récapitulatif', subLabel: 'Vérification' },
]

const fmt = (v: number) => v.toLocaleString('fr-FR')

const exonere = computed(() => store.contexte.prise_en_charge_tiers)

onMounted(async () => {
  store.reset()
  if (!store.beneficiaires.length) store.ajouterBeneficiaire()
  try {
    await store.chargerBaremes()
  } catch {
    toast.error('Impossible de charger le barème.')
  }
})

function suivant() {
  erreursApi.value = []
  if (step.value === 1 && !store.etape1Valide) {
    toast.warning('Complétez le contexte de la mission (objet, destination, zone, dates).')
    return
  }
  if (step.value === 2 && !store.etape2Valide) {
    toast.warning('Chaque bénéficiaire doit avoir un agent, une référence SEMO unique, une catégorie et un type.')
    return
  }
  step.value = Math.min(3, step.value + 1)
}

function precedent() {
  step.value = Math.max(1, step.value - 1)
}

async function enregistrer() {
  erreursApi.value = []
  try {
    const mission = await store.enregistrer()
    missionCreeeId.value = mission.id
    showSuccess.value = true
  } catch (e) {
    const errs = getValidationErrors(e)
    erreursApi.value = Object.values(errs).flat()
    if (!erreursApi.value.length) erreursApi.value = [getErrorMessage(e)]
    toast.error("Échec de l'enregistrement de la mission.")
  }
}

function terminer() {
  showSuccess.value = false
  store.reset()
  router.push({ name: 'missions' })
}
</script>

<template>
  <AppLayout title="Nouvelle mission" subtitle="Retranscription SEMO → ordres de mission">
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="font-display text-2xl font-extrabold text-gray-800">Retranscription d'une mission</h1>
          <p class="text-sm text-gray-400 font-body">Une mission SEMO → N ordres de mission individuels</p>
        </div>
        <AppButton variant="ghost" @click="router.push({ name: 'missions' })">Annuler</AppButton>
      </div>

      <WizardStepper :current-step="step" :steps="steps" />

      <AppAlert v-if="erreursApi.length" type="error">
        <div>
          <p class="font-display font-semibold mb-1">Corrigez les points suivants :</p>
          <ul class="list-disc ml-5 space-y-0.5">
            <li v-for="(msg, i) in erreursApi" :key="i">{{ msg }}</li>
          </ul>
        </div>
      </AppAlert>

      <!-- ÉTAPE 1 — Contexte commun -->
      <AppCard v-if="step === 1">
        <h2 class="font-display font-bold text-gray-800 mb-4">Contexte de la mission</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="text-[11px] text-gray-500 font-body block mb-1">Objet / thème</label>
            <input v-model="store.contexte.objet" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Destination</label>
            <input v-model="store.contexte.destination" type="text" placeholder="ex. Bohicon, Paris…" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Zone (barème)</label>
            <select v-model="store.contexte.zone_code" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
              <option v-for="z in ZONES" :key="z.value" :value="z.value">{{ z.label }}</option>
            </select>
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Date de départ</label>
            <input v-model="store.contexte.date_depart" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Date de retour</label>
            <input v-model="store.contexte.date_retour" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Imputation budgétaire</label>
            <input v-model="store.contexte.imputation_budgetaire" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div class="flex items-end">
            <label class="flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-200 cursor-pointer w-full">
              <input v-model="store.contexte.prise_en_charge_tiers" type="checkbox" class="rounded text-anatt-500 focus:ring-anatt-500">
              <span class="text-sm font-body text-gray-700">Prise en charge par un tiers (exonération)</span>
            </label>
          </div>
          <div class="sm:col-span-2">
            <label class="text-[11px] text-gray-500 font-body block mb-1">Observations</label>
            <textarea v-model="store.contexte.observations" rows="2" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"></textarea>
          </div>
        </div>
        <p class="mt-4 text-[11px] text-gray-400 font-body">Durée mission : <strong>{{ store.nbJoursMission }}</strong> jour(s) · les types proposés à l'étape 2 dépendent de la zone.</p>
      </AppCard>

      <!-- ÉTAPE 2 — Bénéficiaires -->
      <div v-else-if="step === 2" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-display font-bold text-gray-800">Bénéficiaires ({{ store.beneficiaires.length }})</h2>
          <AppButton size="sm" variant="outline" @click="store.ajouterBeneficiaire()">+ Ajouter un bénéficiaire</AppButton>
        </div>
        <BeneficiaireCard
          v-for="(b, i) in store.beneficiaires"
          :key="i"
          :beneficiaire="b"
          :index="i"
          :zone="store.contexte.zone_code"
          :nb-jours-mission="store.nbJoursMission"
          :per-diem="store.calculer(b)"
          :exonere="exonere"
          :doublon="store.referencesEnDoublon.has(b.reference_semo.trim())"
          @remove="store.retirerBeneficiaire(i)"
        />
        <AppCard v-if="!store.beneficiaires.length">
          <p class="text-center text-sm text-gray-400 font-body py-6">Aucun bénéficiaire. Ajoutez-en au moins un.</p>
        </AppCard>
      </div>

      <!-- ÉTAPE 3 — Récapitulatif -->
      <AppCard v-else>
        <h2 class="font-display font-bold text-gray-800 mb-4">Récapitulatif</h2>
        <div class="grid sm:grid-cols-2 gap-3 text-sm font-body mb-5">
          <p><span class="text-gray-400">Objet :</span> <strong>{{ store.contexte.objet }}</strong></p>
          <p><span class="text-gray-400">Destination :</span> <strong>{{ store.contexte.destination }}</strong> ({{ store.contexte.zone_code }})</p>
          <p><span class="text-gray-400">Période :</span> {{ store.contexte.date_depart }} → {{ store.contexte.date_retour }} ({{ store.nbJoursMission }} j)</p>
          <p><span class="text-gray-400">Prise en charge tiers :</span> {{ store.contexte.prise_en_charge_tiers ? 'Oui (exonéré)' : 'Non' }}</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm font-body">
            <thead>
              <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                <th class="py-2">Bénéficiaire</th>
                <th>Rôle</th>
                <th>Réf. SEMO</th>
                <th>Cat.</th>
                <th>Type</th>
                <th class="text-right">Total</th>
                <th class="text-right">Avance</th>
                <th class="text-right">Reliquat</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(b, i) in store.beneficiaires" :key="i" class="border-b border-gray-50">
                <td class="py-2">{{ b.agent?.nom_complet || (b.agent ? b.agent.nom + ' ' + b.agent.prenoms : '—') }}</td>
                <td>{{ b.role }}</td>
                <td>{{ b.reference_semo }}</td>
                <td>{{ b.categorie }}</td>
                <td>{{ b.type_prise_en_charge }}</td>
                <td class="text-right font-semibold">{{ fmt(store.calculer(b).total) }}</td>
                <td class="text-right text-gray-500">{{ fmt(store.calculer(b).avance) }}</td>
                <td class="text-right text-gray-500">{{ fmt(store.calculer(b).reliquat) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-100 font-display font-bold text-anatt-700">
                <td class="py-2" colspan="5">Total mission</td>
                <td class="text-right">{{ fmt(store.totalMission) }} FCFA</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="mt-4 text-[11px] text-gray-400 font-body">À l'enregistrement, l'API éclate cette mission en {{ store.beneficiaires.length }} ordre(s) de mission individuel(s).</p>
      </AppCard>

      <!-- Navigation -->
      <div class="flex items-center justify-between">
        <AppButton v-if="step > 1" variant="ghost" @click="precedent">← Précédent</AppButton>
        <span v-else></span>
        <AppButton v-if="step < 3" @click="suivant">Suivant →</AppButton>
        <AppButton v-else :loading="store.submitting" @click="enregistrer">Enregistrer la mission</AppButton>
      </div>
    </div>

    <AppModal :show="showSuccess" title="Mission enregistrée" @close="terminer">
      <p class="text-sm font-body text-gray-600">
        La mission a été retranscrite avec succès en {{ store.beneficiaires.length }} ordre(s) de mission.
        Les versements AVANCE et RELIQUAT ont été créés en statut <strong>EN_ATTENTE</strong>.
      </p>
      <template #footer>
        <AppButton @click="terminer">Voir les ordres de mission</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
