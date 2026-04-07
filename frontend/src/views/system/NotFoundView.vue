<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-anatt-800 via-anatt-700 to-anatt-900 bg-pattern flex items-center justify-center p-4">
    <div class="fixed top-0 left-0 right-0 h-1.5 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>
    <div class="text-center login-card">
      <div class="w-24 h-24 bg-white/10 backdrop-blur-sm rounded-2xl mx-auto mb-6 flex items-center justify-center border border-white/20">
        <span class="text-5xl font-display font-bold text-gold-400">404</span>
      </div>
      <h1 class="text-3xl font-display font-bold text-white mb-2">Page introuvable</h1>
      <p class="text-anatt-200 text-sm mb-8 max-w-md">La page que vous recherchez n'existe pas ou a ete deplacee.</p>
      <div class="flex items-center justify-center gap-4">
        <button class="px-6 py-3 bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold rounded-xl transition-all" @click="router.push('/')">
          Retour a l'accueil
        </button>
        <button class="px-6 py-3 border-2 border-white/30 text-white font-display font-semibold rounded-xl hover:bg-white/10 transition-all" @click="router.back()">
          Page precedente
        </button>
      </div>
    </div>
  </div>
</template>
