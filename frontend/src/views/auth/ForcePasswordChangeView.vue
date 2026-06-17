<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'
import { getErrorMessage } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'
import AppLogo from '@/components/shared/AppLogo.vue'

const router = useRouter()
const auth = useAuthStore()
const { success } = useToast()

const currentPassword = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const errorMsg = ref('')

async function handleSubmit() {
  loading.value = true
  errorMsg.value = ''
  try {
    await authService.changePassword({
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    // Recharge le profil : must_change_password repasse à false côté serveur.
    await auth.fetchUser()
    success('Mot de passe mis à jour.')
    router.push('/')
  } catch (e: unknown) {
    errorMsg.value = getErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="font-body min-h-screen bg-gradient-to-br from-anatt-800 via-anatt-700 to-anatt-900 bg-pattern flex items-center justify-center p-4">
    <div class="fixed top-0 left-0 right-0 h-1.5 flex z-50">
      <div class="flex-1 bg-benin-green"></div>
      <div class="flex-1 bg-gold-400"></div>
      <div class="flex-1 bg-benin-red"></div>
    </div>

    <div class="w-full max-w-md">
      <div class="flex justify-center mb-8">
        <div class="bg-white rounded-2xl shadow-xl px-8 py-5">
          <AppLogo size="lg" />
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-6">
          <h2 class="text-xl font-display font-bold text-gray-800">Changement de mot de passe requis</h2>
          <p class="text-gray-500 text-sm mt-1">
            Pour des raisons de sécurité, vous devez définir un nouveau mot de passe avant d'accéder à l'application.
          </p>
        </div>

        <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">
          <span class="text-sm text-red-700">{{ errorMsg }}</span>
        </div>

        <form @submit.prevent="handleSubmit">
          <div class="mb-4">
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Mot de passe actuel</label>
            <input
              v-model="currentPassword"
              type="password"
              required
              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all"
            >
          </div>

          <div class="mb-4">
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Nouveau mot de passe</label>
            <input
              v-model="password"
              type="password"
              required
              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all"
            >
            <p class="text-xs text-gray-400 mt-1">Au moins 12 caractères, majuscules, minuscules, chiffres et symboles.</p>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Confirmer le nouveau mot de passe</label>
            <input
              v-model="passwordConfirmation"
              type="password"
              required
              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all"
            >
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60"
          >
            <svg v-if="loading" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span>{{ loading ? 'Enregistrement...' : 'Mettre à jour le mot de passe' }}</span>
          </button>
        </form>

        <button type="button" class="w-full mt-3 text-sm text-gray-500 hover:text-gray-700 font-display" @click="handleLogout">
          Se déconnecter
        </button>
      </div>
    </div>
  </div>
</template>
