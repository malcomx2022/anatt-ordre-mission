<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppToast from '@/components/shared/AppToast.vue'
</script>

<template>
  <RouterView />
  <!-- Conteneur global des notifications : sans lui, tous les messages de
       succès et d'erreur de l'application resteraient invisibles. -->
  <AppToast />
</template>
