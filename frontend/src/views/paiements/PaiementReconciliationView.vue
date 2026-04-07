<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppTable from '@/components/shared/AppTable.vue'

const file = ref<File | null>(null)
const uploading = ref(false)
const results = ref<{ ref: string; montant: number; statut: string }[]>([])

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  file.value = target.files?.[0] || null
}

function handleUpload() {
  uploading.value = true
  setTimeout(() => {
    uploading.value = false
    results.value = [
      { ref: 'OM-2026-0032', montant: 75000, statut: 'SUCCES' },
      { ref: 'OM-2026-0033', montant: 125000, statut: 'SUCCES' },
      { ref: 'OM-2026-0034', montant: 50000, statut: 'ECHEC' },
      { ref: 'OM-2026-0035', montant: 100000, statut: 'SUCCES' },
    ]
  }, 2000)
}
</script>

<template>
  <AppLayout title="Reconciliation" subtitle="Rapprochement fichiers envoyes vs retours operateurs">
    <div class="space-y-6">
      <!-- Upload zone -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <h2 class="font-display font-bold text-gray-800 mb-4">Importer le fichier retour</h2>
        <div class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
          <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <p class="font-display font-medium text-gray-600 mb-2">Deposez le fichier retour CSV ici</p>
          <p class="text-sm text-gray-400 font-body mb-4">Formats acceptes: .csv, .txt</p>
          <input type="file" accept=".csv,.txt" class="hidden" id="fileInput" @change="onFileChange">
          <label for="fileInput" class="cursor-pointer px-6 py-2.5 bg-anatt-500 text-white font-display font-semibold text-sm rounded-xl hover:bg-anatt-600 transition-all">
            Choisir un fichier
          </label>
          <p v-if="file" class="mt-3 text-sm font-body text-anatt-600">{{ file.name }}</p>
        </div>
        <div v-if="file" class="mt-4 flex justify-end">
          <AppButton :loading="uploading" @click="handleUpload">Lancer la reconciliation</AppButton>
        </div>
      </div>

      <!-- Results -->
      <div v-if="results.length > 0" class="space-y-4">
        <div class="grid grid-cols-3 gap-4">
          <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-display font-bold text-gray-800">{{ results.length }}</p>
            <p class="text-xs text-gray-500 font-body">Total operations</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-display font-bold text-green-600">{{ results.filter(r => r.statut === 'SUCCES').length }}</p>
            <p class="text-xs text-gray-500 font-body">Reussies</p>
          </div>
          <div class="bg-white rounded-xl border border-gray-100 p-4 text-center">
            <p class="text-2xl font-display font-bold text-red-600">{{ results.filter(r => r.statut === 'ECHEC').length }}</p>
            <p class="text-xs text-gray-500 font-body">Echouees</p>
          </div>
        </div>

        <AppTable>
          <template #header>
            <th class="px-5 py-3">Reference</th>
            <th class="px-5 py-3">Montant</th>
            <th class="px-5 py-3">Statut</th>
          </template>
          <template #body>
            <tr v-for="r in results" :key="r.ref" class="hover:bg-anatt-50/30">
              <td class="px-5 py-4 text-sm font-body font-medium">{{ r.ref }}</td>
              <td class="px-5 py-4 text-sm font-body">{{ r.montant.toLocaleString() }} FCFA</td>
              <td class="px-5 py-4"><AppBadge :variant="r.statut === 'SUCCES' ? 'success' : 'danger'">{{ r.statut }}</AppBadge></td>
            </tr>
          </template>
        </AppTable>
      </div>
    </div>
  </AppLayout>
</template>
