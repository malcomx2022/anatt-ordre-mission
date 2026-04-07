<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'

const route = useRoute()
const router = useRouter()

const events = ref([
  { action: 'Creation de l\'ordre de mission', user: 'Gislain AHOUANSOU', role: 'ADMIN', date: '07/04/2026 10:30:15', ip: '192.168.1.100', details: 'Statut: BROUILLON' },
  { action: 'Modification des dates', user: 'Gislain AHOUANSOU', role: 'ADMIN', date: '07/04/2026 10:45:22', ip: '192.168.1.100', details: 'Date retour changee: 12/04 -> 14/04' },
  { action: 'Soumission pour validation', user: 'Gislain AHOUANSOU', role: 'ADMIN', date: '07/04/2026 11:00:05', ip: '192.168.1.100', details: 'Statut: BROUILLON -> SOUMIS' },
  { action: 'Validation chef de service', user: 'Marie DOSSOU', role: 'CHEF_SERVICE', date: '07/04/2026 14:20:33', ip: '192.168.1.105', details: 'Statut: SOUMIS -> VALIDE_N1' },
])
</script>

<template>
  <AppLayout title="Historique des actions" subtitle="OM-2026-0042">
    <template #header-actions>
      <AppButton variant="outline" @click="router.push(`/ordres/${route.params.id}`)">Retour au detail</AppButton>
    </template>

    <div class="max-w-3xl">
      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="space-y-0">
          <div v-for="(event, i) in events" :key="i" class="flex gap-4">
            <div class="flex flex-col items-center">
              <div class="w-4 h-4 rounded-full border-2 border-anatt-500 bg-white shrink-0 mt-1"></div>
              <div v-if="i < events.length - 1" class="w-0.5 flex-1 bg-gray-200"></div>
            </div>
            <div class="pb-6 flex-1">
              <div class="bg-gray-50 rounded-xl p-4">
                <div class="flex items-start justify-between mb-2">
                  <p class="text-sm font-display font-semibold text-gray-800">{{ event.action }}</p>
                  <span class="text-[10px] font-display font-bold bg-anatt-50 text-anatt-600 px-2 py-0.5 rounded-full">{{ event.role }}</span>
                </div>
                <p class="text-xs text-gray-500 font-body mb-1">{{ event.date }} - {{ event.user }}</p>
                <p class="text-xs text-gray-400 font-body">IP: {{ event.ip }}</p>
                <p v-if="event.details" class="text-xs text-gray-600 font-body mt-2 bg-white rounded px-2 py-1">{{ event.details }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
