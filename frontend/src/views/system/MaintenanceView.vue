<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const hours = ref(0)
const minutes = ref(30)
const seconds = ref(0)
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
  <div class="min-h-screen bg-gradient-to-br from-anatt-800 via-anatt-700 to-anatt-900 bg-pattern flex items-center justify-center p-4">
    <div class="fixed top-0 left-0 right-0 h-1.5 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>
    <div class="text-center login-card max-w-lg">
      <div class="w-24 h-24 bg-gold-400/20 backdrop-blur-sm rounded-2xl mx-auto mb-6 flex items-center justify-center border border-gold-400/30">
        <svg class="w-12 h-12 text-gold-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
      </div>
      <h1 class="text-3xl font-display font-bold text-white mb-2">Maintenance en cours</h1>
      <p class="text-anatt-200 text-sm mb-8">Le systeme est en cours de maintenance. Nous serons de retour bientot.</p>

      <!-- Countdown -->
      <div class="flex items-center justify-center gap-4 mb-8">
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 min-w-[80px] border border-white/20">
          <p class="text-3xl font-display font-bold text-white">{{ pad(hours) }}</p>
          <p class="text-xs text-anatt-200 mt-1">Heures</p>
        </div>
        <span class="text-2xl text-white font-bold">:</span>
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 min-w-[80px] border border-white/20">
          <p class="text-3xl font-display font-bold text-white">{{ pad(minutes) }}</p>
          <p class="text-xs text-anatt-200 mt-1">Minutes</p>
        </div>
        <span class="text-2xl text-white font-bold">:</span>
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 min-w-[80px] border border-white/20">
          <p class="text-3xl font-display font-bold text-white">{{ pad(seconds) }}</p>
          <p class="text-xs text-anatt-200 mt-1">Secondes</p>
        </div>
      </div>

      <div class="bg-white/10 rounded-xl p-4 max-w-sm mx-auto">
        <p class="text-sm text-anatt-100">Pour toute urgence, contactez l'equipe technique a support@anatt.bj</p>
      </div>
    </div>
  </div>
</template>
