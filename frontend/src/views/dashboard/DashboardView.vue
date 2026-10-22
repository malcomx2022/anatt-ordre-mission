<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import StatCard from '@/components/dashboard/StatCard.vue'
import QuickActions from '@/components/dashboard/QuickActions.vue'
import MissionStatusBadge from '@/components/missions/MissionStatusBadge.vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import { rapportsService } from '@/services/rapports'
import { ordresService } from '@/services/ordres'
import type { RapportMensuel, OrdreMission } from '@/types'
import { moisLocal } from '@/utils/benin'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const loading = ref(true)
const rapport = ref<RapportMensuel | null>(null)
const ordres = ref<OrdreMission[]>([])
const totalOrdres = ref(0)

const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

onMounted(async () => {
  try {
    const mois = moisLocal()
    const [r, o] = await Promise.all([
      rapportsService.mensuel(mois),
      // actifs : exclut les OM rejetés/annulés — KPI de volume + activité récente.
      ordresService.list({ per_page: 5, actifs: true }),
    ])
    rapport.value = r
    ordres.value = o.items
    totalOrdres.value = o.meta.total
  } finally {
    loading.value = false
  }
})

const stats = computed(() => [
  { label: 'Ordres de mission actifs', value: String(totalOrdres.value), iconBg: 'bg-blue-100', iconColor: 'text-blue-600', icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
  { label: 'Versements confirmés (mois)', value: String(rapport.value?.synthese.nb_versements ?? 0), iconBg: 'bg-anatt-100', iconColor: 'text-anatt-600', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { label: 'Montant payé (mois)', value: fmt(rapport.value?.synthese.montant_total ?? 0), iconBg: 'bg-purple-100', iconColor: 'text-purple-600', icon: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0z' },
  { label: 'Opérateurs actifs', value: String(rapport.value?.par_operateur.length ?? 0), iconBg: 'bg-gold-100', iconColor: 'text-gold-600', icon: 'M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3' },
])

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: (rapport.value?.par_operateur ?? []).map((o) => o.operateur),
  datasets: [{
    label: 'Montant confirmé (FCFA)',
    backgroundColor: ['#D4A843', '#2563EB'],
    borderRadius: 8,
    data: (rapport.value?.par_operateur ?? []).map((o) => Number(o.montant_total)),
  }],
}))

const chartOptions: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}
</script>

<template>
  <AppLayout title="Tableau de bord" subtitle="Vue d'ensemble du mois en cours">
    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else class="space-y-6">
      <!-- KPIs -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard v-for="s in stats" :key="s.label" :label="s.label" :value="s.value" :icon-bg="s.iconBg" :icon-color="s.iconColor">
          <template #icon>
            <svg :class="[s.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="s.icon" /></svg>
          </template>
        </StatCard>
      </div>

      <!-- Actions rapides (filtrées par permission) -->
      <QuickActions />

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Graphe par opérateur -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-4">Paiements confirmés par opérateur</h3>
          <div class="h-64">
            <Bar :data="chartData" :options="chartOptions" />
          </div>
        </AppCard>

        <!-- OM récents -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-4">Ordres de mission récents</h3>
          <table class="w-full text-sm font-body">
            <tbody>
              <tr v-if="!ordres.length"><td class="py-6 text-center text-gray-400">Aucun OM.</td></tr>
              <tr v-for="o in ordres" :key="o.id" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer" @click="$router.push(`/ordres/${o.id}`)">
                <td class="py-2 font-mono text-xs text-anatt-600">{{ o.reference_semo }}</td>
                <td class="py-2">{{ o.agent ? `${o.agent.nom} ${o.agent.prenoms}` : '—' }}</td>
                <td class="py-2 text-right"><MissionStatusBadge :statut="o.statut" /></td>
              </tr>
            </tbody>
          </table>
        </AppCard>
      </div>
    </div>
  </AppLayout>
</template>
