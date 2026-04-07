<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppModal from '@/components/shared/AppModal.vue'

const route = useRoute()
const router = useRouter()
const commentaire = ref('')
const loading = ref(false)
const showRejectModal = ref(false)
const showSuccessModal = ref(false)
const successTitle = ref('')
const successMessage = ref('')
const rejectMotif = ref('')
const rejectDetail = ref('')

const currentIndex = ref(2)
const totalEnAttente = ref(5)

const mission = ref({
  numero: 'OM-2024-000791',
  semo: 'SEMO-4519',
  objet: 'Controle des permis zone sud',
  itineraire: 'Cotonou -> Abomey',
  duree: '3 jours (10-12 Dec 2024)',
  transport: 'Vehicule de service',
  soumis_par: 'Gislain A.',
  date_soumission: '09 Dec 2024',
  valide_par_chef: 'Marie DOSSOU',
  valide_chef_date: '10 Dec 2024',
})

const beneficiaires = ref([
  {
    initiales: 'DM',
    nom: 'DOSSA Marie',
    telephone: '229 97 12 34 56',
    categorie: '400-800K',
    color: 'purple',
    jours_1_repas: 1,
    taux_1_repas: 12000,
    montant_1_repas: 12000,
    jours_2_repas: 1,
    taux_2_repas: 20000,
    montant_2_repas: 20000,
    nuitees: 2,
    taux_nuitee: 35000,
    montant_nuitees: 70000,
    total: 102000,
    operateur: 'MTN',
    verifie: true,
  },
])

const totalMission = computed(() => beneficiaires.value.reduce((s, b) => s + b.total, 0))
const avance = computed(() => Math.round(totalMission.value * 0.3333))
const reliquat = computed(() => totalMission.value - avance.value)

const conformite = ref([
  { label: 'Baremes conformes (grille Oct 2022)', ok: true },
  { label: 'Numero de telephone valide', ok: true },
  { label: 'Pas de doublon detecte', ok: true },
  { label: 'Personnel actif confirme', ok: true },
  { label: 'Validation N+1 effectuee', ok: true },
])

const rejectMotifs = [
  'Dates incorrectes',
  'Montants non conformes',
  'Beneficiaire errone',
  'Budget insuffisant',
  'Mission non prioritaire',
  'Autre',
]

function handleApprove() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    successTitle.value = 'OM approuve par le DG !'
    successMessage.value = "L'ordre de mission a recu l'approbation finale. Le processus de paiement peut etre lance."
    showSuccessModal.value = true
  }, 800)
}

function handleRejectConfirm() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    showRejectModal.value = false
    successTitle.value = 'OM rejete par le DG'
    successMessage.value = "L'ordre de mission a ete rejete. Le createur et le chef de service seront notifies."
    showSuccessModal.value = true
  }, 800)
}

function handlePrevious() {
  if (currentIndex.value > 1) currentIndex.value--
}

function handleNext() {
  if (currentIndex.value < totalEnAttente.value) currentIndex.value++
}
</script>

<template>
  <AppLayout title="Approbation Direction Generale" :subtitle="mission.semo + ' - Soumis par ' + mission.soumis_par + ' le ' + mission.date_soumission">
    <template #header-actions>
      <AppBadge variant="success">VALIDE N1</AppBadge>
    </template>

    <div class="animate-fade-up">
      <!-- N+1 validation banner -->
      <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3 mb-6">
        <svg class="w-5 h-5 text-green-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        <p class="text-sm text-green-700 font-body">Valide par le chef de service <strong class="font-display">{{ mission.valide_par_chef }}</strong> le {{ mission.valide_chef_date }}</p>
      </div>

      <!-- Navigation between pending OMs -->
      <div class="flex items-center justify-between mb-6">
        <button
          class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-gray-500 hover:bg-gray-50 flex items-center gap-2 transition-colors"
          @click="handlePrevious"
        >
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          OM precedent
        </button>
        <span class="text-sm text-gray-400 font-body">{{ currentIndex }} / {{ totalEnAttente }} en attente DG</span>
        <button
          class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-gray-500 hover:bg-gray-50 flex items-center gap-2 transition-colors"
          @click="handleNext"
        >
          OM suivant
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </button>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
          <!-- Mission info -->
          <AppCard>
            <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              Informations de la mission
            </h2>
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 text-sm">
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Objet</p>
                <p class="font-body font-semibold">{{ mission.objet }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Itineraire</p>
                <p class="font-body font-semibold">{{ mission.itineraire }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Duree</p>
                <p class="font-body font-semibold text-anatt-600">{{ mission.duree }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Transport</p>
                <p class="font-body font-semibold">{{ mission.transport }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Soumis par</p>
                <p class="font-body font-semibold">{{ mission.soumis_par }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Date soumission</p>
                <p class="font-body font-semibold">{{ mission.date_soumission }}</p>
              </div>
            </div>
          </AppCard>

          <!-- Beneficiary verification -->
          <AppCard>
            <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>
              Verification des beneficiaires
            </h2>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase font-display">
                    <th class="px-4 py-3">Agent</th>
                    <th class="px-4 py-3">Categorie</th>
                    <th class="px-4 py-3">1 Repas</th>
                    <th class="px-4 py-3">2 Repas</th>
                    <th class="px-4 py-3">Nuitees</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Operateur</th>
                    <th class="px-4 py-3">Verif.</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <tr v-for="b in beneficiaires" :key="b.nom" class="hover:bg-anatt-50/30 transition-colors">
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-display font-bold"
                             :class="`bg-${b.color}-100 text-${b.color}-600`">
                          {{ b.initiales }}
                        </div>
                        <div>
                          <p class="font-body font-medium">{{ b.nom }}</p>
                          <p class="text-[10px] text-gray-400 font-body">{{ b.telephone }}</p>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-lg font-body">{{ b.categorie }}</span>
                    </td>
                    <td class="px-4 py-3 font-body">
                      {{ b.jours_1_repas }}j x {{ b.taux_1_repas.toLocaleString() }}<br>
                      <strong>{{ b.montant_1_repas.toLocaleString() }}</strong>
                    </td>
                    <td class="px-4 py-3 font-body">
                      {{ b.jours_2_repas }}j x {{ b.taux_2_repas.toLocaleString() }}<br>
                      <strong>{{ b.montant_2_repas.toLocaleString() }}</strong>
                    </td>
                    <td class="px-4 py-3 font-body">
                      {{ b.nuitees }}n x {{ b.taux_nuitee.toLocaleString() }}<br>
                      <strong>{{ b.montant_nuitees.toLocaleString() }}</strong>
                    </td>
                    <td class="px-4 py-3 font-display font-bold text-anatt-700">{{ b.total.toLocaleString() }} F</td>
                    <td class="px-4 py-3">
                      <span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-1 rounded-lg">{{ b.operateur }}</span>
                    </td>
                    <td class="px-4 py-3">
                      <svg v-if="b.verifie" class="w-5 h-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                      <svg v-else class="w-5 h-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-anatt-50 font-display font-bold">
                    <td colspan="5" class="px-4 py-3 text-right text-anatt-700">TOTAL</td>
                    <td class="px-4 py-3 text-anatt-700">{{ totalMission.toLocaleString() }} F</td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </AppCard>

          <!-- Amount verification -->
          <AppCard>
            <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="16" x="4" y="4" rx="2"/><path d="M8 10h8"/><path d="M8 14h4"/></svg>
              Verification des montants
            </h2>
            <!-- Per diem total -->
            <div class="bg-anatt-50 rounded-xl p-4 text-center mb-4">
              <p class="text-sm text-gray-600 font-body">Montant total per diem</p>
              <p class="text-2xl font-display font-bold text-anatt-600">{{ totalMission.toLocaleString() }} FCFA</p>
            </div>
            <div class="grid grid-cols-3 gap-4">
              <div class="bg-gray-50 rounded-xl p-4 text-center">
                <p class="text-xs text-gray-500 font-body mb-1">Total mission</p>
                <p class="text-xl font-display font-bold text-gray-800">{{ totalMission.toLocaleString() }} F</p>
              </div>
              <div class="bg-blue-50 rounded-xl p-4 text-center">
                <p class="text-xs text-blue-600 font-body mb-1">Avance (33.33%)</p>
                <p class="text-xl font-display font-bold text-blue-700">{{ avance.toLocaleString() }} F</p>
              </div>
              <div class="bg-green-50 rounded-xl p-4 text-center">
                <p class="text-xs text-green-600 font-body mb-1">Reliquat (66.67%)</p>
                <p class="text-xl font-display font-bold text-green-700">{{ reliquat.toLocaleString() }} F</p>
              </div>
            </div>
            <!-- Conformity checklist -->
            <div class="mt-4 space-y-2">
              <div v-for="item in conformite" :key="item.label" class="flex items-center gap-2 text-sm font-body">
                <svg v-if="item.ok" class="w-4 h-4 text-green-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <svg v-else class="w-4 h-4 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                <span>{{ item.label }}</span>
              </div>
            </div>
          </AppCard>
        </div>

        <!-- Right column - Decision panel -->
        <div class="space-y-6">
          <!-- Decision -->
          <div class="bg-white rounded-2xl border-2 border-anatt-200 p-6">
            <h3 class="text-base font-display font-bold text-gray-800 mb-4 text-center flex items-center justify-center gap-2">
              <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m14 14-2 2-2-2"/><path d="M12 12V6"/><circle cx="12" cy="12" r="10"/></svg>
              Decision du DG
            </h3>
            <div class="space-y-3">
              <button
                class="w-full px-5 py-3.5 bg-green-600 text-white rounded-xl text-sm font-display font-bold hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                :disabled="loading"
                @click="handleApprove"
              >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Approuver (DG)
              </button>
              <button
                class="w-full px-5 py-3.5 bg-red-500 text-white rounded-xl text-sm font-display font-bold hover:bg-red-600 transition-colors flex items-center justify-center gap-2"
                @click="showRejectModal = true"
              >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                Rejeter
              </button>
              <button
                class="w-full px-5 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-600 hover:bg-gray-50 flex items-center justify-center gap-2 transition-colors"
              >
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M10 9v6"/><path d="M14 9v6"/></svg>
                Mettre en attente
              </button>
            </div>
          </div>

          <!-- Comment -->
          <AppCard>
            <h3 class="text-sm font-display font-bold text-gray-800 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
              Commentaire (optionnel)
            </h3>
            <textarea
              v-model="commentaire"
              rows="3"
              placeholder="Commentaire du Directeur General..."
              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 resize-none transition-all duration-200"
            ></textarea>
          </AppCard>

          <!-- Validator info -->
          <div class="bg-gray-50 rounded-2xl p-5">
            <h3 class="text-sm font-display font-bold text-gray-600 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
              Validateur
            </h3>
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 bg-anatt-600 rounded-lg flex items-center justify-center text-sm font-display font-bold text-white">DG</div>
              <div>
                <p class="text-sm font-body font-medium text-gray-800">Directeur General</p>
                <p class="text-[11px] text-gray-400 font-body">Niveau d'approbation: Directeur</p>
              </div>
            </div>
            <p class="text-[11px] text-gray-400 font-body">Votre decision sera notifiee au createur de l'OM, au chef de service, et enregistree dans l'audit.</p>
          </div>

          <!-- Validation chain summary -->
          <AppCard>
            <h3 class="text-sm font-display font-bold text-gray-800 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Chaine de validation
            </h3>
            <div class="space-y-3 relative">
              <div class="absolute top-3 bottom-3 left-[11px] w-0.5 bg-gray-200"></div>
              <div class="relative flex gap-3">
                <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0 z-10">
                  <svg class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div>
                  <p class="text-sm font-body font-medium text-gray-800">Creation</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ mission.soumis_par }} - {{ mission.date_soumission }}</p>
                </div>
              </div>
              <div class="relative flex gap-3">
                <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0 z-10">
                  <svg class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div>
                  <p class="text-sm font-body font-medium text-gray-800">Validation N+1</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ mission.valide_par_chef }} - {{ mission.valide_chef_date }}</p>
                </div>
              </div>
              <div class="relative flex gap-3">
                <div class="w-6 h-6 bg-orange-400 rounded-full flex items-center justify-center flex-shrink-0 z-10">
                  <svg class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                  <p class="text-sm font-body font-medium text-orange-600">Approbation DG</p>
                  <p class="text-[11px] text-gray-400 font-body">En attente de votre decision</p>
                </div>
              </div>
            </div>
          </AppCard>
        </div>
      </div>
    </div>

    <!-- Reject modal -->
    <AppModal :show="showRejectModal" title="Rejeter cet OM ?" @close="showRejectModal = false">
      <div class="text-center mb-4">
        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </div>
        <p class="text-sm text-gray-500 font-body">Veuillez indiquer le motif du rejet pour <strong>{{ mission.numero }}</strong>.</p>
      </div>
      <div class="space-y-3">
        <div>
          <label class="text-sm font-display font-medium text-gray-700">Motif du rejet <span class="text-red-500">*</span></label>
          <select
            v-model="rejectMotif"
            class="w-full mt-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
          >
            <option value="">Selectionnez un motif</option>
            <option v-for="motif in rejectMotifs" :key="motif" :value="motif">{{ motif }}</option>
          </select>
        </div>
        <div>
          <label class="text-sm font-display font-medium text-gray-700">Details</label>
          <textarea
            v-model="rejectDetail"
            rows="3"
            placeholder="Precisez le motif..."
            class="w-full mt-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 resize-none"
          ></textarea>
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" @click="showRejectModal = false">Annuler</AppButton>
        <AppButton variant="danger" :loading="loading" :disabled="!rejectMotif" @click="handleRejectConfirm">Confirmer le rejet</AppButton>
      </template>
    </AppModal>

    <!-- Success modal -->
    <AppModal :show="showSuccessModal" title="" @close="showSuccessModal = false; router.push('/ordres')">
      <div class="text-center py-4">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h3 class="text-xl font-display font-bold text-gray-800">{{ successTitle }}</h3>
        <p class="text-sm text-gray-500 font-body mt-2">{{ successMessage }}</p>
        <AppButton class="mt-5" @click="showSuccessModal = false; router.push('/ordres')">
          Retour aux OMs en attente
        </AppButton>
      </div>
    </AppModal>
  </AppLayout>
</template>
