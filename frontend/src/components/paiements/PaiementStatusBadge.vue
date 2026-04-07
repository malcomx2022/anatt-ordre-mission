<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { StatutPaiement } from '@/types'

defineProps<{
  statut: StatutPaiement
}>()

const config: Record<StatutPaiement, { variant: 'success' | 'warning' | 'danger' | 'info'; label: string }> = {
  EN_ATTENTE: { variant: 'warning', label: 'En attente' },
  INITIE: { variant: 'info', label: 'Initie' },
  SUCCES: { variant: 'success', label: 'Succes' },
  ECHEC: { variant: 'danger', label: 'Echoue' },
}
</script>

<template>
  <AppBadge :variant="config[statut].variant">{{ config[statut].label }}</AppBadge>
</template>
