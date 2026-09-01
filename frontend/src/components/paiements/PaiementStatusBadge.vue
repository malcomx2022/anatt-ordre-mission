<script setup lang="ts">
import AppBadge from '@/components/shared/AppBadge.vue'

defineProps<{
  statut: string
}>()

type Variant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

// Statuts de lot (GENERE/CONFIRME/ERREUR) et de versement
// (EN_ATTENTE/GENERE/CONFIRME/ANNULE ; A_RECOUVRER/RECOUVRE pour un trop-perçu).
const config: Record<string, { variant: Variant; label: string }> = {
  EN_ATTENTE: { variant: 'warning', label: 'En attente' },
  GENERE: { variant: 'info', label: 'Généré' },
  CONFIRME: { variant: 'success', label: 'Confirmé' },
  ERREUR: { variant: 'danger', label: 'Erreur' },
  ANNULE: { variant: 'neutral', label: 'Annulé' },
  A_RECOUVRER: { variant: 'danger', label: 'À recouvrer' },
  RECOUVRE: { variant: 'success', label: 'Recouvré' },
}
</script>

<template>
  <AppBadge :variant="config[statut]?.variant ?? 'neutral'">{{ config[statut]?.label ?? statut }}</AppBadge>
</template>
