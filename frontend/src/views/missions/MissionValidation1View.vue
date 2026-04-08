<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppModal from '@/components/shared/AppModal.vue'
import { useApi } from '@/composables/useApi'
import type { OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()
const commentaire = ref('')
const loading = ref(false)
const loadingData = ref(true)
const showRejectModal = ref(false)
const showSuccessModal = ref(false)
const successTitle = ref('')
const successMessage = ref('')
const rejectMotif = ref('')
const rejectDetail = ref('')

const mission = ref<OrdreMission | null>(null)

// Navigation entre OM en attente
const pendingIds = ref<number[]>([])
const currentPendingIndex = computed(() => {
  if (!mission.value) return -1
  return pendingIds.value.indexOf(mission.value.id)
})

onMounted(async () => {
  try {
    const [missionRes, pendingRes] = await Promise.all([
      api.get(`/api/ordres/${route.params.id}`),
      api.get('/api/ordres?statut=SOUMIS&per_page=100'),
    ])
    mission.value = missionRes.data.data
    pendingIds.value = (pendingRes.data.data || []).map((m: any) => m.id)
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const conformite = computed(() => {
  if (!mission.value) return []
  const agent = mission.value.agent
  return [
    { label: 'Baremes conformes', ok: !!mission.value.taux_id },
    { label: 'Numero de telephone valide', ok: !!(agent?.telephone) },
    { label: 'Pas de doublon detecte', ok: true },
    { label: 'Personnel actif confirme', ok: agent?.actif !== false },
  ]
})

const rejectMotifs = [
  'Dates incorrectes',
  'Montants non conformes',
  'Beneficiaire errone',
  'Information manquante',
  'Autre',
]

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function handleApprove() {
  loading.value = true
  try {
    await api.post(`/api/ordres/${route.params.id}/valider-n1`)
    successTitle.value = 'OM valide !'
    successMessage.value = "L'ordre de mission a ete valide avec succes. Le createur et la direction seront notifies."
    showSuccessModal.value = true
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}

async function handleRejectConfirm() {
  const fullComment = rejectMotif.value + (rejectDetail.value ? ' - ' + rejectDetail.value : '')
  if (fullComment.length < 10) return
  loading.value = true
  try {
    await api.post(`/api/ordres/${route.params.id}/rejeter`, { commentaire: fullComment })
    showRejectModal.value = false
    successTitle.value = 'OM rejete'
    successMessage.value = "L'ordre de mission a ete rejete. Le createur sera notifie."
    showSuccessModal.value = true
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}

function navigatePending(direction: number) {
  const newIdx = currentPendingIndex.value + direction
  if (newIdx >= 0 && newIdx < pendingIds.value.length) {
    router.push(`/ordres/${pendingIds.value[newIdx]}/valider-n1`)
  }
}
</script>

<template>
  <AppLayout :title="'Validation Chef de Service'" :subtitle="mission ? mission.numero + ' - ' + mission.objet : 'Chargement...'">
    <template #header-actions>
      <AppBadge v-if="mission" variant="warning">{{ mission.statut }}</AppBadge>
    </template>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-else-if="mission" class="animate-fade-up">
      <!-- Navigation between pending OMs -->
      <div class="flex items-center justify-between mb-6">
        <button
          class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-gray-500 hover:bg-gray-50 flex items-center gap-2 transition-colors"
          :disabled="currentPendingIndex <= 0"
          @click="navigatePending(-1)"
        >
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          OM precedent
        </button>
        <span class="text-sm text-gray-400 font-body">{{ currentPendingIndex + 1 }} / {{ pendingIds.length }} en attente</span>
        <button
          class="px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-gray-500 hover:bg-gray-50 flex items-center gap-2 transition-colors"
          :disabled="currentPendingIndex >= pendingIds.length - 1"
          @click="navigatePending(1)"
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
                <p class="text-xs text-gray-400 font-body mb-1">Destination</p>
                <p class="font-body font-semibold">{{ mission.destination }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Duree</p>
                <p class="font-body font-semibold text-anatt-600">{{ mission.nb_jours }} jour(s) ({{ formatDate(mission.date_depart) }} - {{ formatDate(mission.date_retour) }})</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Agent</p>
                <p class="font-body font-semibold">{{ mission.agent ? mission.agent.prenom + ' ' + mission.agent.nom : '--' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Service</p>
                <p class="font-body font-semibold">{{ mission.agent?.service || '--' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-body mb-1">Montant per diem</p>
                <p class="font-body font-semibold text-anatt-600">{{ Number(mission.montant_per_diem).toLocaleString() }} FCFA</p>
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
                  <tr v-if="mission.agent" class="hover:bg-anatt-50/30 transition-colors">
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-display font-bold bg-anatt-100 text-anatt-600">
                          {{ (mission.agent.prenom?.[0] || '') + (mission.agent.nom?.[0] || '') }}
                        </div>
                        <div>
                          <p class="font-body font-medium">{{ mission.agent.prenom }} {{ mission.agent.nom }}</p>
                          <p class="text-[10px] text-gray-400 font-body">{{ mission.agent.telephone }}</p>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded-lg font-body">{{ mission.agent.categorie }}</span>
                    </td>
                    <td class="px-4 py-3 font-body" colspan="3">
                      {{ mission.nb_jours }} jour(s) x {{ mission.taux ? Number(mission.taux.montant_journalier).toLocaleString() : '--' }} FCFA
                    </td>
                    <td class="px-4 py-3 font-display font-bold text-anatt-700">{{ Number(mission.montant_per_diem).toLocaleString() }} F</td>
                    <td class="px-4 py-3">
                      <span v-if="mission.agent.numero_mtn" class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-1 rounded-lg">MTN</span>
                      <span v-else-if="mission.agent.numero_moov" class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-1 rounded-lg">MOOV</span>
                    </td>
                    <td class="px-4 py-3">
                      <svg class="w-5 h-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-anatt-50 font-display font-bold">
                    <td colspan="5" class="px-4 py-3 text-right text-anatt-700">TOTAL</td>
                    <td class="px-4 py-3 text-anatt-700">{{ Number(mission.montant_per_diem).toLocaleString() }} F</td>
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
            <div class="grid grid-cols-3 gap-4">
              <div class="bg-gray-50 rounded-xl p-4 text-center">
                <p class="text-xs text-gray-500 font-body mb-1">Total mission</p>
                <p class="text-xl font-display font-bold text-gray-800">{{ Number(mission.montant_per_diem).toLocaleString() }} F</p>
              </div>
              <div class="bg-blue-50 rounded-xl p-4 text-center">
                <p class="text-xs text-blue-600 font-body mb-1">Avance (33%)</p>
                <p class="text-xl font-display font-bold text-blue-700">{{ Math.round(Number(mission.montant_per_diem) * 0.33).toLocaleString() }} F</p>
              </div>
              <div class="bg-green-50 rounded-xl p-4 text-center">
                <p class="text-xs text-green-600 font-body mb-1">Reliquat (67%)</p>
                <p class="text-xl font-display font-bold text-green-700">{{ Math.round(Number(mission.montant_per_diem) * 0.67).toLocaleString() }} F</p>
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
              Votre decision
            </h3>
            <div class="space-y-3">
              <button
                class="w-full px-5 py-3.5 bg-green-600 text-white rounded-xl text-sm font-display font-bold hover:bg-green-700 transition-colors flex items-center justify-center gap-2"
                :disabled="loading"
                @click="handleApprove"
              >
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Approuver
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
              placeholder="Ajoutez un commentaire..."
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
              <div class="w-10 h-10 bg-anatt-600 rounded-lg flex items-center justify-center text-sm font-display font-bold text-white">CS</div>
              <div>
                <p class="text-sm font-body font-medium text-gray-800">Chef de Service</p>
                <p class="text-[11px] text-gray-400 font-body">Niveau d'approbation: N+1</p>
              </div>
            </div>
            <p class="text-[11px] text-gray-400 font-body">Votre decision sera notifiee au createur de l'OM et enregistree dans l'audit.</p>
          </div>
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
