<script setup lang="ts">
import { ref } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'

const roles = [
  { name: 'Super Admin', users: 1, borderColor: 'border-red-200', iconBg: 'bg-red-100', iconColor: 'text-red-600' },
  { name: 'Administrateur', users: 1, borderColor: 'border-blue-200', iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { name: 'Validateur', users: 1, borderColor: 'border-green-200', iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { name: 'Gestionnaire', users: 3, borderColor: 'border-amber-200', iconBg: 'bg-amber-100', iconColor: 'text-amber-600' },
  { name: 'Lecteur', users: 1, borderColor: 'border-gray-200', iconBg: 'bg-gray-100', iconColor: 'text-gray-600' },
]

interface PermissionModule {
  name: string
  permissions: { label: string; values: boolean[] }[]
}

const modules: PermissionModule[] = [
  { name: 'Tableau de bord', permissions: [
    { label: 'Voir le dashboard', values: [true, true, true, true, true] },
    { label: 'Statistiques avancees', values: [true, true, false, false, false] },
  ]},
  { name: 'Personnel', permissions: [
    { label: 'Consulter', values: [true, true, true, true, false] },
    { label: 'Creer / Modifier', values: [true, true, false, true, false] },
    { label: 'Supprimer', values: [true, true, false, false, false] },
    { label: 'Importer en masse', values: [true, true, false, false, false] },
  ]},
  { name: 'Ordres de Mission', permissions: [
    { label: 'Consulter', values: [true, true, true, true, false] },
    { label: 'Creer / Modifier', values: [true, true, false, true, false] },
    { label: 'Valider / Rejeter', values: [true, true, true, false, false] },
  ]},
  { name: 'Paiements', permissions: [
    { label: 'Consulter les lots', values: [true, true, true, true, false] },
    { label: 'Generer des fichiers', values: [true, true, false, true, false] },
    { label: 'Marquer paye', values: [true, true, true, false, false] },
    { label: 'Reconciliation', values: [true, true, false, false, false] },
  ]},
  { name: 'Administration', permissions: [
    { label: 'Gerer utilisateurs', values: [true, false, false, false, false] },
    { label: 'Gerer roles', values: [true, false, false, false, false] },
    { label: 'Parametres systeme', values: [true, false, false, false, false] },
    { label: 'Journal d\'audit', values: [true, true, false, false, false] },
  ]},
]

const roleHeaders = [
  { label: 'Super Admin', color: 'text-red-600' },
  { label: 'Admin', color: 'text-blue-600' },
  { label: 'Validateur', color: 'text-green-600' },
  { label: 'Gestionnaire', color: 'text-amber-600' },
  { label: 'Lecteur', color: 'text-gray-500' },
]
</script>

<template>
  <AppLayout title="Gestion des Roles & Permissions" subtitle="Matrice des droits d'acces par role">
    <template #header-actions>
      <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-display font-semibold text-gray-700 hover:bg-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Nouveau role
      </button>
      <button class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" /></svg>
        Enregistrer
      </button>
    </template>

    <!-- Role Cards -->
    <div class="grid grid-cols-5 gap-3 mb-6 animate-fade-up">
      <div v-for="role in roles" :key="role.name" :class="['bg-white rounded-xl border-2 p-4 text-center', role.borderColor]">
        <div :class="[role.iconBg, 'w-10 h-10 rounded-xl flex items-center justify-center mx-auto mb-2']">
          <svg :class="[role.iconColor, 'w-5 h-5']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
        </div>
        <p class="text-sm font-display font-bold text-gray-800">{{ role.name }}</p>
        <p class="text-[11px] text-gray-500 font-body">{{ role.users }} utilisateur{{ role.users > 1 ? 's' : '' }}</p>
      </div>
    </div>

    <!-- Permission Matrix -->
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-fade-up delay-100">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-5 py-4 text-left text-xs font-display font-semibold text-gray-500 uppercase min-w-[220px] sticky left-0 bg-gray-50 z-10">Module / Permission</th>
              <th v-for="header in roleHeaders" :key="header.label" :class="[header.color, 'px-4 py-4 text-center text-xs font-display font-semibold uppercase']">{{ header.label }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="mod in modules" :key="mod.name">
              <tr class="bg-anatt-50/50">
                <td colspan="6" class="px-5 py-2.5 text-xs font-display font-bold text-anatt-700 uppercase tracking-wider">{{ mod.name }}</td>
              </tr>
              <tr v-for="perm in mod.permissions" :key="perm.label" class="border-b border-gray-50">
                <td class="px-5 py-3 text-sm font-body text-gray-700 pl-10">{{ perm.label }}</td>
                <td v-for="(val, idx) in perm.values" :key="idx" class="text-center">
                  <svg v-if="val" class="w-5 h-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
                  <svg v-else class="w-5 h-5 text-gray-300 mx-auto" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" /></svg>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
        <div class="flex items-center gap-4 text-xs text-gray-500 font-body">
          <span class="flex items-center gap-1">
            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
            Autorise
          </span>
          <span class="flex items-center gap-1">
            <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm-1.72 6.97a.75.75 0 10-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 101.06 1.06L12 13.06l1.72 1.72a.75.75 0 101.06-1.06L13.06 12l1.72-1.72a.75.75 0 10-1.06-1.06L12 10.94l-1.72-1.72z" clip-rule="evenodd" /></svg>
            Refuse
          </span>
        </div>
        <p class="text-xs text-gray-400 font-body">Derniere modification : 15/01/2025 a 09:30 par Gislain A.</p>
      </div>
    </div>
  </AppLayout>
</template>
