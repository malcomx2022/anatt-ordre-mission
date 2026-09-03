<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppTable from '@/components/shared/AppTable.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import EtatStatusBadge from '@/components/etats/EtatStatusBadge.vue'
import { etatsService } from '@/services/etats'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { numeroEtat } from '@/utils/etats'
import type { EtatPaiement, StatutEtat, TrancheEtat } from '@/types'

const toast = useToast()
const { canValiderOM, canViserOM, canApprouverOM } = useAuth()

const etats = ref<EtatPaiement[]>([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const statut = ref<StatutEtat | ''>('')
const tranche = ref<TrancheEtat | ''>('')
const search = ref('')

const fmt = (v: number) => v.toLocaleString('fr-FR')

const filtres: { valeur: StatutEtat | ''; libelle: string }[] = [
  { valeur: '', libelle: 'Tous' },
  { valeur: 'BROUILLON', libelle: 'Brouillons' },
  { valeur: 'CONTROLE', libelle: 'Contrôlés' },
  { valeur: 'VISA_DAF', libelle: 'Visés DAF' },
  { valeur: 'VALIDE', libelle: 'Validés' },
  { valeur: 'REJETE', libelle: 'Rejetés' },
]

const filtresTranche: { valeur: TrancheEtat | ''; libelle: string }[] = [
  { valeur: '', libelle: 'Toutes tranches' },
  { valeur: 'AVANCE', libelle: 'Avances' },
  { valeur: 'RELIQUAT', libelle: 'Reliquats' },
]

/**
 * Les files d'attente que l'utilisateur peut traiter : le contrôleur les
 * brouillons, le DAF les états contrôlés, le DG les états visés.
 */
const mesFiles = computed<StatutEtat[]>(() => {
  const files: StatutEtat[] = []
  if (canValiderOM()) files.push('BROUILLON')
  if (canViserOM()) files.push('CONTROLE')
  if (canApprouverOM()) files.push('VISA_DAF')
  return files
})

/**
 * La file d'attente propre à l'utilisateur — définie SEULEMENT s'il est un
 * acteur dédié du circuit (une seule étape). Un profil de supervision qui cumule
 * plusieurs étapes (ADMIN, SUPER_ADMIN) n'est pas piégé sur une file unique : il
 * voit tous les états par défaut (« Tous »), sinon un statut vide masquerait des
 * données bien présentes dans les autres statuts.
 */
const monStatut = computed<StatutEtat | null>(() =>
  mesFiles.value.length === 1 ? mesFiles.value[0]! : null,
)

/** Nombre d'états attendant l'utilisateur courant, dans la page affichée. */
const aTraiter = computed(() =>
  monStatut.value ? etats.value.filter((e) => e.statut === monStatut.value).length : 0,
)

async function charger() {
  loading.value = true
  try {
    const res = await etatsService.list({
      statut: statut.value || undefined,
      tranche: tranche.value || undefined,
      search: search.value || undefined,
      page: page.value,
    })
    etats.value = res.items
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  // On ouvre l'écran sur la file de l'utilisateur : il voit d'abord ce qui
  // l'attend, pas la liste complète.
  if (monStatut.value) statut.value = monStatut.value
  charger()
})

watch([statut, tranche, search], () => {
  page.value = 1
  charger()
})
watch(page, charger)

const periode = (e: EtatPaiement) => {
  const d = e.mission?.date_depart
  const r = e.mission?.date_retour
  if (!d || !r) return '—'
  const f = (v: string) => new Date(v).toLocaleDateString('fr-FR')
  return `${f(d)} → ${f(r)}`
}
</script>

<template>
  <AppLayout title="États de paiement" subtitle="Circuit contrôle → DAF → Directeur Général">
    <div class="space-y-6">
      <AppCard v-if="monStatut && aTraiter > 0" class="border-l-4 border-l-gold-400">
        <p class="text-sm font-body text-gray-700">
          <strong class="font-display">{{ aTraiter }}</strong>
          état(s) attendent votre intervention.
        </p>
      </AppCard>

      <AppCard>
        <div class="flex flex-wrap items-center gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher un numéro, un objet, une destination…"
            class="flex-1 min-w-[240px] px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm
                   font-body text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2
                   focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"
          />
          <div class="flex flex-wrap gap-2">
            <button
              v-for="f in filtres"
              :key="f.valeur"
              class="px-3 py-1.5 rounded-full text-xs font-display font-semibold transition-colors"
              :class="statut === f.valeur
                ? 'bg-anatt-500 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              @click="statut = f.valeur"
            >
              {{ f.libelle }}
            </button>
            <span class="w-px bg-gray-200 mx-1"></span>
            <button
              v-for="f in filtresTranche"
              :key="f.valeur"
              class="px-3 py-1.5 rounded-full text-xs font-display font-semibold transition-colors"
              :class="tranche === f.valeur
                ? 'bg-gold-500 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              @click="tranche = f.valeur"
            >
              {{ f.libelle }}
            </button>
          </div>
        </div>
      </AppCard>

      <AppTable :loading="loading">
        <template #header>
          <th class="px-6 py-3">Numéro</th>
          <th class="px-6 py-3">Tranche</th>
          <th class="px-6 py-3">Mission</th>
          <th class="px-6 py-3">Période</th>
          <th class="px-6 py-3 text-center">Bénéf.</th>
          <th class="px-6 py-3 text-right">Total</th>
          <th class="px-6 py-3">Statut</th>
        </template>
        <template #body>
          <tr v-if="!etats.length">
            <td colspan="7" class="px-6 py-10 text-center text-gray-400 font-body">
              Aucun état pour ce filtre.
            </td>
          </tr>
          <tr
            v-for="e in etats"
            :key="e.id"
            class="hover:bg-anatt-50/30 transition-colors cursor-pointer"
            @click="$router.push(`/missions/${e.mission_id}/etat`)"
          >
            <td class="px-6 py-4 text-sm font-body text-gray-900">
              {{ numeroEtat(e.numero) }}
            </td>
            <td class="px-6 py-4">
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase"
                :class="e.tranche === 'AVANCE' ? 'bg-anatt-100 text-anatt-700' : 'bg-gold-100 text-gold-700'"
              >
                {{ e.tranche === 'AVANCE' ? 'Avances' : 'Reliquats' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm font-body">
              <div class="text-gray-900">{{ e.mission?.objet ?? '—' }}</div>
              <div class="text-xs text-gray-500">{{ e.mission?.destination ?? '' }}</div>
            </td>
            <td class="px-6 py-4 text-sm font-body text-gray-600">{{ periode(e) }}</td>
            <td class="px-6 py-4 text-sm font-body text-center text-gray-700">
              {{ e.nb_beneficiaires }}
            </td>
            <td class="px-6 py-4 text-sm font-body text-right text-gray-900">
              {{ fmt(e.montant_total) }}
            </td>
            <td class="px-6 py-4"><EtatStatusBadge :statut="e.statut" /></td>
          </tr>
        </template>
        <template #footer>
          <AppPagination
            v-if="lastPage > 1"
            :current-page="page"
            :last-page="lastPage"
            :total="total"
            @page-change="page = $event"
          />
        </template>
      </AppTable>
    </div>
  </AppLayout>
</template>
