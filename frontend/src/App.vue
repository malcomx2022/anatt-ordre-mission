<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppToast from '@/components/shared/AppToast.vue'
import AppNavProgress from '@/components/shared/AppNavProgress.vue'
</script>

<template>
  <!-- Barre de progression pendant le chargement des écrans (import à la demande). -->
  <AppNavProgress />
  <RouterView />
  <!-- Conteneur global des notifications : sans lui, tous les messages de
       succès et d'erreur de l'application resteraient invisibles. -->
  <AppToast />
</template>
