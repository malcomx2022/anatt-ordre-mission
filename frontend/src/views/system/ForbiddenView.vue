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
      <div class="w-24 h-24 bg-red-500/20 backdrop-blur-sm rounded-2xl mx-auto mb-6 flex items-center justify-center border border-red-500/30">
        <svg class="w-12 h-12 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      </div>
      <h1 class="text-3xl font-display font-bold text-white mb-2">Acces refuse</h1>
      <p class="text-anatt-200 text-sm mb-4">Vous n'avez pas les permissions necessaires pour acceder a cette page.</p>
      <div class="bg-white/10 rounded-xl p-4 mb-8 max-w-sm mx-auto">
        <p class="text-sm text-anatt-100">Contactez votre administrateur si vous pensez que c'est une erreur.</p>
      </div>
      <button class="px-6 py-3 bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold rounded-xl transition-all" @click="router.push('/')">
        Retour a l'accueil
      </button>
    </div>
  </div>
</template>
