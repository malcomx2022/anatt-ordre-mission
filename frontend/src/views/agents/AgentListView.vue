<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import AppModal from '@/components/shared/AppModal.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { agentsService, type AgentStats } from '@/services/agents'
import { useSearch } from '@/composables/useSearch'
import { useToast } from '@/composables/useToast'
import { useAuth } from '@/composables/useAuth'
import type { Agent } from '@/types'

const { query: searchQuery, debouncedQuery } = useSearch(300)
const toast = useToast()
const { canManageAgents } = useAuth()

const filterCategorie = ref('')
const filterOperateur = ref('')
const filterStatut = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const loading = ref(true)
const showDeleteModal = ref(false)
const agentToDelete = ref<Agent | null>(null)
const agents = ref<Agent[]>([])

// Compteurs GLOBAUX (tout le jeu filtré), servis par l'API — pas un décompte de
// la seule page affichée (qui variait à chaque pagination).
const stats = ref<AgentStats>({ total: 0, actifs: 0, inactifs: 0, mtn: 0, moov: 0 })

function filtresActuels() {
  return {
    search: debouncedQuery.value || undefined,
    categorie: filterCategorie.value || undefined,
    operateur: filterOperateur.value || undefined,
    actif: filterStatut.value === 'Actif' ? true : filterStatut.value === 'Inactif' ? false : undefined,
  }
}

async function loadAgents() {
  loading.value = true
  try {
    const [page, compteurs] = await Promise.all([
      agentsService.list({ page: currentPage.value, ...filtresActuels() }),
      agentsService.stats(filtresActuels()),
    ])
    agents.value = page.items
    currentPage.value = page.meta.current_page
    totalPages.value = page.meta.last_page
    totalItems.value = page.meta.total
    stats.value = compteurs
  } finally {
    loading.value = false
  }
}

watch([debouncedQuery, filterCategorie, filterOperateur, filterStatut], () => {
  currentPage.value = 1
  loadAgents()
})

function handlePageChange(page: number) {
  currentPage.value = page
  loadAgents()
}

onMounted(loadAgents)

function initiales(a: Agent) {
  return `${a.nom?.[0] ?? ''}${a.prenoms?.[0] ?? ''}`.toUpperCase()
}

const couleurs = [
  'bg-blue-100 text-blue-600', 'bg-green-100 text-green-600',
  'bg-purple-100 text-purple-600', 'bg-amber-100 text-amber-600',
  'bg-pink-100 text-pink-600', 'bg-teal-100 text-teal-600',
]

function confirmDelete(agent: Agent) {
  agentToDelete.value = agent
  showDeleteModal.value = true
}

async function handleDelete() {
  if (agentToDelete.value) {
    try {
      await agentsService.remove(agentToDelete.value.id)
      toast.success('Agent désactivé.')
      loadAgents()
    } catch {
      /* géré par l'intercepteur */
    }
  }
  showDeleteModal.value = false
  agentToDelete.value = null
}
</script>

<template>
  <AppLayout title="Gestion du Personnel" :subtitle="`${stats.total} agent(s) enregistré(s)`">
    <template #header-actions>
      <router-link
        v-if="canManageAgents()"
        to="/agents/import"
        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 transition-colors"
      >
        Importer Excel
      </router-link>
      <router-link
        v-if="canManageAgents()"
        to="/agents/nouveau"
        class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors"
      >
        + Ajouter
      </router-link>
    </template>

    <!-- Filtres -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-2">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Rechercher par nom, matricule, IFU..."
            class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
          >
        </div>
        <select v-model="filterCategorie" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500">
          <option value="">Toutes catégories</option>
          <option value="ORDRE_1">ORDRE_1</option>
          <option value="ORDRE_2">ORDRE_2</option>
          <option value="ORDRE_3">ORDRE_3</option>
        </select>
        <select v-model="filterOperateur" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500">
          <option value="">Tous opérateurs</option>
          <option value="MTN">MTN</option>
          <option value="MOOV">MOOV</option>
        </select>
        <select v-model="filterStatut" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500">
          <option value="">Tous statuts</option>
          <option>Actif</option>
          <option>Inactif</option>
        </select>
      </div>
    </div>

    <!-- Stats -->
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
        <p class="text-2xl font-display font-bold text-gold-600">{{ stats.mtn }}</p>
        <p class="text-xs font-body text-gray-500">MTN</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
        <p class="text-2xl font-display font-bold text-blue-600">{{ stats.moov }}</p>
        <p class="text-xs font-body text-gray-500">MOOV</p>
      </div>
    </div>

    <!-- Tableau -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Agent</th>
              <th class="px-5 py-3">Matricule</th>
              <th class="px-5 py-3">Grade</th>
              <th class="px-5 py-3">Catégorie</th>
              <th class="px-5 py-3">Opérateur</th>
              <th class="px-5 py-3">Téléphone</th>
              <th class="px-5 py-3">Statut</th>
              <th class="px-5 py-3">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="8" class="px-5 py-10 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!agents.length"><td colspan="8" class="px-5 py-10 text-center text-gray-400 font-body">Aucun agent.</td></tr>
            <tr v-for="(agent, i) in agents" :key="agent.id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-display font-bold" :class="couleurs[i % couleurs.length]">
                    {{ initiales(agent) }}
                  </div>
                  <div>
                    <p class="font-display font-medium text-gray-800">{{ agent.nom }} {{ agent.prenoms }}</p>
                    <p class="text-[11px] font-body text-gray-500">{{ agent.ifu || '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3"><span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ agent.matricule }}</span></td>
              <td class="px-5 py-3 font-body">{{ agent.grade || '—' }}</td>
              <td class="px-5 py-3 font-display font-semibold text-anatt-600">{{ agent.categorie }}</td>
              <td class="px-5 py-3">
                <span class="text-xs font-display font-bold px-2 py-1 rounded-lg" :class="agent.operateur === 'MTN' ? 'bg-gold-50 text-gold-600' : 'bg-blue-50 text-blue-600'">
                  {{ agent.operateur }}
                </span>
              </td>
              <td class="px-5 py-3 font-mono text-xs">{{ agent.telephone }}</td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs rounded-lg font-display font-medium" :class="agent.actif ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                  {{ agent.actif ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="px-5 py-3">
                <div class="flex gap-1">
                  <router-link :to="`/agents/${agent.id}`" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600" title="Voir">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  </router-link>
                  <router-link v-if="canManageAgents()" :to="`/agents/${agent.id}/modifier`" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-600" title="Modifier">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                  </router-link>
                  <button v-if="canManageAgents()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500" title="Désactiver" @click="confirmDelete(agent)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="handlePageChange" />
    </div>

    <AppModal :show="showDeleteModal" title="Confirmer la désactivation" size="sm" @close="showDeleteModal = false">
      <p class="text-sm font-body text-gray-600">
        Désactiver <strong>{{ agentToDelete?.nom }} {{ agentToDelete?.prenoms }}</strong> ?
      </p>
      <template #footer>
        <AppButton variant="ghost" @click="showDeleteModal = false">Annuler</AppButton>
        <AppButton variant="danger" @click="handleDelete">Désactiver</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
