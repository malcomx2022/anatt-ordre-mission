<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

// Mêmes icônes que le menu latéral (AppSidebar : grid, credit-card, users).
const suggestions = [
  { label: 'Tableau de bord', route: '/', icon: 'grid' },
  { label: 'Paiements', route: '/paiements', icon: 'credit-card' },
  { label: 'Personnel', route: '/agents', icon: 'users' },
]
</script>

<template>
  <div class="font-body bg-gray-50 min-h-screen flex flex-col">
    <!-- Flag bar -->
    <div class="flag-bar fixed top-0 left-0 right-0 z-50"></div>

    <div class="flex-1 flex items-center justify-center p-6 pt-8">
      <div class="text-center max-w-lg animate-fade-up">
        <!-- SVG Illustration -->
        <div class="relative mb-8">
          <svg class="w-64 h-48 mx-auto" viewBox="0 0 300 220" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: float 3s ease-in-out infinite;">
            <rect x="40" y="160" width="220" height="30" rx="4" fill="#e5e7eb"/>
            <line x1="80" y1="175" x2="100" y2="175" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10" style="animation: roadDash 1s linear infinite;"/>
            <line x1="130" y1="175" x2="150" y2="175" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10" style="animation: roadDash 1s linear infinite;"/>
            <line x1="180" y1="175" x2="200" y2="175" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10" style="animation: roadDash 1s linear infinite;"/>
            <g transform="translate(100, 90)">
              <rect x="0" y="20" width="80" height="50" rx="8" fill="#1D9E4F"/>
              <rect x="5" y="25" width="25" height="18" rx="3" fill="#C5F0D5"/>
              <rect x="35" y="25" width="25" height="18" rx="3" fill="#C5F0D5"/>
              <rect x="65" y="25" width="10" height="18" rx="2" fill="#D4A843"/>
              <circle cx="20" cy="72" r="8" fill="#374151"/><circle cx="20" cy="72" r="4" fill="#6b7280"/>
              <circle cx="60" cy="72" r="8" fill="#374151"/><circle cx="60" cy="72" r="4" fill="#6b7280"/>
              <text x="30" y="12" fill="#CE1126" font-size="20" font-weight="800" text-anchor="middle" class="blink-anim">?</text>
              <text x="55" y="5" fill="#D4A843" font-size="14" font-weight="800" text-anchor="middle" class="blink-anim" style="animation-delay:.5s">?</text>
            </g>
            <g transform="translate(220, 60)">
              <rect x="12" y="40" width="6" height="60" fill="#9ca3af"/>
              <rect x="0" y="10" width="30" height="35" rx="4" fill="#CE1126"/>
              <text x="15" y="33" fill="white" font-size="11" font-weight="800" text-anchor="middle">404</text>
            </g>
          </svg>
        </div>

        <!-- Error Code -->
        <div class="flex items-center justify-center gap-2 mb-4">
          <span class="text-7xl font-display font-extrabold text-anatt-500">4</span>
          <div class="w-14 h-14 bg-gold-400 rounded-full flex items-center justify-center shadow-lg shadow-gold-400/30">
            <svg class="w-6 h-6 text-anatt-900" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0H21M3.375 14.25h17.25" /></svg>
          </div>
          <span class="text-7xl font-display font-extrabold text-anatt-500">4</span>
        </div>

        <h1 class="text-2xl font-display font-extrabold text-gray-800 mb-3">Page non trouvée</h1>
        <p class="text-gray-500 font-body mb-8 max-w-sm mx-auto">Le bus a pris un mauvais virage ! La page que vous recherchez n'existe pas ou a été déplacée.</p>

        <!-- Suggestion Cards -->
        <div class="grid grid-cols-3 gap-3 mb-8 max-w-md mx-auto">
          <router-link v-for="s in suggestions" :key="s.label" :to="s.route" class="bg-white border border-gray-100 rounded-xl p-4 hover:border-anatt-200 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-anatt-50 rounded-lg flex items-center justify-center mx-auto mb-2 group-hover:bg-anatt-100 transition-colors">
              <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <template v-if="s.icon === 'grid'"><rect width="7" height="7" x="3" y="3" rx="1" /><rect width="7" height="7" x="14" y="3" rx="1" /><rect width="7" height="7" x="14" y="14" rx="1" /><rect width="7" height="7" x="3" y="14" rx="1" /></template>
                <template v-else-if="s.icon === 'credit-card'"><rect width="20" height="14" x="2" y="5" rx="2" /><line x1="2" x2="22" y1="10" y2="10" /></template>
                <template v-else><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></template>
              </svg>
            </div>
            <p class="text-xs font-display font-semibold text-gray-700">{{ s.label }}</p>
          </router-link>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-center gap-4">
          <button @click="router.back()" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-display font-semibold hover:bg-gray-100 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Page précédente
          </button>
          <router-link to="/" class="px-5 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 transition-colors shadow-lg shadow-anatt-500/20 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
            Retour à l'accueil
          </router-link>
        </div>

        <!-- URL Display -->
        <div class="mt-8 inline-flex items-center gap-2 bg-gray-100 rounded-full px-4 py-2">
          <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.07-9.07l4.5-4.5a4.5 4.5 0 016.364 6.364l-1.757 1.757" /></svg>
          <span class="text-xs text-gray-500 font-mono">{{ $route.fullPath }}</span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-6">
      <div class="flag-bar mb-4"></div>
      <div class="flex items-center justify-center gap-2 text-gray-400 text-xs font-body">
        <span>ANaTT - Agence Nationale des Transports Terrestres</span>
      </div>
    </footer>
  </div>
</template>

<style scoped>
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
@keyframes roadDash { 0%{stroke-dashoffset:0} 100%{stroke-dashoffset:-40} }
.blink-anim { animation: blink 2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
</style>
