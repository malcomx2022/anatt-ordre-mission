<script setup lang="ts">
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { ROLE_LABELS } from '@/types'
import type { UserRole } from '@/types'

// Ordre des colonnes : conforme à useAuth.ts
const roleKeys: UserRole[] = ['admin', 'gestionnaire_om', 'agent_financier', 'lecteur']

const roleHeaders = roleKeys.map((r) => ({ key: r, label: ROLE_LABELS[r] }))

interface PermissionModule {
  name: string
  permissions: { label: string; values: boolean[] }[]
}

// Matrice [admin, gestionnaire_om, agent_financier, lecteur]
const modules: PermissionModule[] = [
  { name: 'Tableau de bord', permissions: [
    { label: 'Voir le dashboard', values: [true, true, true, true] },
  ]},
  { name: 'Personnel', permissions: [
    { label: 'Consulter', values: [true, true, true, true] },
    { label: 'Créer / Modifier', values: [true, true, false, false] },
    { label: 'Importer en masse', values: [true, true, false, false] },
  ]},
  { name: 'Ordres de mission', permissions: [
    { label: 'Consulter', values: [true, true, true, true] },
    { label: 'Retranscrire (mission)', values: [true, true, false, false] },
    { label: 'Valider / Rejeter', values: [true, true, false, false] },
  ]},
  { name: 'Paiements', permissions: [
    { label: 'Consulter', values: [true, true, true, true] },
    { label: 'Générer les fichiers', values: [true, false, true, false] },
    { label: 'Confirmer', values: [true, false, true, false] },
  ]},
  { name: 'Barème', permissions: [
    { label: 'Consulter', values: [true, true, true, true] },
    { label: 'Modifier', values: [true, false, false, false] },
  ]},
  { name: 'Rapports', permissions: [
    { label: 'Consulter', values: [true, true, true, true] },
    { label: 'Exporter', values: [true, true, true, false] },
  ]},
  { name: 'Administration', permissions: [
    { label: "Journal d'audit", values: [true, false, false, false] },
    { label: 'Utilisateurs / Paramètres', values: [true, false, false, false] },
  ]},
]
</script>

<template>
  <AppLayout title="Matrice des permissions" subtitle="Droits par rôle (admin / gestionnaire OM / agent financier / lecteur)">
    <AppCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
              <th class="px-4 py-3">Permission</th>
              <th v-for="h in roleHeaders" :key="h.key" class="px-4 py-3 text-center">{{ h.label }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <template v-for="mod in modules" :key="mod.name">
              <tr class="bg-anatt-50/40">
                <td :colspan="roleKeys.length + 1" class="px-4 py-2 font-display font-bold text-anatt-700 text-xs uppercase">{{ mod.name }}</td>
              </tr>
              <tr v-for="perm in mod.permissions" :key="perm.label" class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-body text-gray-700">{{ perm.label }}</td>
                <td v-for="(v, i) in perm.values" :key="i" class="px-4 py-2.5 text-center">
                  <span v-if="v" class="text-anatt-500 font-bold">✓</span>
                  <span v-else class="text-gray-200">—</span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </AppCard>
  </AppLayout>
</template>
