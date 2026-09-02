<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { rapportsService } from '@/services/rapports'
import type { RapportTropPercus } from '@/types'

const loading = ref(true)
const rapport = ref<RapportTropPercus | null>(null)

const fmt = (v: number) => (v ?? 0).toLocaleString('fr-FR')

// Regroupe par opérateur : { operateur: { A_RECOUVRER: {nb,montant}, RECOUVRE: {…} } }
const parOperateur = computed(() => {
  const map = new Map<string, { a_recouvrer: number; recouvre: number }>()
  for (const l of rapport.value?.par_operateur ?? []) {
    const e = map.get(l.operateur) ?? { a_recouvrer: 0, recouvre: 0 }
    if (l.statut === 'A_RECOUVRER') e.a_recouvrer += l.montant
    else if (l.statut === 'RECOUVRE') e.recouvre += l.montant
    map.set(l.operateur, e)
  }
  return [...map.entries()].map(([operateur, m]) => ({ operateur, ...m }))
})

async function load() {
  loading.value = true
  try {
    rapport.value = await rapportsService.tropPercus()
  } catch {
    rapport.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <AppLayout title="Trop-perçus" subtitle="Régularisations — reliquats négatifs à recouvrer (retenue sur salaire)">
    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else-if="rapport" class="space-y-6">
      <!-- KPIs -->
      <div class="grid sm:grid-cols-3 gap-4">
        <AppCard>
          <div class="text-[11px] text-gray-400 uppercase font-body">À recouvrer</div>
          <div class="font-display text-3xl font-extrabold text-red-600 mt-1">{{ fmt(rapport.a_recouvrer.montant) }}</div>
          <div class="text-xs text-gray-500 font-body mt-1">{{ rapport.a_recouvrer.nb }} versement(s) · FCFA</div>
        </AppCard>
        <AppCard>
          <div class="text-[11px] text-gray-400 uppercase font-body">Recouvré</div>
          <div class="font-display text-3xl font-extrabold text-anatt-600 mt-1">{{ fmt(rapport.recouvre.montant) }}</div>
          <div class="text-xs text-gray-500 font-body mt-1">{{ rapport.recouvre.nb }} versement(s) · FCFA</div>
        </AppCard>
        <AppCard>
          <div class="text-[11px] text-gray-400 uppercase font-body">Total trop-perçus</div>
          <div class="font-display text-3xl font-extrabold text-gray-800 mt-1">{{ fmt(rapport.total.montant) }}</div>
          <div class="text-xs text-gray-500 font-body mt-1">{{ rapport.total.nb }} versement(s) · FCFA</div>
        </AppCard>
      </div>

      <!-- Ventilation par opérateur -->
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-3">Par opérateur</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm font-body">
            <thead>
              <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                <th class="py-2">Opérateur</th>
                <th class="text-right">À recouvrer (FCFA)</th>
                <th class="text-right">Recouvré (FCFA)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!parOperateur.length"><td colspan="3" class="py-8 text-center text-gray-400">Aucun trop-perçu.</td></tr>
              <tr v-for="row in parOperateur" :key="row.operateur" class="border-b border-gray-50 hover:bg-gray-50">
                <td class="py-2 font-display">{{ row.operateur }}</td>
                <td class="text-right font-semibold text-red-600">{{ fmt(row.a_recouvrer) }}</td>
                <td class="text-right text-anatt-600">{{ fmt(row.recouvre) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="text-[12px] text-gray-400 font-body mt-3">
          Les trop-perçus sont récupérés par <strong>retenue sur salaire</strong> (hors Mobile Money).
          Le détail ligne à ligne et le document de retenue sont sur l’écran
          <router-link to="/paiements/regularisations" class="text-anatt-600 hover:underline">Régularisations</router-link>.
        </p>
      </AppCard>
    </div>
  </AppLayout>
</template>
