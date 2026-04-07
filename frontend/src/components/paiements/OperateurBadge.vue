<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { OperateurPaiement } from '@/types'

defineProps<{
  operateur: OperateurPaiement
}>()
</script>

<template>
  <AppBadge :variant="operateur === 'MTN' ? 'warning' : 'info'">{{ operateur }}</AppBadge>
</template>
