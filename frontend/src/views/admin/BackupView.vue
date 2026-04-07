<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const autoBackupEnabled = ref(true)
const backupFrequency = ref('daily')
const retentionDays = ref(30)
const creatingBackup = ref(false)

const backups = ref([
  { id: 1, name: 'backup_2025-01-15_020000.sql.gz', date: '15/01/2025 02:00', size: '45.2 MB', type: 'Automatique', status: 'Complet' },
  { id: 2, name: 'backup_2025-01-14_020000.sql.gz', date: '14/01/2025 02:00', size: '44.8 MB', type: 'Automatique', status: 'Complet' },
  { id: 3, name: 'backup_2025-01-13_143200.sql.gz', date: '13/01/2025 14:32', size: '44.5 MB', type: 'Manuel', status: 'Complet' },
  { id: 4, name: 'backup_2025-01-13_020000.sql.gz', date: '13/01/2025 02:00', size: '44.5 MB', type: 'Automatique', status: 'Complet' },
  { id: 5, name: 'backup_2025-01-12_020000.sql.gz', date: '12/01/2025 02:00', size: '44.1 MB', type: 'Automatique', status: 'Complet' },
])

const stats = [
  { label: 'Derniere sauvegarde', value: 'Il y a 14h', iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { label: 'Taille totale', value: '1.2 GB', iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'Sauvegardes stockees', value: '30', iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
  { label: 'Prochaine auto', value: '02:00', iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
]

function createBackup() {
  creatingBackup.value = true
  setTimeout(() => {
    creatingBackup.value = false
    backups.value.unshift({
      id: Date.now(),
      name: `backup_2025-01-15_${new Date().toTimeString().slice(0,8).replace(/:/g,'')}.sql.gz`,
      date: '15/01/2025 ' + new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
      size: '45.3 MB',
      type: 'Manuel',
      status: 'Complet',
    })
  }, 3000)
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
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-gray-100 p-5">
        <div :class="[stat.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center mb-3']">
          <svg :class="[stat.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 font-body">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Auto Backup Settings -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <h3 class="text-base font-display font-bold text-gray-800 mb-5">Sauvegarde automatique</h3>
      <div class="space-y-4">
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
          <div><p class="text-sm font-display font-semibold text-gray-800">Activer la sauvegarde automatique</p><p class="text-[11px] text-gray-500 font-body">Sauvegarde programmee selon la frequence definie</p></div>
          <button @click="autoBackupEnabled = !autoBackupEnabled" :class="autoBackupEnabled ? 'bg-anatt-500' : 'bg-gray-300'" class="w-11 h-6 rounded-full relative transition-colors duration-300">
            <span :class="autoBackupEnabled ? 'translate-x-5' : 'translate-x-0.5'" class="block w-5 h-5 bg-white rounded-full shadow transition-transform duration-300"></span>
          </button>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Frequence</label>
            <select v-model="backupFrequency" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none">
              <option value="daily">Quotidienne (02:00)</option>
              <option value="weekly">Hebdomadaire (Dimanche 02:00)</option>
              <option value="monthly">Mensuelle (1er du mois 02:00)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-body font-medium text-gray-600 mb-1.5">Retention (jours)</label>
            <input v-model.number="retentionDays" type="number" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
        </div>
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
          <tr v-for="backup in backups" :key="backup.id" class="hover:bg-anatt-50/30 transition-colors group">
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
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600" title="Telecharger">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                </button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-600" title="Restaurer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                </button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500" title="Supprimer">
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
