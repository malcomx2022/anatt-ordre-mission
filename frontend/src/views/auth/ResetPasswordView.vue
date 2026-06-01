<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import AppLogo from '@/components/shared/AppLogo.vue'

const route = useRoute()
const router = useRouter()

const password = ref('')
const passwordConfirm = ref('')
const loading = ref(false)
const done = ref(false)
const errorMsg = ref('')

const strength = computed(() => {
  let s = 0
  if (password.value.length >= 8) s++
  if (/[A-Z]/.test(password.value)) s++
  if (/[0-9]/.test(password.value)) s++
  if (/[^A-Za-z0-9]/.test(password.value)) s++
  return s
})

const strengthLabel = computed(() => ['', 'Faible', 'Moyen', 'Bon', 'Excellent'][strength.value] || '')
const strengthColor = computed(() => ['', 'bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'][strength.value] || '')
const passwordsMatch = computed(() => password.value === passwordConfirm.value && passwordConfirm.value.length > 0)

const rules = computed(() => [
  { label: 'Au moins 8 caracteres', ok: password.value.length >= 8 },
  { label: 'Une lettre majuscule', ok: /[A-Z]/.test(password.value) },
  { label: 'Un chiffre', ok: /[0-9]/.test(password.value) },
  { label: 'Un caractere special (@#$...)', ok: /[^A-Za-z0-9]/.test(password.value) },
])

async function handleSubmit() {
  if (!passwordsMatch.value) return
  loading.value = true
  errorMsg.value = ''
  try {
    await useApi().post('/api/auth/mot-de-passe/reset', {
      token: route.params.token,
      password: password.value,
      password_confirmation: passwordConfirm.value,
    })
    done.value = true
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Erreur lors de la reinitialisation.'
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
      <div class="flex justify-center mb-8">
        <div class="bg-white rounded-2xl shadow-xl px-8 py-5">
          <AppLogo size="lg" />
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div v-if="!done">
          <div v-if="errorMsg" class="bg-red-50 border border-red-200 rounded-xl p-3 mb-4">
            <span class="text-sm text-red-700">{{ errorMsg }}</span>
          </div>

          <form @submit.prevent="handleSubmit">
            <div class="mb-4">
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Nouveau mot de passe</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <input v-model="password" type="password" placeholder="••••••••" required class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all">
              </div>
              <div class="mt-2 flex gap-1">
                <div v-for="i in 4" :key="i" class="h-1.5 flex-1 rounded-full transition-all" :class="i <= strength ? strengthColor : 'bg-gray-200'" />
              </div>
              <p class="text-xs text-gray-400 mt-1">{{ strengthLabel || 'Entrez un mot de passe' }}</p>
            </div>

            <div class="mb-4">
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Confirmer le mot de passe</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <input v-model="passwordConfirm" type="password" placeholder="••••••••" required class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20 transition-all">
              </div>
              <p v-if="passwordConfirm.length > 0" class="text-xs mt-1" :class="passwordsMatch ? 'text-green-600' : 'text-red-500'">
                {{ passwordsMatch ? 'Les mots de passe correspondent' : 'Les mots de passe ne correspondent pas' }}
              </p>
            </div>

            <div class="bg-gray-50 rounded-xl p-3 mb-6 text-xs text-gray-500 space-y-1">
              <p v-for="rule in rules" :key="rule.label">
                <svg class="w-3 h-3 inline mr-1" :class="rule.ok ? 'text-green-500' : 'text-gray-300'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                {{ rule.label }}
              </p>
            </div>

            <button type="submit" :disabled="loading || !passwordsMatch" class="w-full bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-anatt-500/30 disabled:opacity-60">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              <span>Reinitialiser</span>
            </button>
          </form>
        </div>

        <div v-else class="text-center py-4">
          <div class="w-16 h-16 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-7 h-7 text-green-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          </div>
          <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Mot de passe modifie !</h3>
          <p class="text-gray-500 text-sm mb-6">Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</p>
          <router-link to="/login" class="inline-flex items-center gap-2 bg-anatt-500 hover:bg-anatt-600 text-white font-display font-semibold py-3 px-6 rounded-xl transition-all">
            Se connecter
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
