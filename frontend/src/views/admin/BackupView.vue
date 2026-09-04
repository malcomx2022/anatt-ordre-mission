<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import { useToast } from '@/composables/useToast'

const api = useApi()
const { success: toastSuccess } = useToast()
const loadingData = ref(true)
const creatingBackup = ref(false)

interface BackupEntry {
  name: string
  date: string
  size: string
  type: string
  status: string
}

const backups = ref<BackupEntry[]>([])

const stats = computed(() => [
  { label: 'Derniere sauvegarde', value: backups.value[0]?.date ?? '--', iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { label: 'Sauvegardes stockees', value: String(backups.value.length), iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
  { label: 'Sauvegarde auto (serveur)', value: '02:00', iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
])

async function loadBackups() {
  try {
    const { data } = await api.get('/api/admin/backups')
    backups.value = data.data || []
  } catch {
    // Pas de backups ou endpoint pas encore disponible
  }
}

onMounted(async () => {
  await loadBackups()
  loadingData.value = false
})

async function createBackup() {
  creatingBackup.value = true
  try {
    await api.post('/api/admin/backups')
    toastSuccess('Sauvegarde creee avec succes.')
    await loadBackups()
  } catch {
    // Geree par useApi
  } finally {
    creatingBackup.value = false
  }
}

async function deleteBackup(name: string) {
  if (!confirm(`Supprimer la sauvegarde ${name} ?`)) return
  try {
    await api.delete(`/api/admin/backups/${encodeURIComponent(name)}`)
    await loadBackups()
  } catch {
    // Geree par useApi
  }
}
</script>

<template>
  <AppLayout title="Sauvegardes" subtitle="Gestion des sauvegardes de la base de donnees">
    <template #header-actions>
      <button @click="createBackup" :disabled="creatingBackup" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2 disabled:opacity-50">
        <svg v-if="!creatingBackup" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
        {{ creatingBackup ? 'Sauvegarde en cours...' : 'Nouvelle sauvegarde' }}
      </button>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6 animate-fade-up">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-gray-100 p-5">
        <div :class="[stat.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center mb-3']">
          <svg :class="[stat.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 font-body">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Auto Backup — piloté par le cron serveur (informatif) -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <h3 class="text-base font-display font-bold text-gray-800 mb-3">Sauvegarde automatique</h3>
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
        <p class="text-sm text-blue-800 font-body">
          La sauvegarde automatique quotidienne (02:00) est planifiée côté serveur via le
          <strong>cron</strong> (commande <code class="px-1 py-0.5 bg-blue-100 rounded text-[12px] font-mono">db:backup</code>).
          Sa fréquence et la rétention se règlent dans la planification serveur, hors application. Utilisez le bouton
          <strong>« Nouvelle sauvegarde »</strong> ci-dessus pour déclencher une sauvegarde manuelle immédiate.
        </p>
      </div>
    </div>

    <!-- Backup List -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-sm font-display font-semibold text-gray-700">Historique des sauvegardes</h3></div>
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-display font-semibold text-gray-500 uppercase">Fichier</th>
            <th class="px-5 py-3 text-left text-xs font-display font-semibold text-gray-500 uppercase">Date</th>
            <th class="px-5 py-3 text-center text-xs font-display font-semibold text-gray-500 uppercase">Taille</th>
            <th class="px-5 py-3 text-center text-xs font-display font-semibold text-gray-500 uppercase">Type</th>
            <th class="px-5 py-3 text-center text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
            <th class="px-5 py-3 text-center text-xs font-display font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="backup in backups" :key="backup.name" class="hover:bg-anatt-50/30 transition-colors group">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375" /></svg>
                </div>
                <span class="text-sm font-body font-medium text-gray-800 font-mono text-xs">{{ backup.name }}</span>
              </div>
            </td>
            <td class="px-5 py-3 text-sm font-body text-gray-600">{{ backup.date }}</td>
            <td class="px-5 py-3 text-center text-sm font-display font-semibold text-gray-700">{{ backup.size }}</td>
            <td class="px-5 py-3 text-center"><span :class="backup.type === 'Manuel' ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600'" class="px-2 py-0.5 text-[10px] font-display font-semibold rounded-full">{{ backup.type }}</span></td>
            <td class="px-5 py-3 text-center"><span class="px-2 py-0.5 bg-green-50 text-green-700 text-[10px] font-display font-semibold rounded-full">{{ backup.status }}</span></td>
            <td class="px-5 py-3 text-center">
              <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500" title="Supprimer" @click="deleteBackup(backup.name)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
