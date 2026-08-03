<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const api = useApi()
const { success: toastSuccess } = useToast()
const loadingData = ref(true)
const saving = ref(false)

const appName = ref('Systeme de Paiement des Missions - ANaTT')
const appVersion = ref('1.0.0')
const fiscalYear = ref('2026')
const currency = ref('FCFA (XOF)')
const timezone = ref('Africa/Porto-Novo (UTC+1)')
const batchSize = ref(100)
const reconciliationDays = ref(7)
const sessionDuration = ref(30)
const maxLoginAttempts = ref(5)
const partAvance = ref(70)

const mtnEnabled = ref(true)
const moovEnabled = ref(true)
const doubleValidation = ref(true)
const forceHttps = ref(true)
const logConnections = ref(true)
const maintenanceMode = ref(false)

const notifications = ref([
  { key: 'mission_soumise', label: 'Nouvel ordre de mission', description: 'Notifier les validateurs', enabled: true },
  { key: 'mission_validee', label: 'Validation effectuee', description: 'Notifier le createur de l\'OM', enabled: true },
  { key: 'paiement_effectue', label: 'Paiement effectue', description: 'Notifier l\'agent', enabled: true },
  { key: 'paiement_echec', label: 'Echec de paiement', description: 'Alerter les administrateurs', enabled: true },
  { key: 'rapport_mensuel', label: 'Rapport mensuel auto', description: 'Generer et envoyer le 1er de chaque mois', enabled: false },
])

const activeSection = ref('general')
const sections = [
  { id: 'general', label: 'General', icon: 'building' },
  { id: 'paiements', label: 'Paiements', icon: 'credit-card' },
  { id: 'notifications', label: 'Notifications', icon: 'bell' },
  { id: 'securite', label: 'Securite', icon: 'shield' },
  { id: 'maintenance', label: 'Maintenance', icon: 'wrench' },
]

onMounted(async () => {
  try {
    const { data } = await api.get('/api/admin/settings')
    const s = data.data || {}
    if (s.appName) appName.value = s.appName
    if (s.appVersion) appVersion.value = s.appVersion
    if (s.fiscalYear) fiscalYear.value = s.fiscalYear
    if (s.batchSize) batchSize.value = s.batchSize
    if (s.reconciliationDays) reconciliationDays.value = s.reconciliationDays
    if (s.sessionDuration) sessionDuration.value = s.sessionDuration
    if (s.maxLoginAttempts) maxLoginAttempts.value = s.maxLoginAttempts
    if (s.partAvance) partAvance.value = s.partAvance
    if (s.mtnEnabled !== undefined) mtnEnabled.value = s.mtnEnabled
    if (s.moovEnabled !== undefined) moovEnabled.value = s.moovEnabled
    if (s.doubleValidation !== undefined) doubleValidation.value = s.doubleValidation
    if (s.forceHttps !== undefined) forceHttps.value = s.forceHttps
    if (s.logConnections !== undefined) logConnections.value = s.logConnections
    if (s.maintenanceMode !== undefined) maintenanceMode.value = s.maintenanceMode
    if (s.notifications) {
      for (const n of s.notifications) {
        const found = notifications.value.find(x => x.key === n.key)
        if (found) found.enabled = n.enabled
      }
    }
  } catch {
    // Premier chargement, pas de settings existants
  } finally {
    loadingData.value = false
  }
})

async function saveAll() {
  saving.value = true
  try {
    await api.put('/api/admin/settings', {
      appName: appName.value,
      appVersion: appVersion.value,
      fiscalYear: fiscalYear.value,
      batchSize: batchSize.value,
      reconciliationDays: reconciliationDays.value,
      sessionDuration: sessionDuration.value,
      maxLoginAttempts: maxLoginAttempts.value,
      partAvance: partAvance.value,
      mtnEnabled: mtnEnabled.value,
      moovEnabled: moovEnabled.value,
      doubleValidation: doubleValidation.value,
      forceHttps: forceHttps.value,
      logConnections: logConnections.value,
      maintenanceMode: maintenanceMode.value,
      notifications: notifications.value.map(n => ({ key: n.key, enabled: n.enabled })),
    })
    toastSuccess('Parametres enregistres avec succes.')
  } catch {
    // Geree par useApi
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppLayout title="Parametres du Systeme" subtitle="Configuration generale de l'application">
    <template #header-actions>
      <button :disabled="saving" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-60" @click="saveAll">
        <svg v-if="saving" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        {{ saving ? 'Enregistrement...' : 'Enregistrer tout' }}
      </button>
    </template>

    <div class="flex gap-6">
      <!-- Sidebar tabs -->
      <div class="w-56 shrink-0">
        <nav class="bg-white rounded-2xl border border-gray-100 p-2 space-y-1 sticky top-20">
          <button
            v-for="section in sections" :key="section.id"
            @click="activeSection = section.id"
            :class="activeSection === section.id ? 'bg-anatt-50 text-anatt-700 font-semibold' : 'text-gray-600 hover:bg-gray-50'"
            class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-display w-full text-left"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
            {{ section.label }}
          </button>
        </nav>
      </div>

      <!-- Settings Content -->
      <div class="flex-1 space-y-6">
        <!-- General -->
        <div v-show="activeSection === 'general'" class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
          <h3 class="text-base font-display font-bold text-gray-800 mb-5">Parametres generaux</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Nom de l'application</label><input v-model="appName" type="text" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Version</label><input v-model="appVersion" type="text" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body bg-gray-50 text-gray-500"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Annee fiscale en cours</label><select v-model="fiscalYear" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none"><option>2025</option><option>2024</option></select></div>
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Devise</label><input :value="currency" type="text" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body bg-gray-50 text-gray-500"></div>
            </div>
            <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Fuseau horaire</label><select class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none"><option>{{ timezone }}</option></select></div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Part de l'avance (%)</label><input v-model.number="partAvance" type="number" min="0" max="100" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Part du reliquat (%)</label><input :value="100 - partAvance" type="text" disabled class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body bg-gray-50 text-gray-500"></div>
            </div>
          </div>
        </div>

        <!-- Payments -->
        <div v-show="activeSection === 'paiements'" class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
          <h3 class="text-base font-display font-bold text-gray-800 mb-5">Configuration des paiements</h3>
          <div class="space-y-5">
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">MTN Mobile Money</p><p class="text-[11px] text-gray-500 font-body">API de paiement MTN MoMo</p></div>
              <div class="flex items-center gap-3">
                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-display font-semibold rounded-full">Connecte</span>
                <button @click="mtnEnabled = !mtnEnabled" :class="mtnEnabled ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                  <span :class="mtnEnabled ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
                </button>
              </div>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">MOOV Money</p><p class="text-[11px] text-gray-500 font-body">API de paiement MOOV Africa</p></div>
              <div class="flex items-center gap-3">
                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-[10px] font-display font-semibold rounded-full">Connecte</span>
                <button @click="moovEnabled = !moovEnabled" :class="moovEnabled ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                  <span :class="moovEnabled ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
                </button>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Taille max. d'un lot</label><input v-model.number="batchSize" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Delai de reconciliation (jours)</label><input v-model.number="reconciliationDays" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">Double validation requise</p><p class="text-[11px] text-gray-500 font-body">Exiger deux validations pour les lots > 5 000 000 FCFA</p></div>
              <button @click="doubleValidation = !doubleValidation" :class="doubleValidation ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                <span :class="doubleValidation ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Notifications -->
        <div v-show="activeSection === 'notifications'" class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
          <h3 class="text-base font-display font-bold text-gray-800 mb-5">Notifications</h3>
          <div class="space-y-3">
            <div v-for="notif in notifications" :key="notif.label" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">{{ notif.label }}</p><p class="text-[11px] text-gray-500 font-body">{{ notif.description }}</p></div>
              <button @click="notif.enabled = !notif.enabled" :class="notif.enabled ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                <span :class="notif.enabled ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Security -->
        <div v-show="activeSection === 'securite'" class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
          <h3 class="text-base font-display font-bold text-gray-800 mb-5">Securite</h3>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Duree de session (minutes)</label><input v-model.number="sessionDuration" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
              <div><label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Max tentatives connexion</label><input v-model.number="maxLoginAttempts" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"></div>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">Forcer HTTPS</p><p class="text-[11px] text-gray-500 font-body">Rediriger toutes les requetes vers HTTPS</p></div>
              <button @click="forceHttps = !forceHttps" :class="forceHttps ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                <span :class="forceHttps ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
              </button>
            </div>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
              <div><p class="text-sm font-display font-semibold text-gray-800">Journaliser les connexions</p><p class="text-[11px] text-gray-500 font-body">Enregistrer toutes les tentatives de connexion</p></div>
              <button @click="logConnections = !logConnections" :class="logConnections ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                <span :class="logConnections ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Maintenance -->
        <div v-show="activeSection === 'maintenance'" class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
          <h3 class="text-base font-display font-bold text-gray-800 mb-5">Maintenance</h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-amber-50 border border-amber-200 rounded-xl">
              <div>
                <p class="text-sm font-display font-semibold text-gray-800">Mode maintenance</p>
                <p class="text-[11px] text-amber-700 font-body flex items-center gap-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                  Rend le systeme inaccessible aux non-admins
                </p>
              </div>
              <button @click="maintenanceMode = !maintenanceMode" :class="maintenanceMode ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
                <span :class="maintenanceMode ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
              </button>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <button class="p-4 border border-gray-200 rounded-xl text-left hover:bg-gray-50 transition-colors">
                <p class="text-sm font-display font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                  Sauvegarder la base
                </p>
                <p class="text-[11px] text-gray-500 font-body mt-1">Derniere sauvegarde : 15/01/2025 02:00</p>
              </button>
              <button class="p-4 border border-gray-200 rounded-xl text-left hover:bg-gray-50 transition-colors">
                <p class="text-sm font-display font-semibold text-gray-800 flex items-center gap-2">
                  <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                  Nettoyer le cache
                </p>
                <p class="text-[11px] text-gray-500 font-body mt-1">Taille actuelle : 124 MB</p>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
