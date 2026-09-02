<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const api = useApi()
const { success: toastSuccess } = useToast()
const loadingData = ref(true)
const saving = ref(false)

const smtpHost = ref('')
const smtpPort = ref(587)
const smtpUser = ref('')
const smtpPassword = ref('')
const smtpEncryption = ref('TLS')
const fromAddress = ref('')
const fromName = ref('ANaTT Missions')
const testEmail = ref('')
const testSending = ref(false)
const testSuccess = ref(false)
const testError = ref('')

// Templates derives du SendNotification job (pas de table, definis dans le code)
const templates = [
  { id: 1, name: 'Mission soumise', subject: 'Nouvel OM {numero} - En attente de validation', type: 'mission_soumise' },
  { id: 2, name: 'Mission validee', subject: 'OM {numero} valide par le chef de service', type: 'mission_validee' },
  { id: 3, name: 'Mission approuvee', subject: 'OM {numero} approuve par la DG', type: 'mission_approuvee' },
  { id: 4, name: 'Mission rejetee', subject: 'OM {numero} rejete', type: 'mission_rejetee' },
  { id: 5, name: 'Paiement effectue', subject: 'Paiement de {montant} FCFA effectue via {operateur}', type: 'paiement_effectue' },
  { id: 6, name: 'Paiement echoue', subject: 'Echec paiement OM {numero}', type: 'paiement_echec' },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/admin/settings')
    const s = data.data || {}
    if (s.smtpHost) smtpHost.value = s.smtpHost
    if (s.smtpPort) smtpPort.value = s.smtpPort
    if (s.smtpUser) smtpUser.value = s.smtpUser
    // Le backend renvoie un masque ('********') si un mot de passe est enregistré ;
    // on le reflète dans le champ sans jamais exposer le secret réel.
    if (s.smtpPassword) smtpPassword.value = s.smtpPassword
    if (s.smtpEncryption) smtpEncryption.value = s.smtpEncryption
    if (s.fromAddress) fromAddress.value = s.fromAddress
    if (s.fromName) fromName.value = s.fromName
  } catch {
    // Pas de settings existants
  } finally {
    loadingData.value = false
  }
})

async function saveConfig() {
  saving.value = true
  try {
    // Charger les settings existants puis merger
    const { data: current } = await api.get('/api/admin/settings')
    const merged = {
      ...current.data,
      smtpHost: smtpHost.value,
      smtpPort: smtpPort.value,
      smtpUser: smtpUser.value,
      smtpPassword: smtpPassword.value !== '********' ? smtpPassword.value : undefined,
      smtpEncryption: smtpEncryption.value,
      fromAddress: fromAddress.value,
      fromName: fromName.value,
    }
    await api.put('/api/admin/settings', merged)
    toastSuccess('Configuration email enregistree.')
  } catch {
    // Geree par useApi
  } finally {
    saving.value = false
  }
}

async function sendTestEmailFn() {
  if (!testEmail.value) return
  testSending.value = true
  testSuccess.value = false
  testError.value = ''
  try {
    // Envoyer un email de test via l'API
    await api.post('/api/auth/mot-de-passe/email', { email: testEmail.value })
    testSuccess.value = true
    setTimeout(() => { testSuccess.value = false }, 5000)
  } catch {
    testError.value = 'Echec de l\'envoi. Verifiez la configuration SMTP.'
    setTimeout(() => { testError.value = '' }, 5000)
  } finally {
    testSending.value = false
  }
}
</script>

<template>
  <AppLayout title="Configuration Email" subtitle="Parametres SMTP et templates de notifications">
    <template #header-actions>
      <button :disabled="saving" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-60" @click="saveConfig">
        <svg v-if="saving" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
      </button>
    </template>

    <!-- SMTP Configuration -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
      <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
        <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
        Configuration SMTP
      </h3>
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Serveur SMTP</label>
          <input v-model="smtpHost" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Port</label>
          <input v-model.number="smtpPort" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Nom d'utilisateur</label>
          <input v-model="smtpUser" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Mot de passe</label>
          <input v-model="smtpPassword" type="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Chiffrement</label>
          <select v-model="smtpEncryption" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none">
            <option>TLS</option><option>SSL</option><option>Aucun</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Adresse d'expediteur</label>
          <input v-model="fromAddress" type="email" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
      </div>

      <!-- Test email -->
      <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
        <input v-model="testEmail" type="email" placeholder="Adresse email de test..." class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200 bg-white">
        <button @click="sendTestEmailFn" :disabled="testSending" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-50">
          <svg v-if="!testSending" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
          <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
          {{ testSending ? 'Envoi...' : 'Envoyer un test' }}
        </button>
      </div>
      <div v-if="testSuccess" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700 font-body flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
        Email de test envoye avec succes !
      </div>
      <div v-if="testError" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-body flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
        {{ testError }}
      </div>
    </div>

    <!-- Email Templates -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-100">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-display font-semibold text-gray-700">Templates de notification</h3>
        <button class="text-xs text-anatt-500 font-display font-semibold hover:underline flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
          Nouveau template
        </button>
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="tpl in templates" :key="tpl.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50/50 group">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-anatt-50 rounded-xl flex items-center justify-center">
              <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            </div>
            <div>
              <p class="text-sm font-display font-semibold text-gray-800">{{ tpl.name }}</p>
              <p class="text-[11px] text-gray-400 font-body">Sujet : {{ tpl.subject }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="px-2 py-0.5 bg-green-50 text-green-700 text-[10px] font-display font-semibold rounded-full">Actif</span>
            <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
