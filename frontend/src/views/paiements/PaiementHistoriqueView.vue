<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppTable from '@/components/shared/AppTable.vue'
import AppPagination from '@/components/shared/AppPagination.vue'

const periode = ref('')
const operateur = ref('')

const paiements = ref([
  { id: 1, ref: 'OM-2026-0032', agent: 'Jean KOUASSI', operateur: 'MOOV', montant: 75000, statut: 'SUCCES', date: '06/04/2026' },
  { id: 2, ref: 'OM-2026-0033', agent: 'Sylvie ADJOVI', operateur: 'MTN', montant: 125000, statut: 'SUCCES', date: '06/04/2026' },
  { id: 3, ref: 'OM-2026-0034', agent: 'Marc TOSSOU', operateur: 'MOOV', montant: 50000, statut: 'ECHEC', date: '05/04/2026' },
  { id: 4, ref: 'OM-2026-0035', agent: 'Fatima AGOSSA', operateur: 'MTN', montant: 100000, statut: 'SUCCES', date: '04/04/2026' },
  { id: 5, ref: 'OM-2026-0030', agent: 'Olivier KPADE', operateur: 'MTN', montant: 60000, statut: 'EN_ATTENTE', date: '03/04/2026' },
])

const statutVariant: Record<string, 'success' | 'danger' | 'warning' | 'info'> = {
  SUCCES: 'success', ECHEC: 'danger', EN_ATTENTE: 'warning', INITIE: 'info',
}
</script>

<template>
  <AppLayout title="Historique des paiements" subtitle="Historique complet des virements par periode">
    <div class="space-y-6">
      <!-- Filters -->
      <div class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Periode</label>
            <input v-model="periode" type="month" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Operateur</label>
            <select v-model="operateur" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
              <option value="">Tous</option>
              <option>MOOV</option>
              <option>MTN</option>
            </select>
          </div>
          <div class="flex items-end">
            <AppButton variant="outline" class="w-full">Filtrer</AppButton>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center"><p class="text-2xl font-display font-bold text-gray-800">{{ paiements.length }}</p><p class="text-xs text-gray-500 font-body">Total</p></div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center"><p class="text-2xl font-display font-bold text-green-600">{{ paiements.filter(p => p.statut === 'SUCCES').length }}</p><p class="text-xs text-gray-500 font-body">Reussis</p></div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center"><p class="text-2xl font-display font-bold text-red-600">{{ paiements.filter(p => p.statut === 'ECHEC').length }}</p><p class="text-xs text-gray-500 font-body">Echoues</p></div>
        <div class="bg-white rounded-xl border border-gray-100 p-4 text-center"><p class="text-2xl font-display font-bold text-anatt-600">{{ paiements.reduce((s, p) => s + p.montant, 0).toLocaleString() }}</p><p class="text-xs text-gray-500 font-body">Montant total FCFA</p></div>
      </div>

      <!-- Table -->
      <AppTable>
        <template #header>
          <th class="px-5 py-3">Reference</th>
          <th class="px-5 py-3">Agent</th>
          <th class="px-5 py-3">Operateur</th>
          <th class="px-5 py-3">Montant</th>
          <th class="px-5 py-3">Statut</th>
          <th class="px-5 py-3">Date</th>
        </template>
        <template #body>
          <tr v-for="p in paiements" :key="p.id" class="hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-4 text-sm font-body font-medium">{{ p.ref }}</td>
            <td class="px-5 py-4 text-sm font-body">{{ p.agent }}</td>
            <td class="px-5 py-4"><AppBadge :variant="p.operateur === 'MTN' ? 'warning' : 'info'">{{ p.operateur }}</AppBadge></td>
            <td class="px-5 py-4 text-sm font-body font-medium">{{ p.montant.toLocaleString() }} FCFA</td>
            <td class="px-5 py-4"><AppBadge :variant="statutVariant[p.statut]">{{ p.statut }}</AppBadge></td>
            <td class="px-5 py-4 text-sm font-body text-gray-500">{{ p.date }}</td>
          </tr>
        </template>
        <template #footer>
          <AppPagination :current-page="1" :last-page="3" :total="12" @page-change="() => {}" />
        </template>
      </AppTable>
    </div>
  </AppLayout>
</template>
