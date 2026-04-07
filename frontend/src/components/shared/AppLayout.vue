<script setup lang="ts">
import AppSidebar from './AppSidebar.vue'
import AppTopBar from './AppTopBar.vue'

defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <AppSidebar />
    <main class="ml-64 pt-1 min-h-screen">
      <AppTopBar :title="title" :subtitle="subtitle">
        <template #actions>
          <slot name="header-actions" />
        </template>
      </AppTopBar>
      <div class="p-6">
        <slot />
      </div>
    </main>
  </div>
</template>
