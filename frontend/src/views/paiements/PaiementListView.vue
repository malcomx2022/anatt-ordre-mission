<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { paiementsService } from '@/services/paiements'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import type { LotPaiement } from '@/types'

const toast = useToast()
const { canGenererPaiements } = useAuth()

const lots = ref<LotPaiement[]>([])
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const filterOperateur = ref('')
const filterStatut = ref('')
const filterTranche = ref('')

const fmt = (v: number) => v.toLocaleString('fr-FR')

async function load() {
  loading.value = true
  try {
    const { items, meta } = await paiementsService.lots({
      page: currentPage.value,
      operateur: filterOperateur.value || undefined,
      statut: filterStatut.value || undefined,
      tranche: filterTranche.value || undefined,
    })
    lots.value = items
    currentPage.value = meta.current_page
    totalPages.value = meta.last_page
    totalItems.value = meta.total
  } finally {
    loading.value = false
  }
}

watch([filterOperateur, filterStatut, filterTranche], () => { currentPage.value = 1; load() })
onMounted(load)

function changePage(p: number) { currentPage.value = p; load() }

async function telecharger(lot: LotPaiement) {
  try {
    await paiementsService.telecharger(lot)
  } catch {
    toast.error('Téléchargement impossible.')
  }
}
</script>

<template>
  <AppLayout title="Lots de paiement" :subtitle="`${totalItems} lot(s)`">
    <template #header-actions>
      <router-link v-if="canGenererPaiements()" to="/paiements/mtn" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600">+ Nouvelle génération</router-link>
    </template>

    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <select v-model="filterOperateur" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Tous opérateurs</option>
          <option value="MTN">MTN</option>
          <option value="MOOV">MOOV</option>
        </select>
        <select v-model="filterTranche" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Toutes tranches</option>
          <option value="AVANCE">Avance</option>
          <option value="RELIQUAT">Reliquat</option>
        </select>
        <select v-model="filterStatut" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Tous statuts</option>
          <option value="GENERE">Généré</option>
          <option value="CONFIRME">Confirmé</option>
          <option value="ERREUR">Erreur</option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Mission</th>
              <th class="px-5 py-3">Tranche</th>
              <th class="px-5 py-3">Opérateur</th>
              <th class="px-5 py-3">Lignes</th>
              <th class="px-5 py-3">Montant</th>
              <th class="px-5 py-3">Statut</th>
              <th class="px-5 py-3">Généré le</th>
              <th class="px-5 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="8" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!lots.length"><td colspan="8" class="px-5 py-10 text-center text-gray-400 font-body">Aucun lot généré.</td></tr>
            <tr v-for="lot in lots" :key="lot.id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 font-body">{{ lot.mission?.objet ?? '—' }} <span class="text-gray-400 text-xs">{{ lot.mission?.destination }}</span></td>
              <td class="px-5 py-3 font-display">
                {{ lot.tranche }}
                <span v-if="(lot.sequence ?? 1) > 1" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-600">complémentaire n° {{ lot.sequence }}</span>
              </td>
              <td class="px-5 py-3"><OperateurBadge :operateur="lot.operateur" /></td>
              <td class="px-5 py-3">{{ lot.nb_lignes }}</td>
              <td class="px-5 py-3 font-display font-semibold">{{ fmt(lot.montant_total) }}</td>
              <td class="px-5 py-3"><PaiementStatusBadge :statut="lot.statut" /></td>
              <td class="px-5 py-3 font-body text-gray-500 text-xs">{{ lot.genere_le ? new Date(lot.genere_le).toLocaleDateString('fr-FR') : '—' }}</td>
              <td class="px-5 py-3">
                <button v-if="lot.nom_fichier" class="text-anatt-600 hover:underline text-xs font-display font-semibold" @click="telecharger(lot)">Télécharger</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="changePage" />
    </div>
  </AppLayout>
</template>
