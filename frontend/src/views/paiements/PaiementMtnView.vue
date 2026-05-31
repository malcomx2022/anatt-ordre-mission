<script setup lang="ts">
import AppLayout from '@/components/shared/AppLayout.vue'
import PaiementGeneration from '@/components/paiements/PaiementGeneration.vue'
</script>

<template>
  <AppLayout title="Génération paiement MTN" subtitle="Sélection → aperçu → génération .txt → confirmation">
    <div class="max-w-4xl mx-auto">
      <PaiementGeneration operateur-initial="MTN" />
    </div>
  </AppLayout>
</template>
