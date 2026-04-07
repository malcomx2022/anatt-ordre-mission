<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppModal from '@/components/shared/AppModal.vue'
import { usePaiements } from '@/composables/usePaiements'
import type { Paiement, OperateurPaiement } from '@/types'

const router = useRouter()
const { reconcilier, loading } = usePaiements()

const activeTab = ref<'matched' | 'discrepancies' | 'missing'>('matched')
const fileUploaded = ref(false)
const isDragOver = ref(false)
const selectedOperator = ref<'MTN' | 'MOOV'>('MTN')

// Import releve data
const uploadedFile = ref({
  name: 'releve_mtn_avril2026.csv',
  size: '456 KB',
  transactions: 89,
  reussies: 82,
  echouees: 7,
})

// Mapping preview
const mappings = ref([
  { colonneFichier: 'transaction_id', champSysteme: 'Ref. Transaction', exemple: 'TXN-2026-00891234' },
  { colonneFichier: 'msisdn', champSysteme: 'N. Telephone', exemple: '+22997123456' },
  { colonneFichier: 'amount', champSysteme: 'Montant', exemple: '125 000' },
  { colonneFichier: 'status', champSysteme: 'Statut', exemple: 'SUCCESSFUL' },
  { colonneFichier: 'date', champSysteme: 'Date transaction', exemple: '2026-04-10 14:32' },
])

// Reconciliation summary
const reconciliation = ref({
  correspondances: 13,
  ecarts: 2,
  nonTrouves: 3,
  supplementaires: 0,
})

// Tab data
interface Correspondance {
  beneficiaire: string
  telephone: string
  montantEnvoye: number
  montantRetour: number
  statutOperateur: string
}

interface Ecart {
  beneficiaire: string
  telephone: string
  montantLot: number
  montantRetour: number
  ecart: string
  resolution: string
}

interface NonTrouve {
  beneficiaire: string
  telephone: string
  montant: number
  raisonProbable: string
}

const correspondances = ref<Correspondance[]>([
  { beneficiaire: 'ADJOVI Kossi Pierre', telephone: '95 12 34 56', montantEnvoye: 152000, montantRetour: 152000, statutOperateur: 'SUCCESS' },
  { beneficiaire: 'BOKO Grace Annick', telephone: '96 45 67 89', montantEnvoye: 152000, montantRetour: 152000, statutOperateur: 'SUCCESS' },
  { beneficiaire: 'GANDONOU Estelle', telephone: '95 23 45 67', montantEnvoye: 152000, montantRetour: 152000, statutOperateur: 'SUCCESS' },
])

const ecarts = ref<Ecart[]>([
  { beneficiaire: 'HOUNSA Michel', telephone: '96 11 22 33', montantLot: 152000, montantRetour: 150000, ecart: '-2 000 F', resolution: '' },
  { beneficiaire: 'TOSSOU Raoul', telephone: '95 44 55 66', montantLot: 152000, montantRetour: 152000, ecart: 'STATUT: FAILED', resolution: '' },
])

const nonTrouves = ref<NonTrouve[]>([
  { beneficiaire: 'HOUNKPATIN Joel', telephone: '96 34 56 78', montant: 152000, raisonProbable: 'Non traite par l\'operateur' },
  { beneficiaire: 'MENSAH Adeline', telephone: '95 77 88 99', montant: 152000, raisonProbable: 'Timeout de traitement' },
  { beneficiaire: 'SOSSOU Arnaud', telephone: '96 00 11 22', montant: 152000, raisonProbable: 'Numero non reconnu' },
])

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}

function handleFileDrop(e: DragEvent) {
  isDragOver.value = false
  fileUploaded.value = true
}

function handleFileSelect() {
  fileUploaded.value = true
}

function removeFile() {
  fileUploaded.value = false
}

function applyReconciliation() {
  router.push('/paiements/statuts')
}
</script>

<template>
  <AppLayout title="Reconciliation Paiements" subtitle="Rapprochement avec le releve operateur">
    <template #header-actions>
      <AppButton @click="applyReconciliation">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/><polyline points="20 6 9 17 4 12" transform="translate(2,0)"/></svg>
        Appliquer la Reconciliation
      </AppButton>
    </template>

    <!-- Back button + context -->
    <div class="flex items-center gap-3 mb-6 animate-fade-up">
      <button @click="router.push('/paiements/statuts')" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <div>
        <p class="text-xs font-body text-gray-500">LOT-2026-0046 -- MOOV Flooz -- Rapprochement avec le releve operateur</p>
      </div>
    </div>

    <!-- Operator selection + Upload + Matching summary -->
    <div class="grid grid-cols-2 gap-6 mb-6">
      <!-- Upload Zone -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Fichier Retour Operateur
        </h3>

        <!-- Operator selector -->
        <div class="grid grid-cols-2 gap-3 mb-4">
          <label class="rounded-xl p-4 flex items-center gap-3 cursor-pointer border-2 transition-all duration-200" :class="selectedOperator === 'MTN' ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200 hover:border-yellow-300'">
            <input type="radio" v-model="selectedOperator" value="MTN" class="hidden">
            <div class="w-10 h-10 bg-yellow-400 rounded-xl flex items-center justify-center text-white font-display font-black text-xs">MTN</div>
            <div><p class="text-sm font-display font-bold text-gray-800">MTN MoMo</p><p class="text-[11px] font-body text-gray-500">.csv ou .xlsx</p></div>
          </label>
          <label class="rounded-xl p-4 flex items-center gap-3 cursor-pointer border-2 transition-all duration-200" :class="selectedOperator === 'MOOV' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
            <input type="radio" v-model="selectedOperator" value="MOOV" class="hidden">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white font-display font-black text-xs">MOOV</div>
            <div><p class="text-sm font-display font-bold text-gray-800">MOOV Money</p><p class="text-[11px] font-body text-gray-500">.csv ou .xlsx</p></div>
          </label>
        </div>

        <!-- Drop zone -->
        <div
          v-if="!fileUploaded"
          class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-all duration-300"
          :class="isDragOver ? 'border-anatt-500 bg-anatt-50' : 'border-gray-300 hover:border-anatt-500 hover:bg-anatt-50/30'"
          @dragover.prevent="isDragOver = true"
          @dragleave="isDragOver = false"
          @drop.prevent="handleFileDrop"
          @click="handleFileSelect"
        >
          <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-purple-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </div>
          <p class="text-sm font-display font-semibold text-gray-700 mb-1">Deposer le fichier retour ici</p>
          <p class="text-xs font-body text-gray-400 mb-3">Format CSV ou Excel (.xlsx)</p>
          <span class="px-4 py-2 bg-purple-50 text-purple-700 rounded-lg text-xs font-display font-semibold">Parcourir les fichiers</span>
        </div>

        <!-- Uploaded file -->
        <div v-else class="p-4 bg-anatt-50 border border-anatt-200 rounded-xl flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-anatt-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div>
              <p class="text-sm font-display font-semibold text-gray-800">{{ uploadedFile.name }}</p>
              <p class="text-[11px] font-body text-gray-500">{{ uploadedFile.size }} -- {{ uploadedFile.transactions }} transactions detectees</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <AppBadge variant="success">Valide</AppBadge>
            <button @click="removeFile" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 transition-colors">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
          </div>
        </div>

        <!-- Mapping preview -->
        <div v-if="fileUploaded" class="mt-4">
          <h4 class="text-xs font-display font-semibold text-gray-600 mb-2">Apercu du mapping</h4>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-display font-semibold text-gray-500">Colonne fichier</th>
                  <th class="px-3 py-2 text-center text-xs font-display font-semibold text-gray-500"></th>
                  <th class="px-3 py-2 text-left text-xs font-display font-semibold text-gray-500">Champ systeme</th>
                  <th class="px-3 py-2 text-left text-xs font-display font-semibold text-gray-500">Exemple</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="m in mappings" :key="m.colonneFichier">
                  <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ m.colonneFichier }}</td>
                  <td class="px-3 py-2 text-center text-gray-300">
                    <svg class="w-3 h-3 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </td>
                  <td class="px-3 py-2 text-anatt-500 font-body font-medium text-xs">{{ m.champSysteme }}</td>
                  <td class="px-3 py-2 font-body text-gray-500 text-xs">{{ m.exemple }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="mt-4 bg-blue-50 rounded-xl p-3">
          <p class="text-xs font-body text-blue-700 flex items-start gap-2">
            <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <span>Le fichier retour doit contenir les colonnes : numero telephone, montant, statut, ID transaction. Il sera automatiquement rapproche avec les donnees du lot.</span>
          </p>
        </div>
      </div>

      <!-- Matching Summary -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/></svg>
          Resultat du Rapprochement
        </h3>
        <div class="space-y-3">
          <div class="flex items-center justify-between p-3 bg-anatt-50 rounded-xl">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-anatt-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-anatt-800">Correspondances exactes</p>
                <p class="text-xs font-body text-anatt-600">Numero + montant identiques</p>
              </div>
            </div>
            <span class="text-2xl font-display font-bold text-anatt-500">{{ reconciliation.correspondances }}</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-amber-50 rounded-xl">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-amber-800">Ecarts detectes</p>
                <p class="text-xs font-body text-amber-600">Montant ou statut different</p>
              </div>
            </div>
            <span class="text-2xl font-display font-bold text-amber-700">{{ reconciliation.ecarts }}</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-red-700">Non trouves</p>
                <p class="text-xs font-body text-red-500">Absents du fichier retour</p>
              </div>
            </div>
            <span class="text-2xl font-display font-bold text-red-600">{{ reconciliation.nonTrouves }}</span>
          </div>
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
              </div>
              <div>
                <p class="text-sm font-display font-semibold text-gray-700">Entrees supplementaires</p>
                <p class="text-xs font-body text-gray-500">Dans le retour mais pas dans le lot</p>
              </div>
            </div>
            <span class="text-2xl font-display font-bold text-gray-600">{{ reconciliation.supplementaires }}</span>
          </div>
        </div>

        <!-- Import summary bar -->
        <div v-if="fileUploaded" class="mt-4 bg-gradient-to-r from-anatt-500 to-anatt-600 rounded-xl p-4">
          <div class="grid grid-cols-3 gap-3 mb-3">
            <div class="bg-white/10 rounded-lg p-2 text-center text-white">
              <p class="text-lg font-display font-bold">{{ uploadedFile.transactions }}</p>
              <p class="text-[10px] font-body text-anatt-100">Transactions</p>
            </div>
            <div class="bg-white/10 rounded-lg p-2 text-center text-white">
              <p class="text-lg font-display font-bold">{{ uploadedFile.reussies }}</p>
              <p class="text-[10px] font-body text-anatt-100">Reussies</p>
            </div>
            <div class="bg-white/10 rounded-lg p-2 text-center text-white">
              <p class="text-lg font-display font-bold">{{ uploadedFile.echouees }}</p>
              <p class="text-[10px] font-body text-anatt-100">Echouees</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs for review -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <div class="border-b border-gray-100 px-6">
        <div class="flex gap-6">
          <button
            @click="activeTab = 'matched'"
            class="py-3.5 text-sm font-display border-b-2 transition-colors"
            :class="activeTab === 'matched' ? 'font-semibold border-anatt-500 text-anatt-500' : 'font-medium text-gray-400 border-transparent hover:text-gray-600'"
          >
            <svg class="w-3.5 h-3.5 inline mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Correspondances <span class="bg-anatt-50 text-anatt-500 text-xs px-2 py-0.5 rounded-full ml-1">{{ reconciliation.correspondances }}</span>
          </button>
          <button
            @click="activeTab = 'discrepancies'"
            class="py-3.5 text-sm font-display border-b-2 transition-colors"
            :class="activeTab === 'discrepancies' ? 'font-semibold border-anatt-500 text-anatt-500' : 'font-medium text-gray-400 border-transparent hover:text-gray-600'"
          >
            <svg class="w-3.5 h-3.5 inline mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Ecarts <span class="bg-amber-50 text-amber-700 text-xs px-2 py-0.5 rounded-full ml-1">{{ reconciliation.ecarts }}</span>
          </button>
          <button
            @click="activeTab = 'missing'"
            class="py-3.5 text-sm font-display border-b-2 transition-colors"
            :class="activeTab === 'missing' ? 'font-semibold border-anatt-500 text-anatt-500' : 'font-medium text-gray-400 border-transparent hover:text-gray-600'"
          >
            <svg class="w-3.5 h-3.5 inline mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
            Non trouves <span class="bg-red-50 text-red-600 text-xs px-2 py-0.5 rounded-full ml-1">{{ reconciliation.nonTrouves }}</span>
          </button>
        </div>
      </div>

      <!-- Matched Tab -->
      <div v-if="activeTab === 'matched'">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Beneficiaire</th>
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Telephone</th>
              <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant Envoye</th>
              <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant Retour</th>
              <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Statut Operateur</th>
              <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="c in correspondances" :key="c.beneficiaire" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ c.beneficiaire }}</td>
              <td class="px-5 py-3 text-sm font-body text-gray-600">{{ c.telephone }}</td>
              <td class="px-5 py-3 text-right text-sm font-body text-gray-700">{{ formatMontant(c.montantEnvoye) }} F</td>
              <td class="px-5 py-3 text-right text-sm font-display font-semibold text-anatt-500">{{ formatMontant(c.montantRetour) }} F</td>
              <td class="px-5 py-3 text-center"><AppBadge variant="success">SUCCESS</AppBadge></td>
              <td class="px-5 py-3 text-center">
                <span class="text-xs font-body text-anatt-600 font-medium flex items-center justify-center gap-1">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  Valide auto
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="px-5 py-3 text-center text-xs font-body text-gray-400 border-t border-gray-100">
          Affichage de 3 sur {{ reconciliation.correspondances }} correspondances
        </div>
      </div>

      <!-- Discrepancies Tab -->
      <div v-if="activeTab === 'discrepancies'">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Beneficiaire</th>
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Telephone</th>
              <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant Lot</th>
              <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant Retour</th>
              <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Ecart</th>
              <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Resolution</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="e in ecarts" :key="e.beneficiaire" class="bg-amber-50/30">
              <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ e.beneficiaire }}</td>
              <td class="px-5 py-3 text-sm font-body text-gray-600">{{ e.telephone }}</td>
              <td class="px-5 py-3 text-right text-sm font-body text-gray-700">{{ formatMontant(e.montantLot) }} F</td>
              <td class="px-5 py-3 text-right text-sm font-display font-semibold text-amber-700">{{ formatMontant(e.montantRetour) }} F</td>
              <td class="px-5 py-3 text-center">
                <span class="px-2 py-0.5 rounded text-xs font-display font-bold" :class="e.ecart.startsWith('-') ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700'">{{ e.ecart }}</span>
              </td>
              <td class="px-5 py-3 text-center">
                <select v-model="e.resolution" class="px-2 py-1 border border-amber-200 rounded-lg text-xs font-body bg-white">
                  <option value="">-- Choisir --</option>
                  <option value="accept">Accepter retour</option>
                  <option value="keep">Garder lot</option>
                  <option value="dispute">Marquer litige</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Missing Tab -->
      <div v-if="activeTab === 'missing'">
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Beneficiaire</th>
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Telephone</th>
              <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant</th>
              <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Raison Probable</th>
              <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="n in nonTrouves" :key="n.beneficiaire" class="bg-red-50/30">
              <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ n.beneficiaire }}</td>
              <td class="px-5 py-3 text-sm font-body text-gray-600">{{ n.telephone }}</td>
              <td class="px-5 py-3 text-right text-sm font-body text-gray-700">{{ formatMontant(n.montant) }} F</td>
              <td class="px-5 py-3 text-xs font-body text-red-600">{{ n.raisonProbable }}</td>
              <td class="px-5 py-3 text-center">
                <button v-if="n.raisonProbable !== 'Numero non reconnu'" class="px-3 py-1 bg-orange-50 text-orange-700 rounded-lg text-xs font-display font-semibold hover:bg-orange-100 transition-colors flex items-center gap-1 mx-auto">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                  Relancer
                </button>
                <button v-else class="px-3 py-1 bg-gray-50 text-gray-600 rounded-lg text-xs font-display font-semibold hover:bg-gray-100 transition-colors flex items-center gap-1 mx-auto">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                  Corriger
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
