<script setup lang="ts">
defineProps<{
  loading?: boolean
}>()
</script>

<template>
  <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm font-body">
        <thead>
          <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase tracking-wider font-display">
            <slot name="header" />
          </tr>
        </thead>
        <tbody v-if="!loading" class="divide-y divide-gray-50">
          <slot name="body" />
        </tbody>
      </table>
      <div v-if="loading" class="flex items-center justify-center py-12">
        <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
      </div>
    </div>
    <slot name="footer" />
  </div>
</template>
