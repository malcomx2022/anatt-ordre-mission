<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import AppModal from '@/components/shared/AppModal.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { useMissions } from '@/composables/useMissions'
import type { OrdreMission, StatutMission } from '@/types'

const router = useRouter()
const { loading } = useMissions()

const searchQuery = ref('')
const filterStatus = ref('')
const filterMonth = ref('')

const stats = ref({
  total: 156,
  brouillons: 12,
  en_attente: 8,
  valides: 89,
  payes: 42,
  rejetes: 5,
})

const missions = ref([
  {
    id: 1,
    numero: 'OM-2026-000789',
    semo: 'SEMO-4517',
    agent: { nom: 'MAGBE', prenom: 'Victorin', initiales: 'MV', categorie: 'Cadre', bgColor: 'bg-blue-100', textColor: 'text-blue-600', salaire: '>=800K' },
    destination: 'Parakou',
    periode: '01-03 Avr 2026',
    nb_jours: 3,
    montant: 60000,
    statut: 'PAYE' as StatutMission,
    avance: { montant: 20000, statut: 'paye' },
    reliquat: { montant: 40000, statut: 'paye' },
  },
  {
    id: 2,
    numero: 'OM-2026-000790',
    semo: 'SEMO-4518',
    agent: { nom: 'ADJOVI', prenom: 'Pierre', initiales: 'AP', categorie: 'Agent', bgColor: 'bg-green-100', textColor: 'text-green-600', salaire: '<400K' },
    destination: 'Natitingou',
    periode: '05-07 Avr 2026',
    nb_jours: 3,
    montant: 45000,
    statut: 'VALIDE_N1' as StatutMission,
    avance: { montant: 15000, statut: 'paye' },
    reliquat: { montant: 30000, statut: 'en_attente' },
  },
  {
    id: 3,
    numero: 'OM-2026-000791',
    semo: 'SEMO-4519',
    agent: { nom: 'DOSSA', prenom: 'Marie', initiales: 'DM', categorie: 'Chef Service', bgColor: 'bg-purple-100', textColor: 'text-purple-600', salaire: '400-800K' },
    destination: 'Abomey',
    periode: '10-12 Avr 2026',
    nb_jours: 3,
    montant: 88000,
    statut: 'SOUMIS' as StatutMission,
    avance: null,
    reliquat: null,
  },
  {
    id: 4,
    numero: 'OM-2026-000792',
    semo: 'SEMO-4520',
    agent: { nom: 'KONE', prenom: 'Jean', initiales: 'KJ', categorie: 'Agent', bgColor: 'bg-amber-100', textColor: 'text-amber-600', salaire: '<400K' },
    destination: 'Djougou',
    periode: '15-16 Avr 2026',
    nb_jours: 2,
    montant: 25000,
    statut: 'BROUILLON' as StatutMission,
    avance: null,
    reliquat: null,
  },
  {
    id: 5,
    numero: 'OM-2026-000793',
    semo: 'SEMO-4521',
    agent: { nom: 'SAOSSI', prenom: 'Paterne', initiales: 'SP', categorie: 'Directeur', bgColor: 'bg-red-100', textColor: 'text-red-600', salaire: '>=800K' },
    destination: 'Bohicon',
    periode: '08-10 Avr 2026',
    nb_jours: 3,
    montant: 75000,
    statut: 'REJETE' as StatutMission,
    avance: null,
    reliquat: null,
    commentaire_rejet: 'Dates incorrectes',
  },
  {
    id: 6,
    numero: 'OM-2026-000794',
    semo: 'SEMO-4522',
    agent: { nom: 'GBAGUIDI', prenom: 'Flora', initiales: 'GF', categorie: 'Cadre', bgColor: 'bg-teal-100', textColor: 'text-teal-600', salaire: '>=800K' },
    destination: 'Cotonou',
    periode: '20-22 Mar 2026',
    nb_jours: 3,
    montant: 55000,
    statut: 'PAYE' as StatutMission,
    avance: { montant: 18333, statut: 'paye' },
    reliquat: { montant: 36667, statut: 'paye' },
  },
])

const currentPage = ref(1)
const totalPages = ref(26)
const totalItems = ref(156)

const deleteModalOpen = ref(false)
const deleteRef = ref('')

const filteredMissions = computed(() => {
  return missions.value.filter((m) => {
    const matchSearch =
      !searchQuery.value ||
      m.numero.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      m.semo.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      `${m.agent.nom} ${m.agent.prenom}`.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      m.destination.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchStatus = !filterStatus.value || m.statut === filterStatus.value
    return matchSearch && matchStatus
  })
})

function getStatutBadge(statut: StatutMission) {
  switch (statut) {
    case 'BROUILLON':
      return { label: 'Brouillon', bg: 'bg-blue-50', text: 'text-blue-600' }
    case 'SOUMIS':
      return { label: 'En attente', bg: 'bg-gold-50', text: 'text-gold-600' }
    case 'VALIDE_N1':
      return { label: 'Valide', bg: 'bg-anatt-50', text: 'text-anatt-600' }
    case 'APPROUVE_DG':
      return { label: 'Approuve DG', bg: 'bg-green-50', text: 'text-green-700' }
    case 'PAYE':
      return { label: 'Paye', bg: 'bg-green-50', text: 'text-green-700' }
    case 'REJETE':
      return { label: 'Rejete', bg: 'bg-red-50', text: 'text-red-600' }
  }
}

function formatMontant(n: number) {
  return new Intl.NumberFormat('fr-FR').format(n)
}

function confirmDelete(ref: string) {
  deleteRef.value = ref
  deleteModalOpen.value = true
}

function handleDelete() {
  deleteModalOpen.value = false
}
</script>

<template>
  <AppLayout title="Ordres de Mission" subtitle="Gestion et suivi des ordres de mission">
    <template #header-actions>
      <router-link
        to="/ordres?attente=1"
        class="px-4 py-2.5 border border-orange-200 bg-orange-50 rounded-xl text-sm font-display font-medium text-orange-700 hover:bg-orange-100 transition-colors flex items-center gap-2"
      >
        <!-- clock icon -->
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        En attente
        <span class="bg-orange-600 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-1">{{ stats.en_attente }}</span>
      </router-link>
      <router-link
        to="/ordres/nouveau"
        class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2"
      >
        <!-- plus icon -->
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvel OM
      </router-link>
    </template>

    <!-- Stats mini -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-gray-800">{{ stats.total }}</p>
        <p class="text-[11px] font-body text-gray-500">Total OMs</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-gray-400">{{ stats.brouillons }}</p>
        <p class="text-[11px] font-body text-gray-500">Brouillons</p>
      </div>
      <div class="bg-white rounded-xl border border-orange-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-orange-500">{{ stats.en_attente }}</p>
        <p class="text-[11px] font-body text-gray-500">En attente</p>
      </div>
      <div class="bg-white rounded-xl border border-green-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-green-600">{{ stats.valides }}</p>
        <p class="text-[11px] font-body text-gray-500">Valides</p>
      </div>
      <div class="bg-white rounded-xl border border-blue-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-blue-600">{{ stats.payes }}</p>
        <p class="text-[11px] font-body text-gray-500">Payes</p>
      </div>
      <div class="bg-white rounded-xl border border-red-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-red-500">{{ stats.rejetes }}</p>
        <p class="text-[11px] font-body text-gray-500">Rejetes</p>
      </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par reference, missionnaire..."
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
          />
        </div>
        <select
          v-model="filterStatus"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        >
          <option value="">Tous les statuts</option>
          <option value="BROUILLON">Brouillon</option>
          <option value="SOUMIS">En attente</option>
          <option value="VALIDE_N1">Valide</option>
          <option value="PAYE">Paye</option>
          <option value="REJETE">Rejete</option>
        </select>
        <select class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500">
          <option value="">Tous types paiement</option>
          <option value="avance">Avance</option>
          <option value="reliquat">Reliquat</option>
        </select>
        <input
          v-model="filterMonth"
          type="month"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        />
      </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider">
              <th class="px-4 py-3"><input type="checkbox" class="rounded border-gray-300" /></th>
              <th class="px-4 py-3">Reference</th>
              <th class="px-4 py-3">Ref. SEMO</th>
              <th class="px-4 py-3">Missionnaire(s)</th>
              <th class="px-4 py-3">Destination</th>
              <th class="px-4 py-3">Periode</th>
              <th class="px-4 py-3">Montant</th>
              <th class="px-4 py-3">Avance / Reliquat</th>
              <th class="px-4 py-3">Statut</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="m in filteredMissions"
              :key="m.id"
              class="hover:bg-anatt-50/30 transition-colors"
            >
              <td class="px-4 py-3"><input type="checkbox" class="rounded border-gray-300" /></td>
              <td class="px-4 py-3">
                <router-link :to="`/ordres/${m.id}`" class="font-display font-semibold text-anatt-500 hover:underline">
                  {{ m.numero }}
                </router-link>
              </td>
              <td class="px-4 py-3">
                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ m.semo }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div :class="[m.agent.bgColor, m.agent.textColor, 'w-8 h-8 rounded-lg flex items-center justify-center text-xs font-display font-bold']">
                    {{ m.agent.initiales }}
                  </div>
                  <div>
                    <p class="font-display font-medium text-gray-800">{{ m.agent.nom }} {{ m.agent.prenom }}</p>
                    <p class="text-[11px] font-body text-gray-400">{{ m.agent.categorie }} &bull; {{ m.agent.salaire }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-gray-700 flex items-center gap-1">
                  <svg class="w-3 h-3 text-anatt-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                  {{ m.destination }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs text-gray-600">{{ m.periode }}</span><br />
                <span class="text-[10px] text-gray-400">{{ m.nb_jours }} jours</span>
              </td>
              <td class="px-4 py-3 font-display font-semibold text-gray-800">{{ formatMontant(m.montant) }} F</td>
              <td class="px-4 py-3">
                <template v-if="m.avance">
                  <div class="space-y-1">
                    <span class="inline-flex items-center text-[10px] px-2 py-0.5 rounded-full" :class="m.avance.statut === 'paye' ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-600'">
                      <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                      Avance: {{ formatMontant(m.avance.montant) }}
                    </span>
                    <br />
                    <span v-if="m.reliquat" class="inline-flex items-center text-[10px] px-2 py-0.5 rounded-full" :class="m.reliquat.statut === 'paye' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-600'">
                      <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                      Reliquat: {{ formatMontant(m.reliquat.montant) }}
                    </span>
                  </div>
                </template>
                <template v-else-if="m.statut === 'SOUMIS'">
                  <span class="text-[11px] text-gray-400 italic font-body">En attente de validation</span>
                </template>
                <template v-else-if="m.statut === 'BROUILLON'">
                  <span class="text-[11px] text-gray-400 italic font-body">Brouillon</span>
                </template>
                <template v-else-if="m.statut === 'REJETE'">
                  <span class="text-[11px] text-red-500 italic font-body">Motif: {{ (m as any).commentaire_rejet }}</span>
                </template>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium"
                  :class="[getStatutBadge(m.statut).bg, getStatutBadge(m.statut).text]"
                >
                  <svg class="w-1.5 h-1.5" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="4"/></svg>
                  {{ getStatutBadge(m.statut).label }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex gap-1">
                  <router-link
                    :to="`/ordres/${m.id}`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600"
                    title="Voir"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </router-link>
                  <router-link
                    v-if="m.statut === 'BROUILLON' || m.statut === 'SOUMIS' || m.statut === 'REJETE'"
                    :to="`/ordres/${m.id}/modifier`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-600"
                    title="Modifier"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </router-link>
                  <button
                    v-if="m.statut === 'BROUILLON'"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500"
                    title="Supprimer"
                    @click="confirmDelete(m.numero)"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                  </button>
                  <router-link
                    v-if="m.statut === 'VALIDE_N1' || m.statut === 'APPROUVE_DG'"
                    to="/paiements"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-green-600"
                    title="Generer paiement"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                  </router-link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm font-body text-gray-500">
          Affichage <span class="font-medium">1-6</span> sur <span class="font-medium">{{ totalItems }}</span>
        </p>
        <div class="flex gap-1">
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 cursor-not-allowed">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg bg-anatt-500 text-white text-sm font-display font-medium">1</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-body">2</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-body">3</button>
          <span class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">...</span>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 text-sm font-body">{{ totalPages }}</button>
          <button class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal suppression -->
    <AppModal :open="deleteModalOpen" @close="deleteModalOpen = false">
      <div class="text-center">
        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <h3 class="text-lg font-display font-bold text-gray-800">Supprimer cet OM ?</h3>
        <p class="text-sm font-body text-gray-500 mt-2">
          L'ordre de mission <strong>{{ deleteRef }}</strong> sera supprime definitivement. Seuls les brouillons peuvent etre supprimes.
        </p>
        <div class="flex gap-3 mt-6">
          <button
            class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
            @click="deleteModalOpen = false"
          >
            Annuler
          </button>
          <button
            class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl text-sm font-display font-medium hover:bg-red-600"
            @click="handleDelete"
          >
            Supprimer
          </button>
        </div>
      </div>
    </AppModal>
  </AppLayout>
</template>
