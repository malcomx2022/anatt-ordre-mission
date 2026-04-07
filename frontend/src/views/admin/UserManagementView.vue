<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const searchQuery = ref('')
const roleFilter = ref('')
const statusFilter = ref('')

const stats = [
  { label: 'Utilisateurs total', value: '8', iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'En ligne actuellement', value: '5', iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { label: 'Inactifs (30+ jours)', value: '2', iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
  { label: 'Comptes desactives', value: '1', iconBg: 'bg-red-100', iconColor: 'text-red-600' },
]

const users = [
  { initials: 'GA', name: 'Gislain ADJAHO', email: 'gislain.adjaho@anatt.bj', role: 'Super Admin', roleBg: 'bg-red-100', roleColor: 'text-red-700', status: 'En ligne', statusColor: 'text-green-600', statusDot: 'bg-green-500', lastLogin: 'Maintenant', ip: '192.168.1.45', actions30d: 458, pct: 92, gradient: 'from-anatt-500 to-anatt-700', online: true, disabled: false },
  { initials: 'MD', name: 'Marie DOSSA', email: 'marie.dossa@anatt.bj', role: 'Administrateur', roleBg: 'bg-blue-100', roleColor: 'text-blue-700', status: 'En ligne', statusColor: 'text-green-600', statusDot: 'bg-green-500', lastLogin: 'Il y a 12 min', ip: '192.168.1.22', actions30d: 312, pct: 78, gradient: 'from-purple-400 to-purple-600', online: true, disabled: false },
  { initials: 'PA', name: 'Pierre ADJOVI', email: 'pierre.adjovi@anatt.bj', role: 'Validateur', roleBg: 'bg-green-100', roleColor: 'text-green-700', status: 'En ligne', statusColor: 'text-green-600', statusDot: 'bg-green-500', lastLogin: 'Il y a 1h', ip: '192.168.1.18', actions30d: 178, pct: 45, gradient: 'from-blue-400 to-blue-600', online: true, disabled: false },
  { initials: 'JK', name: 'Jean-Baptiste KONE', email: 'jb.kone@anatt.bj', role: 'Gestionnaire', roleBg: 'bg-amber-100', roleColor: 'text-amber-700', status: 'En ligne', statusColor: 'text-green-600', statusDot: 'bg-green-500', lastLogin: 'Il y a 25 min', ip: '192.168.1.30', actions30d: 267, pct: 65, gradient: 'from-amber-400 to-amber-600', online: true, disabled: false },
  { initials: 'SP', name: 'Paterne SAOSSI', email: 'paterne.saossi@anatt.bj', role: 'Gestionnaire', roleBg: 'bg-amber-100', roleColor: 'text-amber-700', status: 'Hors ligne', statusColor: 'text-gray-500', statusDot: 'bg-gray-400', lastLogin: 'Hier, 17:30', ip: '192.168.1.55', actions30d: 142, pct: 35, gradient: 'from-cyan-400 to-cyan-600', online: false, disabled: false },
  { initials: 'FK', name: 'Fatima KOUKOUI', email: 'fatima.koukoui@anatt.bj', role: 'Lecteur', roleBg: 'bg-gray-100', roleColor: 'text-gray-600', status: 'Inactif', statusColor: 'text-amber-600', statusDot: 'bg-amber-500', lastLogin: '12/12/2024', ip: '192.168.1.67', actions30d: 8, pct: 5, gradient: 'from-pink-400 to-pink-600', online: false, disabled: false },
  { initials: 'AH', name: 'Arnaud HOUNTON', email: 'arnaud.hounton@anatt.bj', role: 'Gestionnaire', roleBg: 'bg-gray-100', roleColor: 'text-gray-500', status: 'Desactive', statusColor: 'text-red-500', statusDot: 'bg-red-500', lastLogin: '05/11/2024', ip: '-', actions30d: 0, pct: 0, gradient: 'from-gray-300 to-gray-400', online: false, disabled: true },
]
</script>

<template>
  <AppLayout title="Gestion des Utilisateurs" subtitle="Comptes d'acces et permissions du systeme">
    <template #header-actions>
      <router-link to="/admin/utilisateurs/nouveau" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Nouvel utilisateur
      </router-link>
    </template>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6 animate-fade-up">
      <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-2xl border border-gray-100 p-5">
        <div class="flex items-center justify-between mb-3">
          <div :class="[stat.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center']">
            <svg :class="[stat.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
          </div>
        </div>
        <p class="text-2xl font-display font-bold text-gray-900">{{ stat.value }}</p>
        <p class="text-xs text-gray-500 font-body">{{ stat.label }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 animate-fade-up delay-100">
      <div class="flex items-center gap-4">
        <div class="flex-1 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
          <input v-model="searchQuery" type="text" placeholder="Rechercher par nom, email, role..." class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 outline-none transition-all duration-200">
        </div>
        <select v-model="roleFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Tous les roles</option>
          <option>Super Admin</option><option>Administrateur</option><option>Validateur</option><option>Gestionnaire</option><option>Lecteur</option>
        </select>
        <select v-model="statusFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Tous les statuts</option>
          <option>Actif</option><option>Inactif</option><option>Desactive</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-200">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Utilisateur</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Role</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Statut</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Derniere connexion</th>
            <th class="px-5 py-3.5 text-left text-xs font-display font-semibold text-gray-500 uppercase">Actions (30j)</th>
            <th class="px-5 py-3.5 text-center text-xs font-display font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="user in users" :key="user.email" class="group" :class="user.disabled ? 'opacity-50' : ''">
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <div class="relative">
                  <div :class="['w-10 h-10 bg-gradient-to-br rounded-xl flex items-center justify-center text-white text-sm font-display font-bold', user.gradient]">{{ user.initials }}</div>
                  <div v-if="user.online" class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></div>
                </div>
                <div>
                  <p class="text-sm font-display font-semibold text-gray-800">{{ user.name }}</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ user.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-4"><span :class="[user.roleBg, user.roleColor, 'px-2.5 py-1 text-xs font-display font-semibold rounded-lg']">{{ user.role }}</span></td>
            <td class="px-5 py-4">
              <span :class="[user.statusColor, 'flex items-center gap-1.5 text-sm font-body']">
                <span :class="[user.statusDot, 'w-2 h-2 rounded-full']"></span>{{ user.status }}
              </span>
            </td>
            <td class="px-5 py-4">
              <p class="text-sm font-body text-gray-700">{{ user.lastLogin }}</p>
              <p class="text-[11px] text-gray-400 font-body">{{ user.ip }}</p>
            </td>
            <td class="px-5 py-4">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                  <div class="bg-anatt-500 h-1.5 rounded-full" :style="{ width: user.pct + '%' }"></div>
                </div>
                <span class="text-xs font-display font-semibold text-gray-600">{{ user.actions30d }}</span>
              </div>
            </td>
            <td class="px-5 py-4 text-center">
              <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                </button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </button>
                <button v-if="!user.disabled && user.role !== 'Super Admin'" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
