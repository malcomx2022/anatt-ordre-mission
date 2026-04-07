<script setup lang="ts">
defineProps<{
  variant?: 'success' | 'warning' | 'danger' | 'info' | 'neutral'
}>()
</script>

<template>
  <span
    class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-display font-bold"
    :class="[
      variant === 'success' ? 'bg-anatt-50 text-anatt-600' :
      variant === 'warning' ? 'bg-gold-50 text-gold-600' :
      variant === 'danger' ? 'bg-red-50 text-red-600' :
      variant === 'info' ? 'bg-blue-50 text-blue-600' :
      'bg-gray-100 text-gray-600'
    ]"
  >
    <slot />
  </span>
</template>
