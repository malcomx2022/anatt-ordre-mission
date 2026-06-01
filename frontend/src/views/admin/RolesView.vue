<script setup lang="ts">
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { ROLE_LABELS } from '@/types'
import type { UserRole } from '@/types'

// Colonnes : ordre conforme à App\Support\Permissions.
const roleKeys: UserRole[] = ['SUPER_ADMIN', 'ADMIN', 'GESTIONNAIRE_OM', 'AGENT_FINANCIER', 'CONTROLEUR', 'LECTEUR']
const roleHeaders = roleKeys.map((r) => ({ key: r, label: ROLE_LABELS[r] }))

interface PermissionModule {
  name: string
  permissions: { label: string; values: boolean[] }[]
}

const T = true
const F = false

// Matrice [SUPER_ADMIN, ADMIN, GESTIONNAIRE_OM, AGENT_FINANCIER, CONTROLEUR, LECTEUR]
const modules: PermissionModule[] = [
  { name: 'Tableau de bord', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, T] },
  ]},
  { name: 'Personnel', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, F] },
    { label: 'Gérer (créer / modifier)', values: [T, T, T, F, F, F] },
  ]},
  { name: 'Ordres de mission', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, T] },
    { label: 'Retranscrire (mission)', values: [T, F, T, F, F, F] },
    { label: 'Valider / Rejeter', values: [T, F, F, F, T, F] },
  ]},
  { name: 'Paiements', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, F] },
    { label: 'Générer les fichiers', values: [T, F, F, T, F, F] },
    { label: 'Confirmer', values: [T, F, F, T, F, F] },
  ]},
  { name: 'Barème', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, F] },
    { label: 'Modifier', values: [T, T, F, F, F, F] },
  ]},
  { name: 'Rapports', permissions: [
    { label: 'Consulter', values: [T, T, T, T, T, T] },
  ]},
  { name: 'Administration', permissions: [
    { label: "Journal d'audit", values: [T, T, F, F, T, F] },
    { label: 'Gérer les utilisateurs', values: [T, T, F, F, F, F] },
    { label: 'Gérer les super admins & paramètres', values: [T, F, F, F, F, F] },
  ]},
]
</script>

<template>
  <AppLayout title="Matrice des permissions" subtitle="Droits par profil (6 profils RBAC)">
    <AppCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
              <th class="px-4 py-3">Permission</th>
              <th v-for="h in roleHeaders" :key="h.key" class="px-3 py-3 text-center">{{ h.label }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <template v-for="mod in modules" :key="mod.name">
              <tr class="bg-anatt-50/40">
                <td :colspan="roleKeys.length + 1" class="px-4 py-2 font-display font-bold text-anatt-700 text-xs uppercase">{{ mod.name }}</td>
              </tr>
              <tr v-for="perm in mod.permissions" :key="perm.label" class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-body text-gray-700">{{ perm.label }}</td>
                <td v-for="(v, i) in perm.values" :key="i" class="px-3 py-2.5 text-center">
                  <span v-if="v" class="text-anatt-500 font-bold">✓</span>
                  <span v-else class="text-gray-200">—</span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <p class="text-[11px] text-gray-400 font-body mt-3">Le super administrateur a accès implicite à tout (Gate::before).</p>
    </AppCard>
  </AppLayout>
</template>
