<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { useApi } from '@/composables/useApi'
import { useSearch } from '@/composables/useSearch'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import type { User } from '@/types'
import { ROLE_LABELS } from '@/types'
import { badgeDeRole } from '@/utils/roles'
import { rolesService } from '@/services'

const api = useApi()
const toast = useToast()
const { user: currentUser, isSuperAdmin } = useAuth()
const { query: searchQuery, debouncedQuery } = useSearch(300)
const roleFilter = ref('')
const statutFilter = ref('')
const loading = ref(true)
const users = ref<User[]>([])
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const resetResult = ref<{ name: string; password: string } | null>(null)
// Répartition par rôle GLOBALE (renvoyée par l'API), pas seulement la page courante.
const roleStats = ref({ total: 0, super_admins: 0, admins: 0, autres: 0 })

// Catalogue des rôles (système + personnalisés) pour le filtre et l'édition.
const roleOptions = ref<{ value: string; label: string; systeme: boolean }[]>([])

async function loadRoles() {
  try {
    const { profils } = await rolesService.matrice()
    roleOptions.value = profils.map((p) => ({ value: p.cle, label: p.libelle, systeme: p.systeme }))
  } catch {
    roleOptions.value = []
  }
}

/** Libellé lisible d'un rôle (système ou personnalisé), repli sur le code. */
function roleLabel(code: string): string {
  return roleOptions.value.find((r) => r.value === code)?.label ?? ROLE_LABELS[code as keyof typeof ROLE_LABELS] ?? code
}

const stats = computed(() => [
  { label: 'Utilisateurs total', value: String(roleStats.value.total || totalItems.value), iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { label: 'Super admins', value: String(roleStats.value.super_admins), iconBg: 'bg-red-100', iconColor: 'text-red-600' },
  { label: 'Admins', value: String(roleStats.value.admins), iconBg: 'bg-purple-100', iconColor: 'text-purple-600' },
  { label: 'Autres profils', value: String(roleStats.value.autres), iconBg: 'bg-green-100', iconColor: 'text-green-600' },
])

async function loadUsers() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: currentPage.value }
    if (debouncedQuery.value) params.search = debouncedQuery.value
    if (roleFilter.value) params.role = roleFilter.value
    if (statutFilter.value) params.actif = statutFilter.value

    const { data } = await api.get('/api/admin/users', { params })
    users.value = data.data || []
    if (data.stats) roleStats.value = data.stats
    if (data.meta) {
      currentPage.value = data.meta.current_page
      totalPages.value = data.meta.last_page
      totalItems.value = data.meta.total
    }
  } catch {
    // Geree par useApi
  } finally {
    loading.value = false
  }
}

watch([debouncedQuery, roleFilter, statutFilter], () => {
  currentPage.value = 1
  loadUsers()
})

// Un super admin n'est gérable que par un super admin ; on ne s'auto-désactive/supprime pas.
function peutGerer(u: User): boolean {
  if (u.role === 'SUPER_ADMIN' && !isSuperAdmin()) return false
  return true
}
function estSoiMeme(u: User): boolean {
  return u.id === currentUser.value?.id
}

function handlePageChange(page: number) {
  currentPage.value = page
  loadUsers()
}

onMounted(() => {
  loadUsers()
  loadRoles()
})

// ── Édition du rôle d'un utilisateur ──
const editing = ref<User | null>(null)
const editRole = ref('')
const savingRole = ref(false)

// Le profil SUPER_ADMIN n'est proposé qu'à un super admin (le serveur l'exige aussi).
const rolesAssignables = computed(() =>
  roleOptions.value.filter((r) => r.value !== 'SUPER_ADMIN' || isSuperAdmin()),
)

function openEditRole(u: User) {
  editing.value = u
  editRole.value = u.role
}

async function saveRole() {
  if (!editing.value || !editRole.value || editRole.value === editing.value.role) {
    editing.value = null
    return
  }
  savingRole.value = true
  try {
    await api.put(`/api/admin/users/${editing.value.id}`, { role: editRole.value })
    toast.success('Rôle mis à jour. Les sessions actives de l’utilisateur ont été révoquées.')
    editing.value = null
    loadUsers()
  } catch {
    // Erreurs (403/422) gérées par useApi (toast).
  } finally {
    savingRole.value = false
  }
}

function getInitials(name: string): string {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

async function deleteUser(user: User) {
  if (!confirm(`Supprimer ${user.name} ?`)) return
  try {
    await api.delete(`/api/admin/users/${user.id}`)
    loadUsers()
  } catch {
    // Geree par useApi
  }
}

async function toggleActif(user: User) {
  const cible = !user.actif
  if (!confirm(`${cible ? 'Activer' : 'Désactiver'} le compte de ${user.name} ?`)) return
  try {
    await api.post(`/api/admin/users/${user.id}/statut`, { actif: cible })
    toast.success(cible ? 'Compte activé.' : 'Compte désactivé.')
    loadUsers()
  } catch {
    // Geree par useApi
  }
}

async function resetPassword(user: User) {
  if (!confirm(`Réinitialiser le mot de passe de ${user.name} ?`)) return
  try {
    const { data } = await api.post(`/api/admin/users/${user.id}/reset-password`)
    resetResult.value = { name: user.name, password: data.data?.password ?? '' }
  } catch {
    // Geree par useApi
  }
}

function copier(texte: string) {
  navigator.clipboard?.writeText(texte)
  toast.success('Copié dans le presse-papiers.')
}
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
          <option value="">Tous les rôles</option>
          <option v-for="p in roleOptions" :key="p.value" :value="p.value">{{ p.label }}</option>
        </select>
        <select v-model="statutFilter" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none">
          <option value="">Tous les statuts</option>
          <option value="1">Actifs</option>
          <option value="0">Inactifs</option>
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
          <tr v-if="loading">
            <td colspan="6" class="px-5 py-12 text-center">
              <svg class="w-8 h-8 animate-spin text-anatt-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-400 font-body">Aucun utilisateur trouve.</td>
          </tr>
          <tr v-for="u in users" :key="u.id" class="group hover:bg-anatt-50/30 transition-colors">
            <td class="px-5 py-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-anatt-100 rounded-xl flex items-center justify-center text-anatt-600 text-sm font-display font-bold">{{ getInitials(u.name) }}</div>
                <div>
                  <p class="text-sm font-display font-semibold text-gray-800">{{ u.name }}</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ u.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-4">
              <span :class="[badgeDeRole(u.role), 'px-2.5 py-1 text-xs font-display font-semibold rounded-lg']" :title="roleLabel(u.role)">{{ u.role }}</span>
            </td>
            <td class="px-5 py-4">
              <span v-if="u.actif" class="px-2.5 py-1 text-xs font-display font-semibold rounded-lg bg-anatt-50 text-anatt-600">Actif</span>
              <span v-else class="px-2.5 py-1 text-xs font-display font-semibold rounded-lg bg-gray-100 text-gray-500">Inactif</span>
            </td>
            <td class="px-5 py-4">
              <p class="text-sm font-body text-gray-500">{{ u.derniere_connexion ? new Date(u.derniere_connexion).toLocaleString('fr-FR') : 'Jamais' }}</p>
            </td>
            <td class="px-5 py-4">
              <span class="text-xs font-display text-gray-500">--</span>
            </td>
            <td class="px-5 py-4 text-center">
              <div class="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- Modifier le rôle -->
                <button v-if="peutGerer(u)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-purple-50 text-purple-500" title="Modifier le rôle" @click="openEditRole(u)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 12.75v6.75A2.25 2.25 0 0117.25 21H5.25A2.25 2.25 0 013 18.75V6.75A2.25 2.25 0 015.25 4.5H12" /></svg>
                </button>
                <!-- Permissions (overrides par utilisateur) -->
                <router-link v-if="peutGerer(u)" :to="`/admin/utilisateurs/${u.id}/permissions`" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-anatt-50 text-anatt-600" title="Permissions">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                </router-link>
                <!-- Réinitialiser le mot de passe -->
                <button v-if="peutGerer(u)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-blue-500" title="Réinitialiser le mot de passe" @click="resetPassword(u)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>
                </button>
                <!-- Activer / désactiver -->
                <button v-if="peutGerer(u) && !estSoiMeme(u)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-amber-50 text-amber-500" :title="u.actif ? 'Désactiver' : 'Activer'" @click="toggleActif(u)">
                  <svg v-if="u.actif" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </button>
                <!-- Supprimer -->
                <button v-if="peutGerer(u) && !estSoiMeme(u)" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-500" title="Supprimer" @click="deleteUser(u)">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <AppPagination
        :current-page="currentPage"
        :last-page="totalPages"
        :total="totalItems"
        @page-change="handlePageChange"
      />
    </div>

    <!-- Mot de passe réinitialisé -->
    <teleport to="body">
      <div v-if="resetResult" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl">
          <h3 class="text-lg font-display font-bold text-gray-800 mb-2">Mot de passe réinitialisé</h3>
          <p class="text-sm font-body text-gray-500 mb-4">Nouveau mot de passe temporaire pour <span class="font-semibold">{{ resetResult.name }}</span>. Communiquez-le de façon sécurisée — il ne sera plus affiché.</p>
          <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-5">
            <code class="flex-1 text-sm font-mono text-gray-800 break-all">{{ resetResult.password }}</code>
            <button class="px-2 py-1 text-xs font-display rounded-lg bg-gray-200 hover:bg-gray-300" @click="copier(resetResult.password)">Copier</button>
          </div>
          <button class="w-full px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600" @click="resetResult = null">Fermer</button>
        </div>
      </div>
    </teleport>

    <!-- Modifier le rôle d'un utilisateur -->
    <teleport to="body">
      <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="editing = null">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm mx-4 shadow-2xl">
          <h3 class="text-lg font-display font-bold text-gray-800 mb-1">Modifier le rôle</h3>
          <p class="text-sm font-body text-gray-500 mb-4">
            Profil de <span class="font-semibold">{{ editing.name }}</span>.
            Changer le rôle révoque ses sessions actives (nouvelles permissions à la reconnexion).
          </p>
          <label class="block text-[11px] text-gray-400 uppercase font-body mb-1">Rôle</label>
          <select v-model="editRole" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 mb-5">
            <option v-for="r in rolesAssignables" :key="r.value" :value="r.value">
              {{ r.label }}{{ r.systeme ? '' : ' (personnalisé)' }}
            </option>
          </select>
          <div class="flex gap-2">
            <button class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm font-display font-medium hover:bg-gray-50" :disabled="savingRole" @click="editing = null">Annuler</button>
            <button class="flex-1 px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 disabled:opacity-50" :disabled="savingRole || editRole === editing.role" @click="saveRole">
              {{ savingRole ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </AppLayout>
</template>
