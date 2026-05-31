<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { Operateur } from '@/types'

defineProps<{
  operateur: Operateur
}>()
</script>

<template>
  <AppBadge :variant="operateur === 'MTN' ? 'warning' : 'info'">{{ operateur }}</AppBadge>
</template>
