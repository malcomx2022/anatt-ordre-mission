<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'
import type { StatutOM } from '@/types'

defineProps<{
  statut: StatutOM
}>()

type Variant = 'info' | 'warning' | 'success' | 'danger' | 'neutral'

const config: Record<StatutOM, { variant: Variant; label: string }> = {
  BROUILLON: { variant: 'info', label: 'Brouillon' },
  IMPORTE: { variant: 'warning', label: 'Importé' },
  VERIFIE: { variant: 'info', label: 'Vérifié' },
  VISA_DAF: { variant: 'info', label: 'Visé DAF' },
  EN_ATTENTE: { variant: 'warning', label: 'En attente' },
  VALIDE: { variant: 'success', label: 'Validé' },
  REJETE: { variant: 'danger', label: 'Rejeté' },
  SOLDE: { variant: 'success', label: 'Soldé' },
  ANNULE: { variant: 'neutral', label: 'Annulé' },
}
</script>

<template>
  <AppBadge :variant="config[statut]?.variant ?? 'neutral'">{{ config[statut]?.label ?? statut }}</AppBadge>
</template>
