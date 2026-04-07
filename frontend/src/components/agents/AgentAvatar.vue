<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  nom: string
  prenom?: string
  size?: 'sm' | 'md' | 'lg'
  color?: string
}>()

const initials = computed(() => {
  const parts = props.prenom ? [props.prenom, props.nom] : props.nom.split(' ')
  return parts.map((p) => p[0]).join('').toUpperCase().slice(0, 2)
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'w-8 h-8 text-xs'
    case 'lg': return 'w-16 h-16 text-xl'
    default: return 'w-10 h-10 text-sm'
  }
})
</script>

<template>
  <div
    class="rounded-lg flex items-center justify-center font-display font-bold"
    :class="[sizeClasses, color || 'bg-anatt-100 text-anatt-600']"
  >
    {{ initials }}
  </div>
</template>
