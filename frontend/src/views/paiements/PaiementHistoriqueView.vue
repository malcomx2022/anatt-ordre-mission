<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { usePaiements } from '@/composables/usePaiements'
import type { Paiement, OperateurPaiement, StatutPaiement } from '@/types'

const router = useRouter()
const { loading } = usePaiements()

const searchQuery = ref('')
const filterOperateur = ref('')
const filterStatut = ref('')
const dateDebut = ref('2026-01-01')
const dateFin = ref('2026-04-07')

interface HistoriquePaiement {
  id: number
  reference: string
  agent: string
  refOM: string
  operateur: OperateurPaiement
  telephone: string
  montant: number
  statut: StatutPaiement
  dateVirement: string
  refTransaction: string
}

const paiements = ref<HistoriquePaiement[]>([
  { id: 1, reference: 'PAY-2026-0312', agent: 'MAGBE Victorin', refOM: 'OM-2026-0089', operateur: 'MTN', telephone: '22997123456', montant: 185000, statut: 'SUCCES', dateVirement: '07/04/2026 14:32', refTransaction: 'TXN-8A3F2B1' },
  { id: 2, reference: 'PAY-2026-0311', agent: 'ADJOVI Kossi Pierre', refOM: 'OM-2026-0091', operateur: 'MOOV', telephone: '22995123456', montant: 152000, statut: 'SUCCES', dateVirement: '07/04/2026 14:30', refTransaction: 'TXN-9C4D3E2' },
  { id: 3, reference: 'PAY-2026-0310', agent: 'DOSSOU Fabrice', refOM: 'OM-2026-0093', operateur: 'MTN', telephone: '22996789012', montant: 152000, statut: 'ECHEC', dateVirement: '06/04/2026 11:15', refTransaction: 'ERR-TIMEOUT' },
  { id: 4, reference: 'PAY-2026-0309', agent: 'BOKO Grace Annick', refOM: 'OM-2026-0087', operateur: 'MOOV', telephone: '22996456789', montant: 98500, statut: 'SUCCES', dateVirement: '05/04/2026 09:22', refTransaction: 'TXN-2E5F4A3' },
  { id: 5, reference: 'PAY-2026-0308', agent: 'GANDONOU Estelle', refOM: 'OM-2026-0085', operateur: 'MTN', telephone: '22995234567', montant: 127000, statut: 'SUCCES', dateVirement: '05/04/2026 09:20', refTransaction: 'TXN-7G8H5B4' },
  { id: 6, reference: 'PAY-2026-0307', agent: 'SAOSSI Paterne', refOM: 'OM-2026-0083', operateur: 'MOOV', telephone: '22967234567', montant: 142500, statut: 'EN_ATTENTE', dateVirement: '04/04/2026 16:45', refTransaction: '--' },
  { id: 7, reference: 'PAY-2026-0306', agent: 'KONE Jean-Baptiste', refOM: 'OM-2026-0081', operateur: 'MTN', telephone: '22994567890', montant: 115200, statut: 'SUCCES', dateVirement: '04/04/2026 16:40', refTransaction: 'TXN-4K9L6C5' },
  { id: 8, reference: 'PAY-2026-0305', agent: 'HOUNKPATIN Joel', refOM: 'OM-2026-0079', operateur: 'MTN', telephone: '22996345678', montant: 152000, statut: 'INITIE', dateVirement: '03/04/2026 10:00', refTransaction: 'PENDING-001' },
  { id: 9, reference: 'PAY-2026-0304', agent: 'DOSSA Marie', refOM: 'OM-2026-0077', operateur: 'MOOV', telephone: '22995789012', montant: 89000, statut: 'SUCCES', dateVirement: '02/04/2026 15:30', refTransaction: 'TXN-5M2N7D6' },
  { id: 10, reference: 'PAY-2026-0303', agent: 'HOUNSA Michel', refOM: 'OM-2026-0075', operateur: 'MTN', telephone: '22996112233', montant: 175000, statut: 'SUCCES', dateVirement: '01/04/2026 08:55', refTransaction: 'TXN-6P3Q8E7' },
])

const stats = computed(() => {
  const total = paiements.value.length
  const succes = paiements.value.filter(p => p.statut === 'SUCCES').length
  const echec = paiements.value.filter(p => p.statut === 'ECHEC').length
  const montantTotal = paiements.value.reduce((s, p) => s + p.montant, 0)
  const montantPaye = paiements.value.filter(p => p.statut === 'SUCCES').reduce((s, p) => s + p.montant, 0)
  return { total, succes, echec, montantTotal, montantPaye }
})

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}

function statutVariant(statut: StatutPaiement): 'success' | 'warning' | 'danger' | 'info' {
  switch (statut) {
    case 'SUCCES': return 'success'
    case 'EN_ATTENTE': return 'warning'
    case 'ECHEC': return 'danger'
    case 'INITIE': return 'info'
    default: return 'info'
  }
}

function statutLabel(statut: StatutPaiement): string {
  switch (statut) {
    case 'SUCCES': return 'Succes'
    case 'EN_ATTENTE': return 'En attente'
    case 'ECHEC': return 'Echoue'
    case 'INITIE': return 'Initie'
    default: return statut
  }
}
</script>

<template>
  <AppLayout title="Historique des Paiements" subtitle="Historique complet des virements par periode">
    <template #header-actions>
      <AppButton variant="outline">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exporter Excel
      </AppButton>
    </template>

    <!-- Stats Summary -->
    <div class="grid grid-cols-5 gap-4 mb-6 animate-fade-up">
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-2">
          <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
        </div>
        <p class="text-xl font-display font-bold text-gray-900">{{ stats.total }}</p>
        <p class="text-xs font-body text-gray-500">Total virements</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-2">
          <div class="w-9 h-9 bg-anatt-50 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
        </div>
        <p class="text-xl font-display font-bold text-gray-900">{{ stats.succes }}</p>
        <p class="text-xs font-body text-gray-500">Reussis</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-2">
          <div class="w-9 h-9 bg-red-50 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
          </div>
        </div>
        <p class="text-xl font-display font-bold text-gray-900">{{ stats.echec }}</p>
        <p class="text-xs font-body text-gray-500">Echoues</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-2">
          <div class="w-9 h-9 bg-gold-50 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-gold-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
        </div>
        <p class="text-xl font-display font-bold text-gray-900">{{ formatMontant(stats.montantTotal) }}</p>
        <p class="text-xs font-body text-gray-500">Montant total (F)</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-2">
          <div class="w-9 h-9 bg-anatt-50 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
        </div>
        <p class="text-xl font-display font-bold text-anatt-500">{{ formatMontant(stats.montantPaye) }}</p>
        <p class="text-xs font-body text-gray-500">Montant paye (F)</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher par agent, reference, telephone..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="filterOperateur" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les operateurs</option>
          <option value="MTN">MTN MoMo</option>
          <option value="MOOV">MOOV Flooz</option>
        </select>
        <select v-model="filterStatut" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les statuts</option>
          <option value="SUCCES">Succes</option>
          <option value="ECHEC">Echoue</option>
          <option value="EN_ATTENTE">En attente</option>
          <option value="INITIE">Initie</option>
        </select>
        <div class="flex items-center gap-2">
          <input v-model="dateDebut" type="date" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <span class="text-xs font-body text-gray-400">au</span>
          <input v-model="dateFin" type="date" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Agent</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Ref. OM</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Operateur</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Telephone</th>
            <th class="text-right px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Montant</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Transaction</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="p in paiements" :key="p.id" class="hover:bg-anatt-50/30 transition-colors cursor-pointer" :class="p.statut === 'ECHEC' ? 'bg-red-50/20' : ''">
            <td class="px-5 py-4">
              <span class="text-sm font-display font-bold text-anatt-500">{{ p.reference }}</span>
            </td>
            <td class="px-5 py-4">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-display font-bold" :class="p.operateur === 'MTN' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700'">
                  {{ p.agent.split(' ').map(n => n[0]).slice(0, 2).join('') }}
                </div>
                <span class="text-sm font-body font-medium text-gray-800">{{ p.agent }}</span>
              </div>
            </td>
            <td class="px-5 py-4">
              <router-link :to="`/ordres/${p.id}`" class="text-sm font-body text-anatt-500 hover:underline">{{ p.refOM }}</router-link>
            </td>
            <td class="px-5 py-4">
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-display font-semibold"
                :class="p.operateur === 'MTN' ? 'bg-yellow-50 text-yellow-800' : 'bg-blue-50 text-blue-800'"
              >
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                {{ p.operateur === 'MTN' ? 'MTN' : 'MOOV' }}
              </span>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-mono text-gray-600">{{ p.telephone }}</span>
            </td>
            <td class="px-5 py-4 text-right">
              <span class="text-sm font-display font-bold text-gray-900">{{ formatMontant(p.montant) }} F</span>
            </td>
            <td class="px-5 py-4 text-center">
              <AppBadge :variant="statutVariant(p.statut)">{{ statutLabel(p.statut) }}</AppBadge>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-body text-gray-600">{{ p.dateVirement.split(' ')[0] }}</span><br>
              <span class="text-[10px] font-body text-gray-400">{{ p.dateVirement.split(' ')[1] }}</span>
            </td>
            <td class="px-5 py-4">
              <span class="text-xs font-mono" :class="p.statut === 'ECHEC' ? 'text-red-400' : 'text-gray-400'">{{ p.refTransaction }}</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm font-body text-gray-500">
          Affichage <span class="font-semibold text-gray-700">1-10</span> sur <span class="font-semibold text-gray-700">312</span>
        </p>
        <div class="flex items-center gap-1">
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-anatt-500 text-white text-sm font-display font-semibold">1</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">2</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">3</button>
          <span class="text-gray-400 px-1">...</span>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">32</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
