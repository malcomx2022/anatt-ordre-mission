<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const hours = ref(0)
const minutes = ref(30)
const seconds = ref(0)
const notifyEmail = ref('')
let timer: ReturnType<typeof setInterval>

onMounted(() => {
  timer = setInterval(() => {
    if (seconds.value > 0) {
      seconds.value--
    } else if (minutes.value > 0) {
      minutes.value--
      seconds.value = 59
    } else if (hours.value > 0) {
      hours.value--
      minutes.value = 59
      seconds.value = 59
    }
  }, 1000)
})

onUnmounted(() => clearInterval(timer))

function pad(n: number): string {
  return n.toString().padStart(2, '0')
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
            <rect x="50" y="50" width="220" height="145" rx="6" fill="#f3f4f6" stroke="#d1d5db" stroke-width="2"/>
            <path d="M40 55 L160 15 L280 55" stroke="#1D9E4F" stroke-width="4" stroke-linecap="round" fill="none"/>
            <path d="M45 55 L160 18 L275 55" fill="#1D9E4F" opacity="0.1"/>
            <line x1="55" y1="80" x2="265" y2="80" stroke="#e5e7eb" stroke-width="1"/>
            <line x1="55" y1="95" x2="265" y2="95" stroke="#e5e7eb" stroke-width="1"/>
            <g class="lift-anim">
              <rect x="90" y="160" width="140" height="5" rx="2" fill="#9ca3af"/>
              <rect x="95" y="165" width="6" height="25" fill="#6b7280"/>
              <rect x="219" y="165" width="6" height="25" fill="#6b7280"/>
              <g transform="translate(100, 105)">
                <rect x="0" y="15" width="120" height="42" rx="8" fill="#1D9E4F"/>
                <rect x="6" y="20" width="28" height="16" rx="3" fill="#C5F0D5"/>
                <rect x="40" y="20" width="28" height="16" rx="3" fill="#C5F0D5"/>
                <rect x="74" y="20" width="28" height="16" rx="3" fill="#C5F0D5"/>
                <rect x="108" y="20" width="8" height="16" rx="2" fill="#D4A843"/>
                <rect x="35" y="42" width="50" height="8" rx="2" fill="#14713A"/>
                <text x="60" y="49" fill="#C5F0D5" font-size="6" font-weight="700" text-anchor="middle">ANaTT</text>
              </g>
            </g>
            <g class="gear-anim" style="transform-origin:75px 130px">
              <circle cx="75" cy="130" r="14" fill="none" stroke="#D4A843" stroke-width="3" opacity="0.6"/>
              <circle cx="75" cy="130" r="6" fill="#D4A843" opacity="0.4"/>
            </g>
            <circle cx="130" cy="110" r="3" fill="#D4A843" class="sparkle-anim"/>
            <circle cx="210" cy="105" r="2" fill="#D4A843" class="sparkle-anim" style="animation-delay:.5s"/>
            <circle cx="170" cy="100" r="2.5" fill="#1D9E4F" class="sparkle-anim" style="animation-delay:1s"/>
          </svg>
        </div>

        <!-- Icon -->
        <div class="flex items-center justify-center mb-5">
          <div class="w-16 h-16 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center shadow-lg shadow-anatt-500/30">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384-3.19A1.505 1.505 0 015 10.73V5.865a1.505 1.505 0 011.036-1.43l5.384-1.795a1.505 1.505 0 01.964 0l5.384 1.795A1.505 1.505 0 0118.804 5.865v4.865a1.505 1.505 0 01-1.036 1.43l-5.384 3.19a1.505 1.505 0 01-1.964 0z" /></svg>
          </div>
        </div>

        <h1 class="text-2xl font-display font-extrabold text-gray-800 mb-3">Maintenance en cours</h1>
        <p class="text-gray-500 font-body mb-6 max-w-sm mx-auto">Notre bus est au garage pour une revision ! Le systeme est temporairement indisponible pour amelioration.</p>

        <!-- Progress Bar -->
        <div class="max-w-xs mx-auto mb-8">
          <div class="flex items-center justify-between text-xs text-gray-500 font-body mb-2">
            <span class="font-display font-semibold">Progression</span>
            <span class="font-mono">~75%</span>
          </div>
          <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-anatt-500 via-gold-400 to-anatt-400 rounded-full" style="width:75%; animation: progressFill 4s ease-in-out infinite;"></div>
          </div>
          <p class="text-[11px] text-gray-400 font-body mt-2">Temps estime : ~{{ pad(minutes) }}:{{ pad(seconds) }}</p>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-2 gap-3 mb-8 max-w-sm mx-auto">
          <div class="bg-white border border-gray-100 rounded-xl p-4 text-left">
            <div class="flex items-center gap-2 mb-2">
              <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <p class="text-xs font-display font-bold text-gray-700">Debut</p>
            </div>
            <p class="text-sm font-display font-semibold text-gray-900">08:00</p>
            <p class="text-[11px] text-gray-400 font-body">Aujourd'hui</p>
          </div>
          <div class="bg-white border border-gray-100 rounded-xl p-4 text-left">
            <div class="flex items-center gap-2 mb-2">
              <div class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              </div>
              <p class="text-xs font-display font-bold text-gray-700">Fin prevue</p>
            </div>
            <p class="text-sm font-display font-semibold text-gray-900">10:30</p>
            <p class="text-[11px] text-gray-400 font-body">Aujourd'hui</p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col items-center gap-3">
          <button @click="location.reload()" class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 transition-colors shadow-lg shadow-anatt-500/20 w-full max-w-xs flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
            Verifier la disponibilite
          </button>
          <a href="mailto:support@anatt.bj" class="px-6 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-display font-semibold hover:bg-gray-100 transition-colors w-full max-w-xs inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            Contacter le support
          </a>
        </div>

        <!-- Notification subscribe -->
        <div class="mt-8 bg-anatt-50 border border-anatt-100 rounded-2xl p-4 max-w-sm mx-auto">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-anatt-100 rounded-xl flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
            </div>
            <div class="text-left">
              <p class="text-xs font-display font-bold text-anatt-800">Etre notifie du retour</p>
              <p class="text-[11px] text-anatt-600 font-body">Recevez un email quand le systeme sera retabli</p>
            </div>
          </div>
          <div class="flex mt-3 gap-2">
            <input v-model="notifyEmail" type="email" placeholder="votre@email.bj" class="flex-1 text-xs px-3 py-2 border border-anatt-200 rounded-lg focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none bg-white font-body transition-all duration-200">
            <button class="px-4 py-2 bg-anatt-500 text-white text-xs font-display font-semibold rounded-lg hover:bg-anatt-600 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
            </button>
          </div>
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
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
.lift-anim { animation: lift 2s ease-in-out infinite; }
@keyframes lift { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
.gear-anim { animation: gearRotate 5s linear infinite; }
@keyframes gearRotate { 0%{transform:rotate(0)} 100%{transform:rotate(360deg)} }
.sparkle-anim { animation: sparkle 1.5s ease-in-out infinite; }
@keyframes sparkle { 0%,100%{opacity:0;transform:scale(0)} 50%{opacity:1;transform:scale(1)} }
@keyframes progressFill { 0%{width:0} 50%{width:75%} 100%{width:100%} }
</style>
