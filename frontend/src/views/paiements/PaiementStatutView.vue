<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { paiementsService } from '@/services/paiements'
import type { Operateur } from '@/types'

interface Ligne { operateur: string; statut: string; total: number; montant_total: number }

const lignes = ref<Ligne[]>([])
const loading = ref(true)

const fmt = (v: number) => Number(v).toLocaleString('fr-FR')

onMounted(async () => {
  try {
    lignes.value = await paiementsService.statuts() as Ligne[]
  } finally {
    loading.value = false
  }
})

const total = computed(() => ({
  versements: lignes.value.reduce((s, l) => s + Number(l.total), 0),
  montant: lignes.value.reduce((s, l) => s + Number(l.montant_total), 0),
  confirmes: lignes.value.filter((l) => l.statut === 'CONFIRME').reduce((s, l) => s + Number(l.total), 0),
}))
</script>

<template>
  <AppLayout title="Statuts des versements" subtitle="Synthèse par opérateur et statut">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <AppCard><p class="text-2xl font-display font-bold text-gray-800">{{ total.versements }}</p><p class="text-xs font-body text-gray-500">Versements</p></AppCard>
      <AppCard><p class="text-2xl font-display font-bold text-anatt-600">{{ fmt(total.montant) }}</p><p class="text-xs font-body text-gray-500">Montant total (FCFA)</p></AppCard>
      <AppCard><p class="text-2xl font-display font-bold text-green-600">{{ total.confirmes }}</p><p class="text-xs font-body text-gray-500">Versements confirmés</p></AppCard>
    </div>

    <AppCard>
      <p v-if="loading" class="text-center text-gray-400 font-body py-6">Chargement…</p>
      <p v-else-if="!lignes.length" class="text-center text-gray-400 font-body py-6">Aucun versement.</p>
      <table v-else class="w-full text-sm font-body">
        <thead>
          <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
            <th class="py-2">Opérateur</th>
            <th>Statut</th>
            <th class="text-right">Nombre</th>
            <th class="text-right">Montant</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l, i) in lignes" :key="i" class="border-b border-gray-50">
            <td class="py-2"><OperateurBadge :operateur="(l.operateur as Operateur)" /></td>
            <td><PaiementStatusBadge :statut="l.statut" /></td>
            <td class="text-right font-semibold">{{ l.total }}</td>
            <td class="text-right">{{ fmt(l.montant_total) }}</td>
          </tr>
        </tbody>
      </table>
    </AppCard>
  </AppLayout>
</template>
