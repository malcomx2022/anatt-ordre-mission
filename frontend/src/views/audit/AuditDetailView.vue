<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { auditService } from '@/services/audit'
import { ROLE_LABELS } from '@/types'
import type { AuditLog } from '@/types'

const route = useRoute()
const id = Number(route.params.id)
const log = ref<AuditLog | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    log.value = await auditService.get(id)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Détail de l'action" :subtitle="log ? log.action : ''">
    <template #header-actions>
      <router-link to="/audit" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Journal</router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else-if="log" class="max-w-2xl mx-auto space-y-5">
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-3">Qui · quoi · quand</h3>
        <dl class="grid grid-cols-3 gap-3 text-sm font-body">
          <dt class="text-gray-400">Action</dt><dd class="col-span-2 font-display font-semibold">{{ log.action }}</dd>
          <dt class="text-gray-400">Entité</dt><dd class="col-span-2">{{ log.entite }} <span v-if="log.entite_id" class="text-gray-400">#{{ log.entite_id }}</span></dd>
          <dt class="text-gray-400">Utilisateur</dt>
          <dd class="col-span-2">
            {{ log.utilisateur?.name ?? '—' }}
            <span v-if="log.utilisateur" class="text-gray-400">({{ ROLE_LABELS[log.utilisateur.role] }})</span>
          </dd>
          <dt class="text-gray-400">Date</dt><dd class="col-span-2">{{ new Date(log.created_at).toLocaleString('fr-FR') }}</dd>
          <dt class="text-gray-400">Adresse IP</dt><dd class="col-span-2 font-mono">{{ log.ip_address ?? '—' }}</dd>
        </dl>
      </AppCard>

      <AppCard v-if="log.donnees">
        <h3 class="font-display font-bold text-gray-800 mb-3">Données</h3>
        <pre class="bg-gray-50 rounded-xl p-4 text-xs font-mono text-gray-700 overflow-x-auto">{{ JSON.stringify(log.donnees, null, 2) }}</pre>
      </AppCard>
    </div>
  </AppLayout>
</template>
