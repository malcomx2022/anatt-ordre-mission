<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppModal from '@/components/shared/AppModal.vue'
import { usePaiements } from '@/composables/usePaiements'
import type { Paiement, OperateurPaiement, StatutPaiement } from '@/types'

const router = useRouter()
const { loading } = usePaiements()

// Lot detail data (from mockup 21)
const lot = ref({
  reference: 'LOT-2026-0046',
  operateur: 'MOOV' as OperateurPaiement,
  type: 'Solde (2/3)',
  fichier: 'LOT_0046_MOOV.csv',
  generePar: 'Gislain A.',
  soumisLe: '14/04/2026 09:15',
  derniereMaj: '14/04/2026 11:42',
  statut: 'EN_COURS',
  montantTotal: 2736000,
  montantPaye: 1976000,
  montantEchec: 152000,
  montantAttente: 608000,
  progression: 75,
  reussis: 13,
  echoues: 1,
  enAttente: 4,
  totalTransactions: 18,
})

interface Transaction {
  id: number
  beneficiaire: string
  telephone: string
  refOM: string
  montant: number
  statut: 'SUCCES' | 'ECHEC' | 'EN_ATTENTE'
  transactionId: string
}

const filterStatut = ref('')

const transactions = ref<Transaction[]>([
  { id: 1, beneficiaire: 'ADJOVI Kossi Pierre', telephone: '95 12 34 56', refOM: 'OM-2026-0089', montant: 152000, statut: 'SUCCES', transactionId: 'TXN-8A3F2B1' },
  { id: 2, beneficiaire: 'BOKO Grace Annick', telephone: '96 45 67 89', refOM: 'OM-2026-0091', montant: 152000, statut: 'SUCCES', transactionId: 'TXN-9C4D3E2' },
  { id: 3, beneficiaire: 'DOSSOU Fabrice', telephone: '96 78 90 12', refOM: 'OM-2026-0093', montant: 152000, statut: 'ECHEC', transactionId: 'ERR: Numero invalide' },
  { id: 4, beneficiaire: 'GANDONOU Estelle', telephone: '95 23 45 67', refOM: 'OM-2026-0095', montant: 152000, statut: 'SUCCES', transactionId: 'TXN-2E5F4A3' },
  { id: 5, beneficiaire: 'HOUNKPATIN Joel', telephone: '96 34 56 78', refOM: 'OM-2026-0097', montant: 152000, statut: 'EN_ATTENTE', transactionId: '--' },
  { id: 6, beneficiaire: 'KIKI Mariette', telephone: '95 56 78 90', refOM: 'OM-2026-0099', montant: 152000, statut: 'SUCCES', transactionId: 'TXN-7G8H5B4' },
])

// Marquage lot paye (from mockup 37)
const showConfirmModal = ref(false)
const showSuccessModal = ref(false)
const confirmPassword = ref('')
const commentaire = ref('Lot valide apres verification complete des releves MOOV du 14/04/2026. Aucun ecart constate.')
const checks = ref({
  paiementsEnvoyes: true,
  reconciliationComplete: true,
  montantCorrespond: true,
  confirmationFinale: false,
})

const canMarkAsPaid = computed(() =>
  checks.value.paiementsEnvoyes &&
  checks.value.reconciliationComplete &&
  checks.value.montantCorrespond &&
  checks.value.confirmationFinale
)

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}

function statutVariant(statut: string): 'success' | 'warning' | 'danger' | 'info' {
  switch (statut) {
    case 'SUCCES': return 'success'
    case 'EN_ATTENTE': return 'warning'
    case 'ECHEC': return 'danger'
    default: return 'info'
  }
}

function statutLabel(statut: string): string {
  switch (statut) {
    case 'SUCCES': return 'Reussi'
    case 'EN_ATTENTE': return 'En attente'
    case 'ECHEC': return 'Echoue'
    default: return statut
  }
}

function confirmMarkPaid() {
  showConfirmModal.value = false
  showSuccessModal.value = true
}
</script>

<template>
  <AppLayout title="Detail Lot de Paiement" subtitle="Suivi des transactions et marquage du lot comme paye">
    <template #header-actions>
      <div class="flex items-center gap-2">
        <AppButton @click="router.push('/paiements/reconciliation')">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
          Reconcilier
        </AppButton>
        <AppButton variant="outline">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Telecharger
        </AppButton>
      </div>
    </template>

    <!-- Lot header info -->
    <div class="flex items-center gap-3 mb-6 animate-fade-up">
      <button @click="router.push('/paiements')" class="w-9 h-9 flex items-center justify-center rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 transition-colors">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      </button>
      <div>
        <div class="flex items-center gap-3">
          <h2 class="text-lg font-display font-bold text-gray-800">{{ lot.reference }}</h2>
          <AppBadge variant="info">En cours</AppBadge>
        </div>
        <p class="text-xs font-body text-gray-500">{{ lot.operateur === 'MOOV' ? 'MOOV Flooz' : 'MTN MoMo' }} -- {{ lot.type }} -- 14 Avril 2026</p>
      </div>
    </div>

    <!-- Progress & Summary grid -->
    <div class="grid grid-cols-3 gap-6 mb-6">
      <!-- Progress Ring -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Progression du Traitement</h3>
        <div class="flex items-center justify-center mb-4">
          <div class="relative w-32 h-32">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="52" stroke="#f3f4f6" stroke-width="8" fill="none"/>
              <circle cx="60" cy="60" r="52" stroke="#1D9E4F" stroke-width="8" fill="none" stroke-dasharray="326.7" :stroke-dashoffset="326.7 * (1 - lot.progression / 100)" stroke-linecap="round"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-2xl font-display font-bold text-gray-900">{{ lot.progression }}%</span>
              <span class="text-[10px] font-body text-gray-500">traite</span>
            </div>
          </div>
        </div>
        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs font-body">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-anatt-500 rounded-full"></span>Reussis</span>
            <span class="font-display font-bold text-gray-800">{{ lot.reussis }}</span>
          </div>
          <div class="flex items-center justify-between text-xs font-body">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-red-500 rounded-full"></span>Echoues</span>
            <span class="font-display font-bold text-gray-800">{{ lot.echoues }}</span>
          </div>
          <div class="flex items-center justify-between text-xs font-body">
            <span class="flex items-center gap-2"><span class="w-2.5 h-2.5 bg-gray-300 rounded-full"></span>En attente</span>
            <span class="font-display font-bold text-gray-800">{{ lot.enAttente }}</span>
          </div>
        </div>
      </div>

      <!-- Info Lot -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Informations du Lot</h3>
        <div class="space-y-3">
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Reference</span><span class="text-sm font-display font-bold text-gray-800">{{ lot.reference }}</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Operateur</span><span class="text-sm font-display font-semibold text-blue-700">MOOV Flooz</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Type</span><span class="text-sm font-body text-gray-700">{{ lot.type }}</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Fichier</span><span class="text-sm font-body text-gray-700">{{ lot.fichier }}</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Genere par</span><span class="text-sm font-body text-gray-700">{{ lot.generePar }}</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Soumis le</span><span class="text-sm font-body text-gray-700">{{ lot.soumisLe }}</span></div>
          <div class="flex justify-between"><span class="text-xs font-body text-gray-500">Derniere MAJ</span><span class="text-sm font-body text-gray-700">{{ lot.derniereMaj }}</span></div>
        </div>
      </div>

      <!-- Montants -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-200">
        <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Resume Financier</h3>
        <div class="space-y-4">
          <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-xs font-body text-gray-500 mb-1">Montant Total du Lot</p>
            <p class="text-2xl font-display font-bold text-gray-900">{{ formatMontant(lot.montantTotal) }} <span class="text-sm font-normal text-gray-500">FCFA</span></p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="bg-anatt-50 rounded-xl p-3 text-center">
              <p class="text-[10px] font-body text-anatt-600 font-medium">Paye</p>
              <p class="text-lg font-display font-bold text-anatt-500">{{ formatMontant(lot.montantPaye) }}</p>
            </div>
            <div class="bg-red-50 rounded-xl p-3 text-center">
              <p class="text-[10px] font-body text-red-500 font-medium">Echoue</p>
              <p class="text-lg font-display font-bold text-red-600">{{ formatMontant(lot.montantEchec) }}</p>
            </div>
          </div>
          <div class="bg-amber-50 rounded-xl p-3 text-center">
            <p class="text-[10px] font-body text-amber-600 font-medium">En attente de traitement</p>
            <p class="text-lg font-display font-bold text-amber-700">{{ formatMontant(lot.montantAttente) }} <span class="text-xs font-normal">FCFA</span></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-200">
      <h3 class="text-sm font-display font-semibold text-gray-700 mb-4">Chronologie</h3>
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-anatt-500 rounded-full flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div><p class="text-xs font-display font-semibold text-gray-800">Genere</p><p class="text-[10px] font-body text-gray-400">14/04 08:30</p></div>
        </div>
        <div class="flex-1 h-0.5 bg-anatt-500"></div>
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-anatt-500 rounded-full flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div><p class="text-xs font-display font-semibold text-gray-800">Valide</p><p class="text-[10px] font-body text-gray-400">14/04 09:00</p></div>
        </div>
        <div class="flex-1 h-0.5 bg-anatt-500"></div>
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-anatt-500 rounded-full flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div><p class="text-xs font-display font-semibold text-gray-800">Soumis</p><p class="text-[10px] font-body text-gray-400">14/04 09:15</p></div>
        </div>
        <div class="flex-1 h-0.5 bg-blue-400 animate-pulse"></div>
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-white animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          </div>
          <div><p class="text-xs font-display font-semibold text-blue-700">En cours</p><p class="text-[10px] font-body text-gray-400">75%</p></div>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200"></div>
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
          </div>
          <div><p class="text-xs font-display font-semibold text-gray-400">Termine</p><p class="text-[10px] font-body text-gray-300">--</p></div>
        </div>
      </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 animate-fade-up delay-300">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-display font-semibold text-gray-700">Transactions ({{ lot.totalTransactions }})</h3>
        <div class="flex items-center gap-2">
          <select v-model="filterStatut" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-body text-gray-600 outline-none">
            <option value="">Tous les statuts</option>
            <option value="SUCCES">Reussis</option>
            <option value="ECHEC">Echoues</option>
            <option value="EN_ATTENTE">En attente</option>
          </select>
          <button class="px-3 py-1.5 text-xs font-display text-anatt-500 font-semibold hover:bg-anatt-50 rounded-lg transition-colors">Exporter CSV</button>
        </div>
      </div>
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">#</th>
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Beneficiaire</th>
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Telephone</th>
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Ref. OM</th>
            <th class="text-right px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Montant</th>
            <th class="text-center px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
            <th class="text-left px-5 py-3 text-xs font-display font-semibold text-gray-500 uppercase">Transaction ID</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="txn in transactions" :key="txn.id" class="hover:bg-anatt-50/30 transition-colors" :class="txn.statut === 'ECHEC' ? 'bg-red-50/30' : ''">
            <td class="px-5 py-3 text-xs font-body text-gray-400">{{ txn.id }}</td>
            <td class="px-5 py-3 text-sm font-body font-medium text-gray-800">{{ txn.beneficiaire }}</td>
            <td class="px-5 py-3 text-sm font-body text-gray-600">{{ txn.telephone }}</td>
            <td class="px-5 py-3">
              <router-link :to="`/ordres/${txn.id}`" class="text-sm font-body text-anatt-500 font-medium hover:underline">{{ txn.refOM }}</router-link>
            </td>
            <td class="px-5 py-3 text-right text-sm font-display font-semibold text-gray-800">{{ formatMontant(txn.montant) }} F</td>
            <td class="px-5 py-3 text-center">
              <AppBadge :variant="statutVariant(txn.statut)">{{ statutLabel(txn.statut) }}</AppBadge>
            </td>
            <td class="px-5 py-3 text-xs font-mono" :class="txn.statut === 'ECHEC' ? 'text-red-400' : 'text-gray-400'">{{ txn.transactionId }}</td>
          </tr>
        </tbody>
      </table>
      <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm font-body text-gray-500">Affichage <span class="font-semibold text-gray-700">1-6</span> sur <span class="font-semibold text-gray-700">18</span></p>
        <div class="flex items-center gap-1">
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-anatt-500 text-white text-sm font-display font-semibold">1</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50">2</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50">3</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Marquage Lot Paye Section -->
    <div class="bg-white rounded-2xl border-2 border-amber-200 p-6 mb-6 animate-fade-up delay-300">
      <div class="flex items-start gap-4 mb-6">
        <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
          <h3 class="text-base font-display font-bold text-gray-800">Confirmer le marquage comme paye</h3>
          <p class="text-sm font-body text-gray-500 mt-1">Cette action est <strong class="text-red-500">irreversible</strong>. Le lot sera marque comme entierement paye et archive.</p>
        </div>
      </div>

      <div class="space-y-3 mb-6">
        <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-anatt-50 transition-colors group">
          <input type="checkbox" v-model="checks.paiementsEnvoyes" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
          <span class="text-sm font-body text-gray-700 group-hover:text-gray-900">Tous les paiements ont ete envoyes avec succes ({{ lot.totalTransactions }}/{{ lot.totalTransactions }})</span>
          <svg v-if="checks.paiementsEnvoyes" class="w-4 h-4 text-anatt-500 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </label>
        <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-anatt-50 transition-colors group">
          <input type="checkbox" v-model="checks.reconciliationComplete" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
          <span class="text-sm font-body text-gray-700 group-hover:text-gray-900">La reconciliation bancaire est complete (0 ecart)</span>
          <svg v-if="checks.reconciliationComplete" class="w-4 h-4 text-anatt-500 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </label>
        <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-anatt-50 transition-colors group">
          <input type="checkbox" v-model="checks.montantCorrespond" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
          <span class="text-sm font-body text-gray-700 group-hover:text-gray-900">Le montant total correspond au budget approuve</span>
          <svg v-if="checks.montantCorrespond" class="w-4 h-4 text-anatt-500 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </label>
        <label class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-xl cursor-pointer hover:bg-amber-100 transition-colors group">
          <input type="checkbox" v-model="checks.confirmationFinale" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
          <span class="text-sm font-body text-gray-700 group-hover:text-gray-900 font-medium">Je confirme avoir verifie toutes les informations ci-dessus</span>
        </label>
      </div>

      <div class="mb-6">
        <label class="block text-sm font-display font-semibold text-gray-700 mb-2">Commentaire / justification <span class="text-gray-400 font-normal">(optionnel)</span></label>
        <textarea v-model="commentaire" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 resize-none transition-all duration-200" placeholder="Ex: Lot valide apres verification complete des releves..."></textarea>
      </div>

      <div class="flex items-center justify-between">
        <AppButton variant="outline" @click="router.push('/paiements')">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Retour au lot
        </AppButton>
        <AppButton :disabled="!canMarkAsPaid" @click="showConfirmModal = true">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m6 12 4 4 8-8"/></svg>
          Marquer comme Paye
        </AppButton>
      </div>
    </div>

    <!-- Confirm Modal -->
    <AppModal :show="showConfirmModal" title="Derniere confirmation" @close="showConfirmModal = false">
      <div class="text-center">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-5">
          <svg class="w-8 h-8 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m6 12 4 4 8-8"/></svg>
        </div>
        <p class="text-sm font-body text-gray-500 mb-6">
          Vous etes sur le point de marquer le lot <strong>{{ lot.reference }}</strong> comme paye pour un montant de <strong class="text-anatt-500">{{ formatMontant(lot.montantTotal) }} FCFA</strong>. Cette action est irreversible.
        </p>
        <div class="bg-gray-50 rounded-xl p-4 mb-6">
          <label class="block text-sm font-display font-semibold text-gray-700 mb-2">Saisissez votre mot de passe pour confirmer</label>
          <input v-model="confirmPassword" type="password" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm font-body text-center tracking-widest focus:ring-2 focus:ring-anatt-500/30 transition-all duration-200" placeholder="........">
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" @click="showConfirmModal = false">Annuler</AppButton>
        <AppButton @click="confirmMarkPaid">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Confirmer
        </AppButton>
      </template>
    </AppModal>

    <!-- Success Modal -->
    <AppModal :show="showSuccessModal" title="Lot marque comme paye" @close="showSuccessModal = false">
      <div class="text-center">
        <div class="w-20 h-20 bg-anatt-50 rounded-full flex items-center justify-center mx-auto mb-5">
          <svg class="w-10 h-10 text-anatt-500" viewBox="0 0 40 40" fill="none">
            <circle cx="20" cy="20" r="18" stroke="#1D9E4F" stroke-width="2" fill="none"/>
            <path d="M12 20l6 6 10-12" stroke="#1D9E4F" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h3 class="text-xl font-display font-bold text-gray-800 mb-2">Lot marque comme paye !</h3>
        <p class="text-sm font-body text-gray-500 mb-6">Le lot {{ lot.reference }} a ete cloture avec succes. Les recus de paiement ont ete generes.</p>
        <div class="bg-anatt-50 rounded-xl p-4 mb-6 text-left space-y-2">
          <div class="flex items-center justify-between text-sm"><span class="font-body text-gray-600">Montant paye</span><span class="font-display font-bold text-anatt-500">{{ formatMontant(lot.montantTotal) }} FCFA</span></div>
          <div class="flex items-center justify-between text-sm"><span class="font-body text-gray-600">Agents payes</span><span class="font-display font-bold text-gray-800">{{ lot.totalTransactions }}</span></div>
          <div class="flex items-center justify-between text-sm"><span class="font-body text-gray-600">Date de cloture</span><span class="font-display font-bold text-gray-800">07/04/2026 16:42</span></div>
          <div class="flex items-center justify-between text-sm"><span class="font-body text-gray-600">Reference</span><span class="font-mono text-xs text-gray-500">PAY-2026-046-FINAL</span></div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="outline" @click="router.push('/paiements')">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          Voir les lots
        </AppButton>
        <AppButton>
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Telecharger recus
        </AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
