<script setup lang="ts">
defineProps<{
  type?: 'success' | 'error' | 'warning' | 'info'
}>()
</script>

<template>
  <div
    class="rounded-xl p-4 flex items-start gap-3 text-sm font-body"
    :class="[
      type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' :
      type === 'error' ? 'bg-red-50 border border-red-200 text-red-700' :
      type === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-700' :
      'bg-blue-50 border border-blue-200 text-blue-700'
    ]"
  >
    <slot />
  </div>
</template>
