<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

/**
 * Réessayer : revient à la page demandée quand l'erreur est survenue en y allant
 * (rechargement interrompu par une erreur passagère de l'API), sinon recharge.
 * Seul un chemin interne est suivi (« /… », jamais « //hôte »).
 */
function reload() {
  const cible = route.query.redirect
  if (typeof cible === 'string' && cible.startsWith('/') && !cible.startsWith('//')) {
    window.location.assign(cible)
  } else {
    window.location.reload()
  }
}
</script>

<template>
  <div class="font-body bg-gray-50 min-h-screen flex flex-col">
    <!-- Flag bar -->
    <div class="flag-bar fixed top-0 left-0 right-0 z-50"></div>

    <div class="flex-1 flex items-center justify-center p-6 pt-8">
      <div class="text-center max-w-lg animate-fade-up">
        <!-- SVG Illustration -->
        <div class="relative mb-8">
          <svg class="w-72 h-52 mx-auto" viewBox="0 0 320 240" fill="none" xmlns="http://www.w3.org/2000/svg" style="animation: float 3s ease-in-out infinite;">
            <rect x="30" y="180" width="260" height="28" rx="4" fill="#e5e7eb"/>
            <line x1="70" y1="194" x2="90" y2="194" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10"/>
            <line x1="120" y1="194" x2="140" y2="194" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10"/>
            <line x1="170" y1="194" x2="190" y2="194" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 10"/>
            <g transform="translate(90, 108) rotate(-3)">
              <rect x="0" y="20" width="90" height="52" rx="8" fill="#1D9E4F"/>
              <rect x="5" y="25" width="26" height="18" rx="3" fill="#C5F0D5"/>
              <rect x="36" y="25" width="26" height="18" rx="3" fill="#C5F0D5"/>
              <line x1="40" y1="28" x2="50" y2="38" stroke="#14713A" stroke-width="1.5"/>
              <rect x="68" y="25" width="16" height="18" rx="2" fill="#D4A843"/>
              <circle cx="20" cy="74" r="9" fill="#374151"/><circle cx="20" cy="74" r="4.5" fill="#6b7280"/>
              <circle cx="68" cy="74" r="9" fill="#374151"/><circle cx="68" cy="74" r="4.5" fill="#6b7280"/>
            </g>
            <circle cx="105" cy="105" r="8" fill="#9ca3af" opacity="0.3" class="smoke-anim"/>
            <circle cx="115" cy="100" r="6" fill="#9ca3af" opacity="0.25" class="smoke-anim" style="animation-delay:.4s"/>
            <circle cx="95" cy="108" r="10" fill="#9ca3af" opacity="0.2" class="smoke-anim" style="animation-delay:.8s"/>
            <circle cx="100" cy="125" r="2" fill="#D4A843" class="spark-anim"/>
            <circle cx="108" cy="120" r="1.5" fill="#CE1126" class="spark-anim" style="animation-delay:.3s"/>
            <g transform="translate(225, 140)">
              <path d="M12 0 L24 20 L0 20 Z" fill="#D4A843" stroke="#B8891A" stroke-width="1.5"/>
              <text x="12" y="16" fill="#92400e" font-size="12" font-weight="800" text-anchor="middle">!</text>
            </g>
            <g class="gear-anim" style="transform-origin:55px 50px">
              <circle cx="55" cy="50" r="12" fill="none" stroke="#d1d5db" stroke-width="3"/>
              <circle cx="55" cy="50" r="5" fill="#d1d5db"/>
            </g>
          </svg>
        </div>

        <!-- Error Code -->
        <div class="flex items-center justify-center gap-2 mb-4">
          <span class="text-7xl font-display font-extrabold text-gray-700">5</span>
          <div class="w-14 h-14 bg-benin-red rounded-full flex items-center justify-center shadow-lg shadow-benin-red/30">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384-3.19A1.505 1.505 0 015 10.73V5.865a1.505 1.505 0 011.036-1.43l5.384-1.795a1.505 1.505 0 01.964 0l5.384 1.795A1.505 1.505 0 0118.804 5.865v4.865a1.505 1.505 0 01-1.036 1.43l-5.384 3.19a1.505 1.505 0 01-1.964 0z" /></svg>
          </div>
          <span class="text-7xl font-display font-extrabold text-gray-700">0</span>
        </div>

        <h1 class="text-2xl font-display font-extrabold text-gray-800 mb-3">Erreur serveur</h1>
        <p class="text-gray-500 font-body mb-8 max-w-sm mx-auto">Le moteur a calé ! Le serveur rencontre un problème temporaire. Réessayez dans un instant : votre session est conservée.</p>

        <!-- Suggestion Cards -->
        <div class="grid grid-cols-3 gap-3 mb-8 max-w-md mx-auto">
          <button @click="reload" class="bg-white border border-gray-100 rounded-xl p-4 hover:border-anatt-200 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-anatt-50 rounded-lg flex items-center justify-center mx-auto mb-2 group-hover:bg-anatt-100 transition-colors">
              <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
            </div>
            <p class="text-xs font-display font-semibold text-gray-700">Réessayer</p>
          </button>
          <router-link to="/" class="bg-white border border-gray-100 rounded-xl p-4 hover:border-gold-400 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center mx-auto mb-2 group-hover:bg-amber-100 transition-colors">
              <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /></svg>
            </div>
            <p class="text-xs font-display font-semibold text-gray-700">Tableau de bord</p>
          </router-link>
          <a href="mailto:support@anatt.bj" class="bg-white border border-gray-100 rounded-xl p-4 hover:border-blue-200 hover:shadow-md transition-all group">
            <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center mx-auto mb-2 group-hover:bg-blue-100 transition-colors">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
            </div>
            <p class="text-xs font-display font-semibold text-gray-700">Support</p>
          </a>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-center gap-4">
          <button @click="router.back()" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-display font-semibold hover:bg-gray-100 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
            Page précédente
          </button>
          <button @click="reload" class="px-5 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 transition-colors shadow-lg shadow-anatt-500/20 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
            Actualiser la page
          </button>
        </div>

        <!-- Status Info -->
        <div class="mt-8 inline-flex items-center gap-2 bg-amber-50 border border-amber-100 rounded-full px-4 py-2">
          <span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse"></span>
          <span class="text-xs text-amber-700 font-display font-medium">Incident en cours de résolution</span>
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
.smoke-anim { animation: smoke 2s ease-out infinite; }
@keyframes smoke { 0%{opacity:0;transform:translateY(0) scale(.5)} 40%{opacity:.6} 100%{opacity:0;transform:translateY(-40px) scale(1.5)} }
.spark-anim { animation: spark .8s ease-in-out infinite; }
@keyframes spark { 0%,100%{opacity:0} 50%{opacity:1} }
.gear-anim { animation: gearRotate 6s linear infinite; }
@keyframes gearRotate { 0%{transform:rotate(0)} 100%{transform:rotate(360deg)} }
</style>
