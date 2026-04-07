<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { StatutMission } from '@/types'

const props = defineProps<{
  statut: StatutMission
}>()

const config: Record<StatutMission, { variant: 'info' | 'warning' | 'success' | 'danger'; label: string }> = {
  BROUILLON: { variant: 'info', label: 'Brouillon' },
  SOUMIS: { variant: 'warning', label: 'Soumis' },
  VALIDE_N1: { variant: 'success', label: 'Valide N1' },
  APPROUVE_DG: { variant: 'success', label: 'Approuve DG' },
  PAYE: { variant: 'success', label: 'Paye' },
  REJETE: { variant: 'danger', label: 'Rejete' },
}
</script>

<template>
  <AppBadge :variant="config[statut].variant">{{ config[statut].label }}</AppBadge>
</template>
