<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { rapportsService } from '@/services/rapports'
import type { RapportMensuel } from '@/types'

const synthese = ref<RapportMensuel | null>(null)
const loading = ref(true)
const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

const liens = [
  { to: '/rapports/mensuel', titre: 'État mensuel', desc: 'Synthèse des versements confirmés du mois' },
  { to: '/rapports/agents', titre: 'États par dimension', desc: 'Par agent, opérateur, zone, imputation' },
  { to: '/rapports/export', titre: 'Export personnalisé', desc: 'Excel / PDF par période' },
]

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

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <router-link v-for="l in liens" :key="l.to" :to="l.to">
        <AppCard hoverable>
          <h3 class="font-display font-bold text-gray-800 mb-1">{{ l.titre }}</h3>
          <p class="text-sm font-body text-gray-500">{{ l.desc }}</p>
        </AppCard>
      </router-link>
    </div>
  </AppLayout>
</template>
