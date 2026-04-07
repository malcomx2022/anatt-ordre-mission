<script setup lang="ts">
import { ref } from 'vue'
import { useApi } from '@/composables/useApi'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const errorMsg = ref('')

async function handleSubmit() {
  loading.value = true
  errorMsg.value = ''
  try {
    await useApi().post('/api/auth/mot-de-passe/email', { email: email.value })
    sent.value = true
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Erreur lors de l\'envoi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="font-body min-h-screen bg-gradient-to-br from-anatt-800 via-anatt-700 to-anatt-900 bg-pattern flex items-center justify-center p-4">
    <div class="fixed top-0 left-0 right-0 h-1.5 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>

    <div class="w-full max-w-md login-card">
      <div class="text-center mb-8">
        <div class="w-20 h-20 bg-white/10 backdrop-blur-sm rounded-2xl mx-auto mb-4 flex items-center justify-center border border-white/20">
          <svg class="w-8 h-8 text-gold-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15.5 7.5 2.3 2.3a1 1 0 0 1 0 1.4l-9.1 9.1-4.4.6.6-4.4 9.2-9.1a1 1 0 0 1 1.4 0z"/><path d="m2.5 21.5 3-3"/></svg>
        </div>
        <h1 class="text-2xl font-display font-bold text-white">Mot de passe oublie</h1>
        <p class="text-anatt-200 text-sm mt-1">Recuperez l'acces a votre compte</p>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <!-- Form state -->
        <div v-if="!sent">
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex gap-3">
              <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
              <p class="text-sm text-blue-700">Entrez votre adresse e-mail. Un lien de reinitialisation vous sera envoye.</p>
            </div>
          </div>

          <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">
            <span class="text-sm text-red-700">{{ errorMsg }}</span>
          </div>

          <form @submit.prevent="handleSubmit">
            <div class="mb-6">
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Adresse e-mail</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                </div>
                <input v-model="email" type="email" placeholder="votre.email@anatt.bj" required class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all">
              </div>
            </div>
            <button type="submit" :disabled="loading" class="w-full bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60">
              <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              <svg v-else class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
              <span>Envoyer le lien</span>
            </button>
          </form>
        </div>

        <!-- Success state -->
        <div v-else class="text-center py-4">
          <div class="w-16 h-16 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-7 h-7 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <h3 class="text-lg font-display font-bold text-gray-800 mb-2">E-mail envoye !</h3>
          <p class="text-gray-500 text-sm mb-6">Un lien de reinitialisation a ete envoye a votre adresse e-mail. Verifiez votre boite de reception.</p>
          <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4">
            <p class="text-sm text-amber-700">Le lien expire dans 60 minutes</p>
          </div>
          <button class="text-anatt-500 hover:text-anatt-600 font-display font-medium text-sm" @click="sent = false">Renvoyer l'e-mail</button>
        </div>

        <div class="mt-6 text-center">
          <router-link to="/login" class="text-sm text-gray-500 hover:text-anatt-500 font-display font-medium">
            <svg class="w-3 h-3 inline mr-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Retour a la connexion
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
