<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'

const props = defineProps<{
  action: string
}>()

const config: Record<string, { variant: 'success' | 'warning' | 'danger' | 'info' | 'neutral' }> = {
  LOGIN: { variant: 'info' },
  CREATE_ORDRE_MISSION: { variant: 'success' },
  SUBMIT_ORDRE_MISSION: { variant: 'warning' },
  VALIDATE_N1: { variant: 'success' },
  APPROVE_DG: { variant: 'success' },
  REJECT_ORDRE_MISSION: { variant: 'danger' },
  GENERATE_PAYMENT: { variant: 'info' },
  RECONCILE_PAYMENT: { variant: 'info' },
  CREATE_AGENT: { variant: 'success' },
  UPDATE_TAUX: { variant: 'warning' },
  IMPORT_AGENTS: { variant: 'info' },
  UPDATE_SETTINGS: { variant: 'warning' },
  BACKUP_DATABASE: { variant: 'neutral' },
}
</script>

<template>
  <AppBadge :variant="config[action]?.variant || 'neutral'">{{ action }}</AppBadge>
</template>
