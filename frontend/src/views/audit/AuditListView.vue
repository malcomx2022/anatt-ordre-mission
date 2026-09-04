<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppPagination from '@/components/shared/AppPagination.vue'
import { auditService } from '@/services/audit'
import type { AuditLog } from '@/types'

const actionFilter = ref('')
const entiteFilter = ref('')
const dateDebut = ref('')
const dateFin = ref('')
const loading = ref(true)
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const entries = ref<AuditLog[]>([])

async function load() {
  loading.value = true
  try {
    const { items, meta } = await auditService.list({
      page: currentPage.value,
      action: actionFilter.value || undefined,
      entite: entiteFilter.value || undefined,
      date_debut: dateDebut.value || undefined,
      date_fin: dateFin.value || undefined,
    })
    entries.value = items
    currentPage.value = meta.current_page
    totalPages.value = meta.last_page
    totalItems.value = meta.total
  } finally {
    loading.value = false
  }
}

watch([actionFilter, entiteFilter, dateDebut, dateFin], () => { currentPage.value = 1; load() })
onMounted(load)

function changePage(p: number) { currentPage.value = p; load() }

function couleurAction(action: string): string {
  if (action.includes('IMPORT') || action.includes('CREATE')) return 'bg-blue-100 text-blue-700'
  if (action.includes('VALIDATE') || action.includes('APPROVE')) return 'bg-green-100 text-green-700'
  if (action.includes('REJECT') || action.includes('ERROR') || action.includes('DELETE')) return 'bg-red-100 text-red-700'
  if (action.includes('GENERATE') || action.includes('CONFIRM')) return 'bg-indigo-100 text-indigo-700'
  if (action === 'LOGIN') return 'bg-cyan-100 text-cyan-700'
  return 'bg-gray-100 text-gray-600'
}

function initiales(nom?: string): string {
  return (nom ?? '?').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2)
}

function resumeDonnees(d: AuditLog['donnees']): string {
  if (!d) return ''
  return Object.entries(d).map(([k, v]) => `${k}: ${v}`).join(' · ')
}
</script>

<template>
  <AppLayout title="Journal d'audit" :subtitle="`${totalItems} action(s) tracée(s)`">
    <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <select v-model="actionFilter" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Toutes les actions</option>
          <optgroup label="Ordres de mission">
            <option value="IMPORT_OM">Import OM</option>
            <option value="VALIDATE_OM">Validation OM</option>
            <option value="AMEND_OM">Amendement OM</option>
            <option value="REJECT_OM">Rejet OM</option>
          </optgroup>
          <optgroup label="États de paiement">
            <option value="RECAP_ETAT">Récapitulatif d'état</option>
            <option value="NUMERO_ETAT">Numéro d'état</option>
            <option value="CONTROL_ETAT">Contrôle d'état</option>
            <option value="VISA_DAF_ETAT">Visa DAF d'état</option>
            <option value="APPROVE_DG_ETAT">Validation DG d'état</option>
            <option value="REJECT_ETAT">Rejet d'état</option>
          </optgroup>
          <optgroup label="Lots de paiement">
            <option value="GENERATE_LOT">Génération lot</option>
            <option value="CONFIRM_LOT">Confirmation lot</option>
            <option value="ERROR_LOT">Lot en erreur</option>
          </optgroup>
          <optgroup label="Régularisations">
            <option value="RECOUVRER_VERSEMENT">Recouvrement trop-perçu</option>
          </optgroup>
          <optgroup label="Barème">
            <option value="CREATE_BAREME">Création barème</option>
            <option value="UPDATE_BAREME">Modification barème</option>
            <option value="DELETE_BAREME">Suppression barème</option>
          </optgroup>
          <optgroup label="Utilisateurs">
            <option value="CREATE_USER">Création utilisateur</option>
            <option value="UPDATE_USER">Modification utilisateur</option>
            <option value="CHANGE_ROLE_USER">Changement de rôle</option>
            <option value="ACTIVATE_USER">Activation utilisateur</option>
            <option value="DEACTIVATE_USER">Désactivation utilisateur</option>
            <option value="RESET_PASSWORD_USER">Réinit. mot de passe</option>
            <option value="RESET_PASSWORD_SUPERADMIN">Réinit. MDP super admin</option>
            <option value="USER_PERM_UPDATE">Permissions utilisateur</option>
            <option value="DELETE_USER">Suppression utilisateur</option>
          </optgroup>
          <optgroup label="Rôles & permissions">
            <option value="ROLE_CREATE">Création de rôle</option>
            <option value="ROLE_RENAME">Renommage de rôle</option>
            <option value="ROLE_DELETE">Suppression de rôle</option>
            <option value="ROLE_PERM_UPDATE">Modification permissions rôle</option>
            <option value="ROLE_PERM_RESET">Réinit. permissions rôle</option>
          </optgroup>
          <optgroup label="Session">
            <option value="LOGIN">Connexion</option>
          </optgroup>
        </select>
        <select v-model="entiteFilter" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="">Toutes les entités</option>
          <option value="ordre_mission">Ordre de mission</option>
          <option value="etat_paiement">État de paiement</option>
          <option value="lot_paiement">Lot de paiement</option>
          <option value="versement">Versement</option>
          <option value="bareme">Barème</option>
          <option value="user">Utilisateur</option>
          <option value="role">Rôle</option>
          <option value="role_permissions">Permissions de rôle</option>
          <option value="session">Session</option>
        </select>
        <div>
          <label class="block text-[11px] font-body text-gray-500 mb-1">Du</label>
          <input v-model="dateDebut" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
        </div>
        <div>
          <label class="block text-[11px] font-body text-gray-500 mb-1">Au</label>
          <input v-model="dateFin" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body focus:outline-none focus:border-anatt-500">
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase tracking-wider">
              <th class="px-5 py-3">Horodatage</th>
              <th class="px-5 py-3">Utilisateur</th>
              <th class="px-5 py-3">Action</th>
              <th class="px-5 py-3">Entité</th>
              <th class="px-5 py-3">Données</th>
              <th class="px-5 py-3">IP</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="loading"><td colspan="7" class="px-5 py-12 text-center text-gray-400 font-body">Chargement…</td></tr>
            <tr v-else-if="!entries.length"><td colspan="7" class="px-5 py-12 text-center text-gray-400 font-body">Aucune entrée d'audit.</td></tr>
            <tr v-for="e in entries" :key="e.id" class="hover:bg-anatt-50/30 transition-colors">
              <td class="px-5 py-3 font-body text-xs text-gray-600">{{ new Date(e.created_at).toLocaleString('fr-FR') }}</td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-display font-bold bg-anatt-100 text-anatt-700">{{ initiales(e.utilisateur?.name) }}</div>
                  <span class="text-sm font-body text-gray-700">{{ e.utilisateur?.name ?? '—' }}</span>
                </div>
              </td>
              <td class="px-5 py-3"><span class="px-2.5 py-1 text-xs font-display font-semibold rounded-lg" :class="couleurAction(e.action)">{{ e.action }}</span></td>
              <td class="px-5 py-3 font-body text-gray-600">{{ e.entite }}<span v-if="e.entite_id" class="text-gray-400"> #{{ e.entite_id }}</span></td>
              <td class="px-5 py-3 font-body text-[11px] text-gray-500 max-w-xs truncate">{{ resumeDonnees(e.donnees) }}</td>
              <td class="px-5 py-3 font-mono text-xs text-gray-400">{{ e.ip_address ?? '—' }}</td>
              <td class="px-5 py-3">
                <router-link :to="`/audit/${e.id}`" class="text-anatt-500 hover:text-anatt-600 text-xs font-display font-semibold">Détail</router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination :current-page="currentPage" :last-page="totalPages" :total="totalItems" @page-change="changePage" />
    </div>
  </AppLayout>
</template>
