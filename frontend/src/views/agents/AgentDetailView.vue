<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import { agentsService } from '@/services/agents'
import { ordresService } from '@/services/ordres'
import { useAuth } from '@/composables/useAuth'
import { categorieLabel } from '@/types'
import type { Agent, OrdreMission } from '@/types'

const route = useRoute()
const id = Number(route.params.id)
const { canManageAgents } = useAuth()

const loading = ref(true)
const agent = ref<Agent | null>(null)
const ordres = ref<OrdreMission[]>([])

onMounted(async () => {
  try {
    agent.value = await agentsService.get(id)
    const { items } = await ordresService.list({ agent_id: id, per_page: 100 })
    ordres.value = items
  } finally {
    loading.value = false
  }
})

const stats = computed(() => ({
  total: ordres.value.length,
  montant: ordres.value.reduce((s, o) => s + Number(o.montant_total), 0),
}))

const fmt = (n: number) => n.toLocaleString('fr-FR')

function statutVariant(s: string) {
  if (s === 'VALIDE' || s === 'SOLDE') return 'success'
  if (s === 'REJETE' || s === 'ANNULE') return 'danger'
  if (s === 'IMPORTE' || s === 'EN_ATTENTE') return 'warning'
  return 'info'
}
</script>

<template>
  <AppLayout title="Fiche agent" :subtitle="agent ? `${agent.matricule} — ${agent.nom} ${agent.prenoms}` : 'Chargement…'">
    <template #header-actions>
      <router-link to="/agents" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Personnel</router-link>
      <router-link v-if="agent && canManageAgents()" :to="`/agents/${agent.id}/modifier`" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600">Modifier</router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else-if="agent" class="space-y-6">
      <!-- En-tête profil -->
      <AppCard>
        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
          <div class="w-20 h-20 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-2xl font-display font-bold text-white">
            {{ agent.nom[0] }}{{ agent.prenoms[0] }}
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-1">
              <h2 class="text-xl font-display font-bold text-gray-800">{{ agent.nom }} {{ agent.prenoms }}</h2>
              <AppBadge :variant="agent.actif ? 'success' : 'danger'">{{ agent.actif ? 'Actif' : 'Inactif' }}</AppBadge>
              <AppBadge variant="info">{{ categorieLabel(agent.categorie) }}</AppBadge>
            </div>
            <p class="text-sm font-body text-gray-500 mb-3">{{ agent.grade || '—' }}</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm font-body text-gray-600">
              <span>Matricule : <strong>{{ agent.matricule }}</strong></span>
              <span>IFU : {{ agent.ifu || '—' }}</span>
              <span>Indice : {{ agent.indice ?? '—' }}</span>
              <span>Salaire brut : {{ agent.salaire_brut ? fmt(agent.salaire_brut) + ' FCFA' : '—' }}</span>
              <span>Téléphone : <span class="font-mono">{{ agent.telephone }}</span></span>
              <span>Opérateur :
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="agent.operateur === 'MTN' ? 'bg-gold-50 text-gold-600' : 'bg-blue-50 text-blue-600'">{{ agent.operateur }}</span>
              </span>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div class="text-center px-4 py-3 bg-gray-50 rounded-xl">
              <p class="text-2xl font-display font-bold text-gray-800">{{ stats.total }}</p>
              <p class="text-[11px] font-body text-gray-500">Ordres de mission</p>
            </div>
            <div class="text-center px-4 py-3 bg-anatt-50 rounded-xl">
              <p class="text-2xl font-display font-bold text-anatt-700">{{ fmt(stats.montant) }}</p>
              <p class="text-[11px] font-body text-anatt-600">Total FCFA</p>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Ordres de mission de l'agent -->
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-4">Ordres de mission</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm font-body">
            <thead>
              <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                <th class="py-2">Réf. SEMO</th>
                <th>Destination</th>
                <th>Rôle</th>
                <th>Jours</th>
                <th class="text-right">Total</th>
                <th class="text-right">Avance</th>
                <th class="text-right">Reliquat</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!ordres.length"><td colspan="8" class="py-8 text-center text-gray-400">Aucun ordre de mission.</td></tr>
              <tr v-for="o in ordres" :key="o.id" class="border-b border-gray-50 hover:bg-gray-50">
                <td class="py-2">
                  <router-link :to="`/ordres/${o.id}`" class="font-mono text-xs text-anatt-600 hover:underline">{{ o.reference_semo }}</router-link>
                </td>
                <td>{{ o.mission?.destination ?? '—' }}</td>
                <td>{{ o.role }}</td>
                <td>{{ o.nb_jours }}</td>
                <td class="text-right font-semibold">{{ fmt(o.montant_total) }}</td>
                <td class="text-right text-gray-500">{{ fmt(o.avance) }}</td>
                <td class="text-right text-gray-500">{{ fmt(o.reliquat) }}</td>
                <td><AppBadge :variant="statutVariant(o.statut)">{{ o.statut }}</AppBadge></td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>
  </AppLayout>
</template>
