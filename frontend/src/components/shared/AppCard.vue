<script setup lang="ts">
defineProps<{
  hoverable?: boolean
}>()
</script>

<template>
  <div
    class="bg-white rounded-2xl p-6 border border-gray-100 overflow-hidden relative group"
    :class="hoverable ? 'card-hover cursor-pointer' : ''"
  >
    <div
      v-if="hoverable"
      class="absolute top-0 left-0 right-0 h-1 bg-anatt-500 scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"
    />
    <slot />
  </div>
</template>
