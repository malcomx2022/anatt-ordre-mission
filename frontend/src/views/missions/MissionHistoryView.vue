<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { useApi } from '@/composables/useApi'

const route = useRoute()
const router = useRouter()
const api = useApi()
const loading = ref(true)
const missionNumero = ref('')

interface HistoryEvent {
  action: string
  user: string
  role: string
  date: string
  ip: string
  details: string
}

const events = ref<HistoryEvent[]>([])

onMounted(async () => {
  try {
    const [historiqueRes, missionRes] = await Promise.all([
      api.get(`/api/ordres/${route.params.id}/historique`),
      api.get(`/api/ordres/${route.params.id}`),
    ])

    missionNumero.value = missionRes.data.data?.reference_semo || `OM-${route.params.id}`

    const logs = historiqueRes.data.data || []
    events.value = logs.map((log: {
      action: string
      user?: { name?: string; role?: string } | null
      created_at: string
      ip_address?: string | null
      donnees?: unknown
    }) => ({
      action: formatAction(log.action),
      user: log.user?.name || '--',
      role: log.user?.role || '--',
      date: formatDate(log.created_at),
      ip: log.ip_address || '--',
      details: formatDetails(log.donnees),
    }))
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
})

function formatAction(action: string): string {
  const map: Record<string, string> = {
    // Codes journalisés par MissionService (entité ordre_mission).
    IMPORT_OM: 'Retranscription depuis SEMO',
    VALIDATE_OM: 'Validation de l\'ordre de mission',
    REJECT_OM: 'Rejet de l\'ordre de mission',
    AMEND_OM: 'Amendement (avenant)',
  }
  return map[action] || action
}

function formatDetails(details: unknown): string {
  if (!details) return ''
  if (typeof details === 'string') return details
  const d = details as { motif?: string; statut?: string; nb_jours?: number; delta?: number }
  if (d.motif && d.nb_jours != null) return `${d.nb_jours} jour(s), écart ${d.delta ?? 0} FCFA — ${d.motif}`
  if (d.motif) return d.motif
  if (d.statut) return `Statut : ${d.statut}`
  return JSON.stringify(details)
}

function formatDate(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}
</script>

<template>
  <AppLayout title="Historique des actions" :subtitle="missionNumero">
    <template #header-actions>
      <AppButton variant="outline" @click="router.push(`/ordres/${route.params.id}`)">Retour au détail</AppButton>
    </template>

    <div class="max-w-3xl">
      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-24">
        <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
      </div>

      <!-- Empty state -->
      <div v-else-if="events.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <p class="text-sm font-body text-gray-400">Aucun historique disponible pour cet ordre de mission.</p>
      </div>

      <!-- Timeline -->
      <div v-else class="bg-white rounded-2xl border border-gray-100 p-6">
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
