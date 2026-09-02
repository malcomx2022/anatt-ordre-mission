<script setup lang="ts">
defineProps<{
  items: { label: string; ok: boolean }[]
}>()
</script>

<template>
  <div class="space-y-2">
    <div v-for="item in items" :key="item.label" class="flex items-center gap-2">
      <svg v-if="item.ok" class="w-4 h-4 text-anatt-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
      <svg v-else class="w-4 h-4 text-red-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
      <span class="text-xs font-body" :class="item.ok ? 'text-gray-700' : 'text-red-500'">{{ item.label }}</span>
    </div>
  </div>
</template>
