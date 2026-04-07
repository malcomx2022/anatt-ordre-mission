<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(false)

function downloadPdf() {
  loading.value = true
  setTimeout(() => { loading.value = false }, 1500)
}
</script>

<template>
  <AppLayout title="Apercu PDF" subtitle="Ordre de mission OM-2026-0042">
    <template #header-actions>
      <AppButton :loading="loading" @click="downloadPdf">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
        Telecharger
      </AppButton>
      <AppButton variant="outline" @click="window.print()">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
        Imprimer
      </AppButton>
    </template>

    <div class="max-w-4xl mx-auto">
      <div class="bg-white rounded-2xl border border-gray-100 p-8 shadow-sm">
        <!-- PDF Preview mock -->
        <div class="border-2 border-dashed border-gray-200 rounded-xl p-12 text-center">
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
          <p class="font-display font-bold text-gray-400 mb-2">Apercu du document PDF</p>
          <p class="text-sm text-gray-400 font-body">Le document sera genere et affiche ici via vue-pdf-embed</p>
          <div class="mt-6 mx-auto max-w-sm text-left bg-gray-50 rounded-xl p-6 text-sm font-body text-gray-600 space-y-2">
            <p class="font-display font-bold text-center text-gray-800 text-lg mb-4">ORDRE DE MISSION</p>
            <p><strong>N°:</strong> OM-2026-0042</p>
            <p><strong>Agent:</strong> Jean KOUASSI (ANT-001)</p>
            <p><strong>Objet:</strong> Formation transport intelligent</p>
            <p><strong>Destination:</strong> Parakou</p>
            <p><strong>Periode:</strong> 10/04/2026 - 14/04/2026 (5 jours)</p>
            <p><strong>Montant:</strong> 125 000 FCFA</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
