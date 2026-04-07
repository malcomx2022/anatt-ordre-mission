<script setup lang="ts">
defineProps<{
  variant?: 'primary' | 'danger' | 'outline' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
}>()
</script>

<template>
  <button
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-xl font-display font-semibold text-sm transition-all duration-200 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
    :class="[
      variant === 'danger' ? 'bg-red-500 text-white hover:bg-red-600' :
      variant === 'outline' ? 'border-2 border-anatt-500 text-anatt-600 hover:bg-anatt-500 hover:text-white' :
      variant === 'ghost' ? 'text-gray-600 hover:bg-gray-100' :
      'bg-anatt-500 text-white hover:bg-anatt-600 shadow-[0_4px_12px_rgba(29,158,79,0.25)]',
      size === 'sm' ? 'px-3 py-1.5 text-xs' :
      size === 'lg' ? 'px-8 py-3.5' :
      'px-6 py-3',
    ]"
  >
    <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
    <slot />
  </button>
</template>
