<script setup lang="ts">
import { NOTIFICATIONS_CIRCUIT } from '@/utils/notifications'
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi, getErrorMessage, getValidationErrors } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const api = useApi()
const { success: toastSuccess, error: toastError } = useToast()
const loadingData = ref(true)
const saving = ref(false)
const testing = ref(false)

const smtpHost = ref('')
const smtpPort = ref(587)
const smtpUser = ref('')
const smtpPassword = ref('')
const smtpEncryption = ref('TLS')
const fromAddress = ref('')
const fromName = ref('ANaTT Missions')

// Notifications réellement envoyées : source unique, miroir de NotificationContenu.
const templates = NOTIFICATIONS_CIRCUIT

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

/**
 * Motif d'un refus : l'API répond 422 « Données invalides. » et met la raison
 * (champ refusé, message du serveur SMTP) dans `errors` — c'est elle qu'on affiche.
 */
function motif(e: unknown): string {
  return Object.values(getValidationErrors(e))[0]?.[0] ?? getErrorMessage(e)
}

/** Enregistre la configuration ; true si le serveur l'a acceptée (422 sinon, affiché). */
async function saveConfig(silencieux = false): Promise<boolean> {
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
    if (!silencieux) toastSuccess('Configuration e-mail enregistrée.')
    return true
  } catch (e) {
    // Valeur refusée par le serveur (422) : le message dit laquelle.
    toastError(motif(e))
    return false
  } finally {
    saving.value = false
  }
}

/** Enregistre puis envoie un e-mail de test au compte connecté, avec cette configuration. */
async function envoyerTest() {
  if (!(await saveConfig(true))) return
  testing.value = true
  try {
    const { data } = await api.post('/api/admin/settings/test-email')
    toastSuccess(data.message || 'E-mail de test envoyé.')
  } catch (e) {
    toastError(motif(e))
  } finally {
    testing.value = false
  }
}
</script>

<template>
  <AppLayout title="Configuration e-mail" subtitle="Serveur SMTP, expéditeur et notifications envoyées">
    <template #header-actions>
      <button :disabled="saving || testing" class="px-4 py-2.5 border-2 border-anatt-500 text-anatt-600 rounded-xl text-sm font-display font-semibold hover:bg-anatt-500 hover:text-white flex items-center gap-2 transition-all duration-200 disabled:opacity-60" @click="envoyerTest">
        <svg class="w-4 h-4" :class="{ 'animate-pulse': testing }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
        {{ testing ? 'Envoi…' : 'Envoyer un e-mail de test' }}
      </button>
      <button :disabled="saving || testing" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-60" @click="saveConfig()">
        <svg v-if="saving" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
      </button>
    </template>

    <!-- Bandeau : ce que la configuration change à l'envoi -->
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 mb-6 flex items-start gap-3 animate-fade-up">
      <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
      <div class="text-sm text-blue-800 font-body space-y-1">
        <p>
          <strong>Serveur renseigné</strong> : il remplace, dès l'e-mail suivant, le transport du fichier
          <code class="px-1 py-0.5 bg-blue-100 rounded text-[12px] font-mono">.env</code> du serveur
          (variables <code class="px-1 py-0.5 bg-blue-100 rounded text-[12px] font-mono">MAIL_*</code>) pour les
          notifications du circuit et le lien de réinitialisation du mot de passe. <strong>Serveur vide</strong> : le
          <code class="px-1 py-0.5 bg-blue-100 rounded text-[12px] font-mono">.env</code> s'applique.
        </p>
        <p>L'adresse et le nom d'expéditeur, s'ils sont renseignés, remplacent ceux du <code class="px-1 py-0.5 bg-blue-100 rounded text-[12px] font-mono">.env</code>. « Envoyer un e-mail de test » enregistre puis écrit à votre adresse.</p>
      </div>
    </div>

    <!-- SMTP Configuration -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
        <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
        Configuration SMTP
      </h3>
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Serveur SMTP</label>
          <input v-model="smtpHost" type="text" placeholder="mail.anatt.bj (vide : .env)" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
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
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Adresse d'expéditeur</label>
          <input v-model="fromAddress" type="email" placeholder="noreply@anatt.bj" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
        <div>
          <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Nom d'expéditeur</label>
          <input v-model="fromName" type="text" maxlength="100" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
        </div>
      </div>
    </div>

    <!-- Email Templates -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-display font-semibold text-gray-700">Templates de notification</h3>
        <p class="text-[11px] text-gray-400 font-body mt-0.5">Modèles définis dans le code (<code>NotificationContenu</code>), communs à la cloche et à l'e-mail — présentés ici à titre de référence.</p>
      </div>
      <div class="divide-y divide-gray-50">
        <div v-for="tpl in templates" :key="tpl.id" class="px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-anatt-50 rounded-xl flex items-center justify-center">
              <svg class="w-5 h-5 text-anatt-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
            </div>
            <div>
              <p class="text-sm font-display font-semibold text-gray-800">{{ tpl.name }}</p>
              <p class="text-[11px] text-gray-400 font-body">Sujet : {{ tpl.subject }}</p>
              <p class="text-[11px] text-gray-400 font-body">Destinataires : comptes actifs avec <code>{{ tpl.destinataires }}</code></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
