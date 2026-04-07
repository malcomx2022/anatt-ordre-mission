<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { usePaiements } from '@/composables/usePaiements'
import type { LotPaiement, OperateurPaiement } from '@/types'

const router = useRouter()
const { loading } = usePaiements()

const searchQuery = ref('')
const filterOperateur = ref('')
const filterStatut = ref('')
const filterType = ref('')
const filterMois = ref('2026-04')
const currentPage = ref(1)

interface LotPaiementDetail extends LotPaiement {
  type_paiement: string
  date_heure: string
}

const lots = ref<LotPaiementDetail[]>([
  { id: 1, reference: 'LOT-2026-0047', operateur: 'MTN', nb_paiements: 24, montant_total: 1248000, statut: 'EN_ATTENTE', type_paiement: 'Avance (1/3)', date_heure: '15/04/2026 14:32', created_at: '2026-04-15' },
  { id: 2, reference: 'LOT-2026-0046', operateur: 'MOOV', nb_paiements: 18, montant_total: 2736000, statut: 'EN_COURS', type_paiement: 'Solde (2/3)', date_heure: '14/04/2026 09:15', created_at: '2026-04-14' },
  { id: 3, reference: 'LOT-2026-0045', operateur: 'MTN', nb_paiements: 31, montant_total: 1612000, statut: 'TRAITE', type_paiement: 'Avance (1/3)', date_heure: '12/04/2026 16:48', created_at: '2026-04-12' },
  { id: 4, reference: 'LOT-2026-0044', operateur: 'MOOV', nb_paiements: 12, montant_total: 624000, statut: 'ECHEC', type_paiement: 'Avance (1/3)', date_heure: '10/04/2026 11:22', created_at: '2026-04-10' },
  { id: 5, reference: 'LOT-2026-0043', operateur: 'MTN', nb_paiements: 28, montant_total: 4256000, statut: 'TRAITE', type_paiement: 'Solde (2/3)', date_heure: '08/04/2026 08:55', created_at: '2026-04-08' },
])

const kpis = computed(() => ({
  total: 47,
  enAttente: 8,
  succes: 35,
  echecs: 4,
}))

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR') + ' F'
}

function statutVariant(statut: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' {
  switch (statut) {
    case 'TRAITE': return 'success'
    case 'EN_ATTENTE': return 'warning'
    case 'EN_COURS': return 'info'
    case 'ECHEC': return 'danger'
    default: return 'neutral'
  }
}

function statutLabel(statut: string): string {
  switch (statut) {
    case 'TRAITE': return 'Traite'
    case 'EN_ATTENTE': return 'En attente'
    case 'EN_COURS': return 'En cours'
    case 'ECHEC': return 'Echoue'
    default: return statut
  }
}

function voirDetail(lot: LotPaiementDetail) {
  router.push(`/paiements/statuts`)
}
</script>

<template>
  <AppLayout title="Lots de Paiement" subtitle="Suivi et gestion des fichiers de paiement generes">
    <template #header-actions>
      <AppButton @click="router.push('/paiements/moov')">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Nouveau Lot
      </AppButton>
    </template>

    <!-- KPI Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8l-2 4h12z"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Total</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.total }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Lots generes</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-amber-600 bg-amber-50 px-2 py-1 rounded-full">En attente</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.enAttente }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">A soumettre</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-anatt-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-anatt-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-anatt-600 bg-anatt-50 px-2 py-1 rounded-full">Succes</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.succes }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Paiements effectues</p>
      </div>
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <span class="text-xs font-display font-semibold text-red-500 bg-red-50 px-2 py-1 rounded-full">Echecs</span>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ kpis.echecs }}</p>
        <p class="text-xs font-body text-gray-500 mt-1">Lots avec erreurs</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher par reference, operateur..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="filterOperateur" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les operateurs</option>
          <option value="MTN">MTN MoMo</option>
          <option value="MOOV">MOOV Flooz</option>
        </select>
        <select v-model="filterStatut" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Tous les statuts</option>
          <option value="EN_ATTENTE">En attente</option>
          <option value="EN_COURS">En cours</option>
          <option value="TRAITE">Traite</option>
          <option value="ECHEC">Echoue</option>
        </select>
        <select v-model="filterType" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
          <option value="">Type paiement</option>
          <option value="avance">Avance (1/3)</option>
          <option value="solde">Solde (2/3)</option>
        </select>
        <input v-model="filterMois" type="month" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:ring-2 focus:ring-anatt-500/30 outline-none">
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-100">
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Reference</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Operateur</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Type</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Beneficiaires</th>
            <th class="text-right px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Montant Total</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
            <th class="text-left px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Date</th>
            <th class="text-center px-5 py-3.5 text-xs font-display font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="lot in lots" :key="lot.id" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-4">
              <span class="text-sm font-display font-bold text-anatt-500">{{ lot.reference }}</span>
            </td>
            <td class="px-5 py-4">
              <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-display font-semibold"
                :class="lot.operateur === 'MTN' ? 'bg-yellow-50 text-yellow-800' : 'bg-blue-50 text-blue-800'"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                {{ lot.operateur === 'MTN' ? 'MTN MoMo' : 'MOOV Flooz' }}
              </span>
            </td>
            <td class="px-5 py-4"><span class="text-sm font-body text-gray-700">{{ lot.type_paiement }}</span></td>
            <td class="px-5 py-4 text-center"><span class="text-sm font-display font-semibold text-gray-800">{{ lot.nb_paiements }}</span></td>
            <td class="px-5 py-4 text-right"><span class="text-sm font-display font-bold text-gray-900">{{ formatMontant(lot.montant_total) }}</span></td>
            <td class="px-5 py-4 text-center">
              <AppBadge :variant="statutVariant(lot.statut)">{{ statutLabel(lot.statut) }}</AppBadge>
            </td>
            <td class="px-5 py-4">
              <span class="text-sm font-body text-gray-600">{{ lot.date_heure.split(' ')[0] }}</span><br>
              <span class="text-[10px] font-body text-gray-400">{{ lot.date_heure.split(' ')[1] }}</span>
            </td>
            <td class="px-5 py-4 text-center">
              <div class="flex items-center justify-center gap-1">
                <button @click="voirDetail(lot)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 hover:text-anatt-500 transition-colors" title="Details">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button v-if="lot.statut === 'EN_ATTENTE'" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-anatt-50 text-gray-400 hover:text-anatt-600 transition-colors" title="Soumettre">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                </button>
                <button v-if="lot.statut === 'EN_COURS'" @click="router.push('/paiements/reconciliation')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-purple-50 text-gray-400 hover:text-purple-600 transition-colors" title="Reconcilier">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                </button>
                <button v-if="lot.statut === 'ECHEC'" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-orange-50 text-gray-400 hover:text-orange-600 transition-colors" title="Relancer">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                </button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-gray-400 hover:text-blue-600 transition-colors" title="Telecharger">
                  <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm font-body text-gray-500">
          Affichage <span class="font-semibold text-gray-700">1-5</span> sur <span class="font-semibold text-gray-700">47</span>
        </p>
        <div class="flex items-center gap-1">
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-anatt-500 text-white text-sm font-display font-semibold">1</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">2</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">3</button>
          <span class="text-gray-400 px-1">...</span>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 text-sm font-body hover:bg-gray-50">10</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
