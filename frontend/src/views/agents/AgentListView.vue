<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import type { Agent } from '@/types'

const router = useRouter()

const searchQuery = ref('')
const filterGrade = ref('')
const filterOperateur = ref('')
const filterStatut = ref('')
const currentPage = ref(1)
const perPage = 5
const showDeleteModal = ref(false)
const agentToDelete = ref<Agent | null>(null)

const agents = ref<(Agent & { grade: string; salaire: number; operateur: string })[]>([
  {
    id: 1, matricule: 'MAT-0045', nom: 'MAGBE', prenom: 'Victorin', email: 'magbe.v@anatt.bj',
    telephone: '229 99 70 74 80', numero_mtn: '22999707480', numero_moov: '22901234567',
    service: 'Direction des Transports Routiers', categorie: 'A1', actif: true,
    grade: 'Cadre', salaire: 850000, operateur: 'MTN'
  },
  {
    id: 2, matricule: 'MAT-0023', nom: 'ADJOVI', prenom: 'Pierre', email: 'adjovi.p@anatt.bj',
    telephone: '229 01 66 33 55', numero_mtn: '', numero_moov: '22901663355',
    service: 'Direction Administrative', categorie: 'C', actif: true,
    grade: 'Agent', salaire: 350000, operateur: 'MOOV'
  },
  {
    id: 3, matricule: 'MAT-0012', nom: 'DOSSA', prenom: 'Marie', email: 'dossa.m@anatt.bj',
    telephone: '229 97 12 34 56', numero_mtn: '22997123456', numero_moov: '',
    service: 'Direction des Ressources Humaines', categorie: 'A2', actif: true,
    grade: 'Chef Service', salaire: 650000, operateur: 'MTN'
  },
  {
    id: 4, matricule: 'MAT-0003', nom: 'SAOSSI', prenom: 'Paterne', email: 'saossi.p@anatt.bj',
    telephone: '229 01 98 76 54', numero_mtn: '', numero_moov: '22901987654',
    service: 'Direction Generale', categorie: 'A1', actif: true,
    grade: 'Directeur', salaire: 1200000, operateur: 'MOOV'
  },
  {
    id: 5, matricule: 'MAT-0067', nom: 'KONE', prenom: 'Jean-Baptiste', email: 'kone.j@anatt.bj',
    telephone: '229 96 45 67 89', numero_mtn: '22996456789', numero_moov: '',
    service: 'Service Technique', categorie: 'C', actif: false,
    grade: 'Agent', salaire: 380000, operateur: 'MTN'
  },
  {
    id: 6, matricule: 'MAT-0078', nom: 'HOUNGBO', prenom: 'Sylvie', email: 'houngbo.s@anatt.bj',
    telephone: '229 97 88 11 22', numero_mtn: '22997881122', numero_moov: '',
    service: 'Direction Financiere', categorie: 'B', actif: true,
    grade: 'Chef Division', salaire: 520000, operateur: 'MTN'
  },
  {
    id: 7, matricule: 'MAT-0091', nom: 'TOSSOU', prenom: 'Eric', email: 'tossou.e@anatt.bj',
    telephone: '229 01 44 55 66', numero_mtn: '', numero_moov: '22901445566',
    service: 'Direction Administrative', categorie: 'C', actif: true,
    grade: 'Agent', salaire: 320000, operateur: 'MOOV'
  }
])

const filteredAgents = computed(() => {
  return agents.value.filter(a => {
    const matchSearch = !searchQuery.value ||
      `${a.nom} ${a.prenom} ${a.matricule}`.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchGrade = !filterGrade.value || a.grade === filterGrade.value
    const matchOp = !filterOperateur.value || a.operateur === filterOperateur.value
    const matchStatut = !filterStatut.value ||
      (filterStatut.value === 'Actif' && a.actif) ||
      (filterStatut.value === 'Inactif' && !a.actif)
    return matchSearch && matchGrade && matchOp && matchStatut
  })
})

const totalPages = computed(() => Math.ceil(filteredAgents.value.length / perPage))
const paginatedAgents = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filteredAgents.value.slice(start, start + perPage)
})

const stats = computed(() => ({
  total: agents.value.length,
  actifs: agents.value.filter(a => a.actif).length,
  mtn: agents.value.filter(a => a.operateur === 'MTN').length,
  moov: agents.value.filter(a => a.operateur === 'MOOV').length
}))

function getInitials(nom: string, prenom: string) {
  return `${nom[0] || ''}${prenom[0] || ''}`.toUpperCase()
}

const initialsColors = [
  'bg-blue-100 text-blue-600',
  'bg-green-100 text-green-600',
  'bg-purple-100 text-purple-600',
  'bg-red-100 text-red-600',
  'bg-amber-100 text-amber-600',
  'bg-pink-100 text-pink-600',
  'bg-teal-100 text-teal-600'
]

function getInitialsColor(id: number) {
  return initialsColors[id % initialsColors.length]
}

function formatSalaire(montant: number) {
  return montant.toLocaleString('fr-FR')
}

function confirmDelete(agent: Agent & { grade: string; salaire: number; operateur: string }) {
  agentToDelete.value = agent
  showDeleteModal.value = true
}

function handleDelete() {
  if (agentToDelete.value) {
    agents.value = agents.value.filter(a => a.id !== agentToDelete.value!.id)
  }
  showDeleteModal.value = false
  agentToDelete.value = null
}
</script>

<template>
  <AppLayout title="Gestion du Personnel" :subtitle="`${stats.total} agents enregistres`">
    <template #header-actions>
      <router-link
        to="/agents/import"
        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2"
      >
        <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h2"/><path d="M8 17h2"/><path d="M14 13h2"/><path d="M14 17h2"/></svg>
        Importer Excel
      </router-link>
      <router-link
        to="/agents/nouveau"
        class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Ajouter
      </router-link>
    </template>

    <!-- Filtres -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par nom, matricule..."
            class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
          >
        </div>
        <select
          v-model="filterGrade"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        >
          <option value="">Tous les grades</option>
          <option>Directeur</option>
          <option>Chef Service</option>
          <option>Chef Division</option>
          <option>Cadre</option>
          <option>Agent</option>
        </select>
        <select
          v-model="filterOperateur"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        >
          <option value="">Tous operateurs</option>
          <option>MTN</option>
          <option>MOOV</option>
        </select>
        <select
          v-model="filterStatut"
          class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500"
        >
          <option value="">Tous statuts</option>
          <option>Actif</option>
          <option>Inactif</option>
        </select>
      </div>
    </div>

    <!-- Stats mini -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-gray-800">{{ stats.total }}</p>
        <p class="text-xs font-body text-gray-500">Total agents</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-anatt-500">{{ stats.actifs }}</p>
        <p class="text-xs font-body text-gray-500">Actifs</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-yellow-600">{{ stats.mtn }}</p>
        <p class="text-xs font-body text-gray-500">MTN MoMo</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-blue-600">{{ stats.moov }}</p>
        <p class="text-xs font-body text-gray-500">MOOV Flooz</p>
      </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3"><input type="checkbox" class="rounded border-gray-300"></th>
              <th class="px-5 py-3">Agent</th>
              <th class="px-5 py-3">Matricule</th>
              <th class="px-5 py-3">Grade</th>
              <th class="px-5 py-3">Salaire</th>
              <th class="px-5 py-3">Operateur</th>
              <th class="px-5 py-3">Telephone</th>
              <th class="px-5 py-3">Statut</th>
              <th class="px-5 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr
              v-for="agent in paginatedAgents"
              :key="agent.id"
              class="hover:bg-anatt-50/30 transition-colors"
            >
              <td class="px-5 py-3"><input type="checkbox" class="rounded border-gray-300"></td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-display font-bold"
                    :class="getInitialsColor(agent.id)"
                  >
                    {{ getInitials(agent.nom, agent.prenom) }}
                  </div>
                  <div>
                    <p class="font-display font-medium text-gray-800">{{ agent.nom }} {{ agent.prenom }}</p>
                    <p class="text-[11px] font-body text-gray-500">{{ agent.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ agent.matricule }}</span>
              </td>
              <td class="px-5 py-3 font-body">{{ agent.grade }}</td>
              <td class="px-5 py-3 font-display font-semibold">{{ formatSalaire(agent.salaire) }}</td>
              <td class="px-5 py-3">
                <span
                  class="text-xs font-display font-bold px-2 py-1 rounded-lg"
                  :class="agent.operateur === 'MTN' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'"
                >
                  {{ agent.operateur }}
                </span>
              </td>
              <td class="px-5 py-3 font-mono text-xs">{{ agent.telephone }}</td>
              <td class="px-5 py-3">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium"
                  :class="agent.actif ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                >
                  <svg class="w-1.5 h-1.5 fill-current" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  {{ agent.actif ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="px-5 py-3">
                <div class="flex gap-1">
                  <router-link
                    :to="`/agents/${agent.id}`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600"
                    title="Voir"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </router-link>
                  <router-link
                    :to="`/agents/${agent.id}/modifier`"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-600"
                    title="Modifier"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                  </router-link>
                  <button
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500"
                    title="Supprimer"
                    @click="confirmDelete(agent)"
                  >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
        <p class="text-sm font-body text-gray-500">
          Affichage {{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, filteredAgents.length) }} sur {{ filteredAgents.length }} agents
        </p>
        <div class="flex items-center gap-1">
          <button
            class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center text-gray-400 disabled:opacity-50"
            :disabled="currentPage === 1"
            @click="currentPage--"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button
            v-for="page in totalPages"
            :key="page"
            class="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-display font-medium"
            :class="page === currentPage ? 'bg-anatt-500 text-white' : 'border border-gray-200 text-gray-700 hover:bg-gray-50'"
            @click="currentPage = page"
          >
            {{ page }}
          </button>
          <button
            class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center text-gray-700 disabled:opacity-50"
            :disabled="currentPage === totalPages"
            @click="currentPage++"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Suppression -->
    <teleport to="body">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      >
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl">
          <div class="text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full mx-auto mb-4 flex items-center justify-center">
              <svg class="w-6 h-6 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </div>
            <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Confirmer la suppression</h3>
            <p class="text-sm font-body text-gray-500 mb-6">
              Voulez-vous vraiment supprimer
              <strong>{{ agentToDelete?.nom }} {{ agentToDelete?.prenom }}</strong> ?
              Cette action est irreversible.
            </p>
            <div class="flex gap-3">
              <button
                class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
                @click="showDeleteModal = false"
              >
                Annuler
              </button>
              <button
                class="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl text-sm font-display font-medium hover:bg-red-700"
                @click="handleDelete"
              >
                Supprimer
              </button>
            </div>
          </div>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>
