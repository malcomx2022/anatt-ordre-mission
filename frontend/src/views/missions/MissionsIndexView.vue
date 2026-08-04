<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppTable from '@/components/shared/AppTable.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import EtatStatusBadge from '@/components/etats/EtatStatusBadge.vue'
import { missionsService } from '@/services/missions'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { numeroEtat } from '@/utils/etats'
import type { MissionResume, StatutEtat } from '@/types'

const toast = useToast()
const { canCreerMissions } = useAuth()

const missions = ref<MissionResume[]>([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const etat = ref<StatutEtat | 'sans_etat' | ''>('')
const search = ref('')

const fmt = (v: number) => v.toLocaleString('fr-FR')

const filtres: { valeur: StatutEtat | 'sans_etat' | ''; libelle: string }[] = [
  { valeur: '', libelle: 'Toutes' },
  { valeur: 'sans_etat', libelle: 'Sans état' },
  { valeur: 'BROUILLON', libelle: 'Brouillon' },
  { valeur: 'CONTROLE', libelle: 'Contrôlé' },
  { valeur: 'VISA_DAF', libelle: 'Visé DAF' },
  { valeur: 'VALIDE', libelle: 'Validé' },
]

async function charger() {
  loading.value = true
  try {
    const res = await missionsService.list({
      etat: etat.value || undefined,
      search: search.value || undefined,
      page: page.value,
    })
    missions.value = res.items
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(charger)

watch([etat, search], () => {
  page.value = 1
  charger()
})
watch(page, charger)

const periode = (m: MissionResume) => {
  if (!m.date_depart || !m.date_retour) return '—'
  const f = (v: string) => new Date(v).toLocaleDateString('fr-FR')
  return `${f(m.date_depart)} → ${f(m.date_retour)}`
}
</script>

<template>
  <AppLayout
    title="Missions"
    subtitle="Chaque mission porte ses ordres de mission et son état de paiement"
  >
    <template #header-actions>
      <router-link
        v-if="canCreerMissions()"
        to="/ordres/nouveau"
        class="px-5 py-2.5 rounded-xl bg-anatt-500 text-white font-display font-bold text-sm
               hover:bg-anatt-600 transition-all duration-200"
      >
        Retranscrire une mission
      </router-link>
    </template>

    <div class="space-y-6">
      <AppCard>
        <div class="flex flex-wrap items-center gap-3">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher un objet, une destination, une imputation…"
            class="flex-1 min-w-[240px] px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm
                   font-body text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2
                   focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"
          />
          <div class="flex flex-wrap gap-2">
            <button
              v-for="f in filtres"
              :key="f.valeur"
              class="px-3 py-1.5 rounded-full text-xs font-display font-semibold transition-colors"
              :class="etat === f.valeur
                ? 'bg-anatt-500 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              @click="etat = f.valeur"
            >
              {{ f.libelle }}
            </button>
          </div>
        </div>
      </AppCard>

      <AppTable :loading="loading">
        <template #header>
          <th class="px-6 py-3">Mission</th>
          <th class="px-6 py-3">Période</th>
          <th class="px-6 py-3">Imputation</th>
          <th class="px-6 py-3 text-center">OM</th>
          <th class="px-6 py-3 text-right">Montant</th>
          <th class="px-6 py-3">État de paiement</th>
        </template>
        <template #body>
          <tr v-if="!missions.length">
            <td colspan="6" class="px-6 py-10 text-center text-gray-400 font-body">
              Aucune mission pour ce filtre.
            </td>
          </tr>
          <tr
            v-for="m in missions"
            :key="m.id"
            class="hover:bg-anatt-50/30 transition-colors cursor-pointer"
            @click="$router.push(`/missions/${m.id}/etat`)"
          >
            <td class="px-6 py-4 text-sm font-body">
              <div class="text-gray-900">{{ m.objet }}</div>
              <div class="text-xs text-gray-500">{{ m.destination }}</div>
            </td>
            <td class="px-6 py-4 text-sm font-body text-gray-600">{{ periode(m) }}</td>
            <td class="px-6 py-4 text-sm font-body text-gray-600">
              {{ m.imputation_budgetaire || '—' }}
            </td>
            <td class="px-6 py-4 text-sm font-body text-center text-gray-700">{{ m.nb_ordres }}</td>
            <td class="px-6 py-4 text-sm font-body text-right text-gray-900">
              {{ fmt(m.montant_total) }}
            </td>
            <td class="px-6 py-4">
              <!-- Deux documents par mission : avances, puis reliquats. -->
              <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold text-gray-400 uppercase w-9">Av.</span>
                  <EtatStatusBadge v-if="m.etat_statut" :statut="m.etat_statut" />
                  <AppBadge v-else variant="neutral">À établir</AppBadge>
                  <span v-if="m.etat_numero" class="text-xs text-gray-500 font-body">
                    {{ numeroEtat(m.etat_numero) }}
                  </span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold text-gray-400 uppercase w-9">Rel.</span>
                  <EtatStatusBadge v-if="m.etat_reliquat_statut" :statut="m.etat_reliquat_statut" />
                  <span v-else class="text-xs text-gray-400 font-body italic">
                    {{ m.etat_statut === 'VALIDE' ? 'À établir' : 'Verrouillé' }}
                  </span>
                  <span v-if="m.etat_reliquat_numero" class="text-xs text-gray-500 font-body">
                    {{ numeroEtat(m.etat_reliquat_numero) }}
                  </span>
                </div>
              </div>
            </td>
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
