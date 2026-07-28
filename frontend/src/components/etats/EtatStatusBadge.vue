<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { StatutEtat } from '@/types'

defineProps<{
  statut: StatutEtat
}>()

type Variant = 'info' | 'warning' | 'success' | 'danger' | 'neutral'

const config: Record<StatutEtat, { variant: Variant; label: string }> = {
  BROUILLON: { variant: 'neutral', label: 'Brouillon' },
  CONTROLE: { variant: 'info', label: 'Contrôlé' },
  VISA_DAF: { variant: 'warning', label: 'Visé DAF' },
  VALIDE: { variant: 'success', label: 'Validé' },
  REJETE: { variant: 'danger', label: 'Rejeté' },
}
</script>

<template>
  <AppBadge :variant="config[statut]?.variant ?? 'neutral'">{{ config[statut]?.label ?? statut }}</AppBadge>
</template>
