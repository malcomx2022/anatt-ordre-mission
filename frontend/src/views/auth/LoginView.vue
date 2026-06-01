<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { getErrorMessage } from '@/composables/useApi'
import AppLogo from '@/components/shared/AppLogo.vue'

const router = useRouter()
const route = useRoute()
const { login, fetchUser } = useAuth()

const email = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  loading.value = true
  errorMsg.value = ''
  try {
    await login(email.value, password.value)
    await fetchUser()
    const redirect = (route.query.redirect as string) || '/'
    router.push(redirect)
  } catch (e: unknown) {
    errorMsg.value = getErrorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="font-body min-h-screen bg-gradient-to-br from-anatt-800 via-anatt-700 to-anatt-900 bg-pattern flex items-center justify-center p-4">
    <!-- Flag bar -->
    <div class="fixed top-0 left-0 right-0 h-1.5 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>

    <div class="w-full max-w-md login-card">
      <!-- Logo ANaTT (version couleur sur badge blanc pour ressortir sur le fond vert) -->
      <div class="flex justify-center mb-8 animate-float">
        <div class="bg-white rounded-2xl shadow-xl px-8 py-5">
          <AppLogo size="lg" />
        </div>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-6">
          <h2 class="text-xl font-display font-bold text-gray-800">Connexion</h2>
          <p class="text-gray-500 text-sm mt-1">Accedez a votre espace de travail</p>
        </div>

        <!-- Alert error -->
        <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <span class="text-sm text-red-700">{{ errorMsg }}</span>
          </div>
        </div>

        <form @submit.prevent="handleLogin">
          <!-- Email -->
          <div class="mb-4">
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Adresse e-mail</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              </div>
              <input
                v-model="email"
                type="email"
                placeholder="votre.email@anatt.bj"
                required
                class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all"
              >
            </div>
          </div>

          <!-- Password -->
          <div class="mb-4">
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Mot de passe</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </div>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                required
                class="w-full pl-10 pr-12 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all"
              >
              <button type="button" class="absolute inset-y-0 right-0 pr-3.5 flex items-center" @click="showPassword = !showPassword">
                <svg class="w-4 h-4 text-gray-400 hover:text-gray-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>

          <!-- Remember + Forgot -->
          <div class="flex items-center justify-between mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="remember" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
              <span class="text-sm text-gray-600">Se souvenir</span>
            </label>
            <router-link to="/mot-de-passe-oublie" class="text-sm text-anatt-500 hover:text-anatt-600 font-display font-medium">
              Mot de passe oublie ?
            </router-link>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60"
          >
            <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span>{{ loading ? 'Connexion...' : 'Se connecter' }}</span>
            <svg v-if="!loading" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>
        </form>
      </div>

      <p class="text-center text-anatt-200 text-xs mt-6">© 2026 ANaTT - Agence Nationale des Transports Terrestres - Benin</p>
    </div>
  </div>
</template>
