<script setup lang="ts">
import { computed } from 'vue'
import logoColor from '@/assets/logo-anatt.png'
import logoWhite from '@/assets/logo-anatt-white.png'
import iconSquare from '@/assets/icon-anatt.png'

const props = defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl'
  variant?: 'full' | 'compact' | 'icon'
  dark?: boolean
}>()

// Fond clair -> logo couleur (texte noir) ; fond sombre -> variante blanche
// (texte blanc, barre tricolore conservée). Le PNG est transparent : il ressort
// sur n'importe quelle interface.
const src = computed(() => (props.dark ? logoWhite : logoColor))

const fullHeight = computed(() => ({
  sm: 'h-8',
  md: 'h-10',
  lg: 'h-12',
  xl: 'h-16',
}[props.size ?? 'md']))

const iconSize = computed(() => ({
  sm: 'w-8 h-8',
  md: 'w-10 h-10',
  lg: 'w-14 h-14',
  xl: 'w-20 h-20',
}[props.size ?? 'md']))
</script>

<template>
  <!-- Logo ANaTT — Agence Nationale des Transports Terrestres (PNG transparent) -->

  <!-- Variante full : logo complet -->
  <img
    v-if="variant === 'full' || !variant"
    :src="src"
    alt="ANaTT — Agence Nationale des Transports Terrestres"
    class="w-auto object-contain select-none"
    :class="fullHeight"
    draggable="false"
  >

  <!-- Variante compact : logo réduit (barres latérales, en-têtes) -->
  <img
    v-else-if="variant === 'compact'"
    :src="src"
    alt="ANaTT"
    class="h-9 w-auto object-contain select-none"
    draggable="false"
  >

  <!-- Variante icon : marque carrée (vert ANaTT + ANaTT blanc) -->
  <img
    v-else-if="variant === 'icon'"
    :src="iconSquare"
    alt="ANaTT"
    class="object-contain rounded-xl select-none shrink-0"
    :class="iconSize"
    draggable="false"
  >
</template>
