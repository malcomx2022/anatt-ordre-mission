<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const user = ref({
  initials: 'GA',
  fullName: 'Gislain ADJAHO',
  role: 'Super Administrateur',
  direction: "Direction des Systemes d'Information",
  fonction: 'Chef de Service Informatique',
  email: 'gislain.adjaho@anatt.bj',
  telephone: '+229 97 XX XX XX',
  dateCreation: '15/03/2024'
})

const permissions = ref([
  'Gestion utilisateurs',
  'Gestion des roles',
  'Parametres systeme',
  'Tous les modules'
])

const recentActivity = ref([
  { icon: 'check', color: 'green', text: 'Validation OM-2025-0089', time: 'Il y a 2 heures' },
  { icon: 'edit', color: 'amber', text: 'Modif. bareme Cat. A', time: 'Il y a 3 heures' },
  { icon: 'login', color: 'blue', text: 'Connexion', time: 'Ce matin, 08:45' }
])

const sessions = ref([
  { device: 'Chrome -- Windows', ip: '192.168.1.45', location: 'Cotonou', current: true, time: '' },
  { device: 'Safari -- iPhone', ip: '10.0.0.15', location: '', current: false, time: 'Hier 19:22' }
])

const passwordForm = ref({
  current: '',
  nouveau: '',
  confirm: ''
})

function updatePassword() {
  passwordForm.value = { current: '', nouveau: '', confirm: '' }
}

function revokeSession(index: number) {
  sessions.value.splice(index, 1)
}

function getActivityIcon(icon: string) {
  switch (icon) {
    case 'check':
      return '<polyline points="20 6 9 17 4 12"/>'
    case 'edit':
      return '<path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>'
    case 'login':
      return '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>'
    default:
      return ''
  }
}

function getActivityColorClass(color: string) {
  const map: Record<string, string> = {
    green: 'bg-green-100 text-green-600',
    amber: 'bg-amber-100 text-amber-600',
    blue: 'bg-blue-100 text-blue-600'
  }
  return map[color] || map.blue
}
</script>

<template>
  <AppLayout title="Mon Profil" subtitle="Gerer vos informations et preferences">
    <div class="max-w-4xl mx-auto">
      <!-- Profile Header -->
      <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden mb-6 animate-fade-up">
        <div class="h-28 bg-gradient-to-r from-anatt-500 via-anatt-600 to-anatt-800 relative">
          <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-black/20 to-transparent"></div>
        </div>
        <div class="px-6 pb-6 -mt-10 relative">
          <div class="flex items-end justify-between">
            <div class="flex items-end gap-5">
              <div class="w-20 h-20 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-white text-2xl font-display font-bold border-4 border-white shadow-lg">
                {{ user.initials }}
              </div>
              <div class="pb-1">
                <h2 class="text-xl font-display font-bold text-gray-900">{{ user.fullName }}</h2>
                <p class="text-sm font-body text-gray-500">{{ user.role }} -- {{ user.direction }}</p>
              </div>
            </div>
            <span class="px-3 py-1.5 bg-green-100 text-green-700 text-xs font-display font-semibold rounded-full flex items-center gap-1">
              <svg class="w-2 h-2 fill-current" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              En ligne
            </span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Personal Info -->
          <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
            <div class="flex items-center justify-between mb-5">
              <h3 class="text-base font-display font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Informations personnelles
              </h3>
              <button class="text-sm text-anatt-500 font-display font-semibold hover:text-anatt-600 flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                Modifier
              </button>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Nom complet</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.fullName }}</p>
              </div>
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Email</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.email }}</p>
              </div>
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Telephone</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.telephone }}</p>
              </div>
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Direction</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.direction }}</p>
              </div>
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Fonction</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.fonction }}</p>
              </div>
              <div>
                <p class="text-[10px] uppercase font-display text-gray-400 font-semibold mb-1">Date de creation</p>
                <p class="text-sm font-body font-medium text-gray-800">{{ user.dateCreation }}</p>
              </div>
            </div>
          </div>

          <!-- Change Password -->
          <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-200">
            <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
              <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Changer le mot de passe
            </h3>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Mot de passe actuel</label>
                <input
                  v-model="passwordForm.current"
                  type="password"
                  placeholder="--------"
                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
                >
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Nouveau mot de passe</label>
                  <input
                    v-model="passwordForm.nouveau"
                    type="password"
                    placeholder="--------"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
                  >
                </div>
                <div>
                  <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Confirmer</label>
                  <input
                    v-model="passwordForm.confirm"
                    type="password"
                    placeholder="--------"
                    class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
                  >
                </div>
              </div>
              <button
                class="px-5 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600"
                @click="updatePassword"
              >
                Mettre a jour
              </button>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
          <!-- Role & Permissions -->
          <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-up delay-100">
            <h4 class="text-sm font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              Role & Permissions
            </h4>
            <div class="p-3 bg-red-50 rounded-xl mb-3 text-center">
              <p class="text-sm font-display font-bold text-red-700 flex items-center justify-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26z"/></svg>
                Super Admin
              </p>
              <p class="text-[10px] font-body text-red-600">Acces complet</p>
            </div>
            <div class="space-y-2">
              <div
                v-for="perm in permissions"
                :key="perm"
                class="flex items-center gap-2 text-sm font-body text-gray-600"
              >
                <svg class="w-4 h-4 text-green-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                {{ perm }}
              </div>
            </div>
          </div>

          <!-- Activity -->
          <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-up delay-200">
            <h4 class="text-sm font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              Activite recente
            </h4>
            <div class="space-y-3">
              <div
                v-for="(activity, idx) in recentActivity"
                :key="idx"
                class="flex items-center gap-3"
              >
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center"
                  :class="getActivityColorClass(activity.color)"
                >
                  <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline v-if="activity.icon === 'check'" points="20 6 9 17 4 12"/>
                    <template v-else-if="activity.icon === 'edit'"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></template>
                    <template v-else-if="activity.icon === 'login'"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></template>
                  </svg>
                </div>
                <div>
                  <p class="text-xs font-display font-medium text-gray-800">{{ activity.text }}</p>
                  <p class="text-[10px] font-body text-gray-400">{{ activity.time }}</p>
                </div>
              </div>
            </div>
            <router-link
              to="/audit"
              class="block mt-4 text-center text-xs font-display font-semibold text-anatt-500 hover:text-anatt-600"
            >
              Voir tout l'historique ->
            </router-link>
          </div>

          <!-- Sessions -->
          <div class="bg-white rounded-2xl border border-gray-100 p-5 animate-fade-up delay-300">
            <h4 class="text-sm font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
              Sessions actives
            </h4>
            <div class="space-y-3">
              <div
                v-for="(session, idx) in sessions"
                :key="idx"
                class="p-3 rounded-xl"
                :class="session.current ? 'bg-green-50 border border-green-200' : 'bg-gray-50'"
              >
                <div class="flex items-center justify-between">
                  <p class="text-xs font-display font-semibold" :class="session.current ? 'text-green-800' : 'text-gray-700'">
                    <svg class="w-3 h-3 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect v-if="idx === 0" x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                      <rect v-else x="5" y="2" width="14" height="20" rx="2" ry="2"/>
                    </svg>
                    {{ session.device }}
                  </p>
                  <span v-if="session.current" class="text-[10px] font-body text-green-600">Session actuelle</span>
                  <button
                    v-else
                    class="text-[10px] font-display text-red-500 font-semibold"
                    @click="revokeSession(idx)"
                  >
                    Revoquer
                  </button>
                </div>
                <p
                  class="text-[10px] mt-1 font-body"
                  :class="session.current ? 'text-green-700' : 'text-gray-500'"
                >
                  {{ session.ip }} {{ session.location ? '-- ' + session.location : '' }}
                  {{ session.time ? '-- ' + session.time : '' }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
