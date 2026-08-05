<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import MissionStatusBadge from '@/components/missions/MissionStatusBadge.vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import { rapportsService } from '@/services/rapports'
import { ordresService } from '@/services/ordres'
import type { RapportMensuel, OrdreMission } from '@/types'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const loading = ref(true)
const rapport = ref<RapportMensuel | null>(null)
const ordres = ref<OrdreMission[]>([])
const totalOrdres = ref(0)

const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

onMounted(async () => {
  try {
    const mois = new Date().toISOString().slice(0, 7)
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
  { label: 'Ordres de mission actifs', value: String(totalOrdres.value), iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'Versements confirmés (mois)', value: String(rapport.value?.synthese.nb_versements ?? 0), iconBg: 'bg-anatt-100', iconColor: 'text-anatt-600' },
  { label: 'Montant payé (mois)', value: fmt(rapport.value?.synthese.montant_total ?? 0), iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
  { label: 'Opérateurs actifs', value: String(rapport.value?.par_operateur.length ?? 0), iconBg: 'bg-gold-100', iconColor: 'text-gold-600' },
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
        <AppCard v-for="s in stats" :key="s.label">
          <div :class="[s.iconBg, 'w-10 h-10 rounded-xl mb-3']"></div>
          <p class="text-2xl font-display font-bold text-gray-800">{{ s.value }}</p>
          <p class="text-xs font-body text-gray-500">{{ s.label }}</p>
        </AppCard>
      </div>

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
