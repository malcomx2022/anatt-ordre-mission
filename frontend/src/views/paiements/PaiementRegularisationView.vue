<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { paiementsService } from '@/services/paiements'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import type { Regularisation, Operateur } from '@/types'

const { can } = useAuth()
const toast = useToast()

const lignes = ref<Regularisation[]>([])
const loading = ref(true)
const enCours = ref<number | null>(null)
const filtreStatut = ref<'' | 'A_RECOUVRER' | 'RECOUVRE'>('')
const recherche = ref('')
// Total GLOBAL à recouvrer (renvoyé par l'API), indépendant de la pagination.
const totalARecouvrer = ref(0)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)

const fmt = (v: number) => v.toLocaleString('fr-FR')
const peutRecouvrer = computed(() => can('paiements.confirmer'))

async function load() {
  loading.value = true
  try {
    const { page, totalARecouvrer: total } = await paiementsService.regularisations({
      page: currentPage.value,
      statut: filtreStatut.value || undefined,
      search: recherche.value || undefined,
    })
    lignes.value = page.items
    currentPage.value = page.meta.current_page
    totalPages.value = page.meta.last_page
    totalItems.value = page.meta.total
    totalARecouvrer.value = total
  } finally {
    loading.value = false
  }
}

async function recouvrer(l: Regularisation) {
  enCours.value = l.versement_id
  try {
    await paiementsService.recouvrer(l.versement_id)
    toast.success('Régularisation marquée comme recouvrée.')
    await load()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    enCours.value = null
  }
}

async function telechargerDocument(l: Regularisation) {
  try {
    await paiementsService.documentRetenue(l.versement_id)
  } catch (e) {
    toast.error(getErrorMessage(e))
  }
}

function changerFiltre(v: '' | 'A_RECOUVRER' | 'RECOUVRE') {
  filtreStatut.value = v
  currentPage.value = 1
  load()
}

function changePage(p: number) {
  currentPage.value = p
  load()
}

// Recherche : on attend une pause de frappe avant de recharger (retour page 1).
let rechercheTimer: ReturnType<typeof setTimeout> | undefined
watch(recherche, () => {
  clearTimeout(rechercheTimer)
  rechercheTimer = setTimeout(() => {
    currentPage.value = 1
    load()
  }, 300)
})

onMounted(load)
</script>

<template>
  <AppLayout title="Régularisations" subtitle="Trop-perçus à récupérer (retenue sur salaire)">
    <!-- Rappel du modèle : l'application ne réalise pas la retenue, elle la trace. -->
    <div class="bg-gold-50 border border-gold-200 rounded-2xl p-4 mb-6 text-sm font-body text-gold-700">
      Un <strong>trop-perçu</strong> naît quand un ordre de mission est réduit après le paiement de l’avance :
      le reliquat devient négatif et sort du circuit Mobile Money. La récupération se fait par
      <strong>retenue sur salaire</strong> (processus RH) ; marquez la ligne « recouvrée » une fois la retenue appliquée.
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-xl border border-gray-200 overflow-hidden">
          <button
            v-for="f in ([['', 'Tous'], ['A_RECOUVRER', 'À recouvrer'], ['RECOUVRE', 'Recouvrés']] as const)"
            :key="f[0]"
            class="px-4 py-2 text-sm font-display font-medium transition-colors"
            :class="filtreStatut === f[0] ? 'bg-anatt-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
            @click="changerFiltre(f[0])"
          >{{ f[1] }}</button>
        </div>
        <input
          v-model="recherche"
          type="search"
          placeholder="Rechercher (bénéficiaire, réf. SEMO, mission)…"
          class="w-72 max-w-full px-4 py-2 rounded-xl border border-gray-200 bg-white text-sm font-body
                 focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
        >
      </div>
      <div v-if="totalARecouvrer > 0" class="text-sm font-body text-gray-500">
        Reste à recouvrer :
        <span class="font-display font-bold text-red-600">{{ fmt(totalARecouvrer) }} FCFA</span>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Bénéficiaire</th>
              <th class="px-5 py-3">Réf. SEMO</th>
              <th class="px-5 py-3">Mission</th>
              <th class="px-5 py-3">Opérateur</th>
              <th class="px-5 py-3 text-right">À recouvrer</th>
              <th class="px-5 py-3">Statut</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="7" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!lignes.length"><td colspan="7" class="px-5 py-10 text-center text-gray-400 font-body">Aucune régularisation.</td></tr>
            <tr v-for="l in lignes" :key="l.versement_id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 font-body">{{ l.beneficiaire }}</td>
              <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ l.reference_semo }}</td>
              <td class="px-5 py-3 font-body text-gray-600">{{ l.mission }}</td>
              <td class="px-5 py-3"><OperateurBadge :operateur="(l.operateur as Operateur)" /></td>
              <td class="px-5 py-3 text-right font-display font-bold text-red-600">{{ fmt(l.montant_a_recouvrer) }}</td>
              <td class="px-5 py-3"><PaiementStatusBadge :statut="l.statut" /></td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-display font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                    title="Document de retenue (PDF)"
                    @click="telechargerDocument(l)"
                  >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                      <polyline points="14 2 14 8 20 8" />
                    </svg>
                    Document
                  </button>
                  <AppButton
                    v-if="peutRecouvrer && l.statut === 'A_RECOUVRER'"
                    size="sm"
                    :loading="enCours === l.versement_id"
                    @click="recouvrer(l)"
                  >Marquer recouvré</AppButton>
                  <span v-else-if="l.statut === 'RECOUVRE'" class="text-xs font-body text-gray-400">
                    {{ l.date_regularisation ? new Date(l.date_regularisation).toLocaleDateString('fr-FR') : '—' }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="changePage" />
    </div>
  </AppLayout>
</template>
