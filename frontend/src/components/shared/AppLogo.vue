<script setup lang="ts">
defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl'
  variant?: 'full' | 'compact' | 'icon'
  dark?: boolean
}>()
</script>

<template>
  <!-- Logo ANaTT - Agence Nationale des Transports Terrestres -->
  <!-- Variante full: logo complet avec texte -->
  <div v-if="variant === 'full' || !variant" class="flex items-center gap-3" :class="size === 'xl' ? 'gap-4' : ''">
    <div
      class="flex items-center justify-center rounded-xl shrink-0"
      :class="[
        size === 'sm' ? 'w-8 h-8' : size === 'lg' ? 'w-14 h-14' : size === 'xl' ? 'w-20 h-20' : 'w-10 h-10',
        dark ? 'bg-white/10' : 'bg-anatt-50'
      ]"
    >
      <!-- Armoiries simplifiees du Benin -->
      <svg
        :class="size === 'sm' ? 'w-5 h-5' : size === 'lg' ? 'w-9 h-9' : size === 'xl' ? 'w-14 h-14' : 'w-6 h-6'"
        viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"
      >
        <!-- Bouclier -->
        <path d="M8 6h24v18c0 6-12 12-12 12S8 30 8 24V6z" fill="#CE1126" opacity="0.15"/>
        <path d="M8 6h12v18c0 6-12 12-12 12V6z" fill="#1D9E4F" opacity="0.2"/>
        <path d="M20 6h12v18c0 6-12 12-12 12V6z" fill="#D4A843" opacity="0.2"/>
        <!-- Etoile centrale -->
        <path d="M20 12l2 4 4.5.7-3.3 3.2.8 4.6L20 22.3l-4 2.2.8-4.6-3.3-3.2 4.5-.7z" fill="#1D9E4F"/>
        <!-- Cadre -->
        <rect x="8" y="6" width="24" height="30" rx="2" stroke="#1D9E4F" stroke-width="1.5" fill="none"/>
      </svg>
    </div>
    <div>
      <div class="flex items-center gap-1.5">
        <span
          class="font-display font-bold tracking-tight"
          :class="[
            size === 'sm' ? 'text-xs' : size === 'lg' ? 'text-xl' : size === 'xl' ? 'text-2xl' : 'text-sm',
            dark ? 'text-white' : 'text-gray-900'
          ]"
        >ANaTT</span>
      </div>
      <p
        class="font-display font-semibold leading-tight"
        :class="[
          size === 'sm' ? 'text-[7px]' : size === 'lg' ? 'text-[10px]' : size === 'xl' ? 'text-xs' : 'text-[8px]',
          dark ? 'text-gray-300' : 'text-gray-500'
        ]"
      >AGENCE NATIONALE DES<br>TRANSPORTS TERRESTRES</p>
      <!-- Flag bar -->
      <div class="flex mt-1" :class="size === 'sm' ? 'h-[2px] w-16' : size === 'xl' ? 'h-[3px] w-32' : 'h-[2px] w-20'">
        <div class="flex-1 bg-benin-green"></div>
        <div class="flex-1 bg-gold-400"></div>
        <div class="flex-1 bg-benin-red"></div>
      </div>
      <p
        class="font-display mt-0.5"
        :class="[
          size === 'sm' ? 'text-[6px]' : size === 'lg' ? 'text-[8px]' : size === 'xl' ? 'text-[10px]' : 'text-[7px]',
          dark ? 'text-gray-400' : 'text-gray-400'
        ]"
      >REPUBLIQUE DU BENIN</p>
    </div>
  </div>

  <!-- Variante compact: icone + ANaTT -->
  <div v-else-if="variant === 'compact'" class="flex items-center gap-2">
    <div
      class="w-8 h-8 flex items-center justify-center rounded-lg shrink-0"
      :class="dark ? 'bg-gold-400' : 'bg-anatt-50'"
    >
      <svg class="w-5 h-5" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8 6h24v18c0 6-12 12-12 12S8 30 8 24V6z" fill="#CE1126" opacity="0.15"/>
        <path d="M20 12l2 4 4.5.7-3.3 3.2.8 4.6L20 22.3l-4 2.2.8-4.6-3.3-3.2 4.5-.7z" fill="#1D9E4F"/>
        <rect x="8" y="6" width="24" height="30" rx="2" stroke="#1D9E4F" stroke-width="1.5" fill="none"/>
      </svg>
    </div>
    <div>
      <span class="font-display font-bold text-sm" :class="dark ? 'text-white' : 'text-gray-900'">ANaTT</span>
      <p class="text-[9px] font-body" :class="dark ? 'text-gray-400' : 'text-gray-500'">Paiements Missions</p>
    </div>
  </div>

  <!-- Variante icon: juste l'icone -->
  <div v-else-if="variant === 'icon'"
    class="flex items-center justify-center rounded-xl shrink-0"
    :class="[
      size === 'sm' ? 'w-8 h-8' : size === 'lg' ? 'w-14 h-14' : 'w-10 h-10',
      dark ? 'bg-gold-400' : 'bg-anatt-50'
    ]"
  >
    <svg
      :class="size === 'sm' ? 'w-5 h-5' : size === 'lg' ? 'w-9 h-9' : 'w-6 h-6'"
      viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"
    >
      <path d="M8 6h24v18c0 6-12 12-12 12S8 30 8 24V6z" fill="#CE1126" opacity="0.15"/>
      <path d="M20 12l2 4 4.5.7-3.3 3.2.8 4.6L20 22.3l-4 2.2.8-4.6-3.3-3.2 4.5-.7z" fill="#1D9E4F"/>
      <rect x="8" y="6" width="24" height="30" rx="2" stroke="#1D9E4F" stroke-width="1.5" fill="none"/>
    </svg>
  </div>
</template>
