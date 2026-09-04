<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale,
} from 'chart.js'
import type { ChartData, ChartOptions } from 'chart.js'
import { rapportsService } from '@/services/rapports'
import type { RapportMensuel } from '@/types'

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const synthese = ref<RapportMensuel | null>(null)
const loading = ref(true)
const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

const liens = [
  { to: '/rapports/mensuel', titre: 'État mensuel', desc: 'Synthèse des versements confirmés du mois' },
  { to: '/rapports/agents', titre: 'États par dimension', desc: 'Par agent, opérateur, zone, imputation' },
  { to: '/rapports/trop-percus', titre: 'Trop-perçus', desc: 'Régularisations à recouvrer / recouvrées' },
  { to: '/rapports/export', titre: 'Export personnalisé', desc: 'Excel / PDF par période' },
]

const aDesOperateurs = computed(() => (synthese.value?.par_operateur.length ?? 0) > 0)

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: (synthese.value?.par_operateur ?? []).map((o) => o.operateur),
  datasets: [{
    label: 'Montant confirmé (FCFA)',
    backgroundColor: ['#D4A843', '#2563EB', '#1D9E4F'],
    borderRadius: 8,
    data: (synthese.value?.par_operateur ?? []).map((o) => Number(o.montant_total)),
  }],
}))

const chartOptions: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}

onMounted(async () => {
  try {
    synthese.value = await rapportsService.mensuel(new Date().toISOString().slice(0, 7))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Rapports & États" subtitle="Tableau de bord analytique">
    <div v-if="!loading && synthese" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <AppCard><p class="text-2xl font-display font-bold text-gray-800">{{ synthese.synthese.nb_versements }}</p><p class="text-xs font-body text-gray-500">Versements confirmés (mois)</p></AppCard>
      <AppCard><p class="text-2xl font-display font-bold text-anatt-600">{{ fmt(synthese.synthese.montant_total) }}</p><p class="text-xs font-body text-gray-500">Montant total (FCFA)</p></AppCard>
      <AppCard><p class="text-sm font-display font-bold text-gray-700">{{ synthese.periode.debut }} → {{ synthese.periode.fin }}</p><p class="text-xs font-body text-gray-500">Période courante</p></AppCard>
    </div>

    <!-- Graphe : paiements confirmés par opérateur (mois en cours) -->
    <AppCard v-if="!loading && synthese" class="mb-6">
      <h3 class="font-display font-bold text-gray-800 mb-4">Paiements confirmés par opérateur (mois en cours)</h3>
      <div v-if="aDesOperateurs" class="h-64">
        <Bar :data="chartData" :options="chartOptions" />
      </div>
      <p v-else class="text-center text-gray-400 font-body py-10">Aucun paiement confirmé ce mois-ci.</p>
    </AppCard>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
      <router-link v-for="l in liens" :key="l.to" :to="l.to">
        <AppCard hoverable class="h-full">
          <h3 class="font-display font-bold text-gray-800 mb-1">{{ l.titre }}</h3>
          <p class="text-sm font-body text-gray-500">{{ l.desc }}</p>
        </AppCard>
      </router-link>
    </div>
  </AppLayout>
</template>
