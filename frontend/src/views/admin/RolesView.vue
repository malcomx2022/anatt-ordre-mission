<script setup lang="ts">
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { ROLE_LABELS } from '@/types'
import type { UserRole } from '@/types'
import { ROLE_PERMISSIONS, TOUTES_PERMISSIONS } from '@/utils/permissions'
import type { Permission } from '@/utils/permissions'

/*
 * La matrice est DÉRIVÉE de ROLE_PERMISSIONS (miroir de config/profils.php).
 * Elle était auparavant recopiée à la main dans cet écran, et avait divergé :
 * les profils DAF et DG ainsi que les droits om.viser / om.approuver
 * manquaient. Toute évolution du RBAC se reflète désormais ici sans retouche.
 */
const roleKeys: UserRole[] = [
  'SUPER_ADMIN', 'ADMIN', 'GESTIONNAIRE_OM', 'AGENT_FINANCIER',
  'CONTROLEUR', 'DAF', 'DG', 'LECTEUR',
]

/** Regroupement par module, dans l'ordre d'apparition des clés. */
const MODULES: { nom: string; prefixes: string[] }[] = [
  { nom: 'Tableau de bord', prefixes: ['dashboard.'] },
  { nom: 'Personnel', prefixes: ['personnel.'] },
  { nom: 'Ordres de mission', prefixes: ['om.'] },
  { nom: 'Paiements', prefixes: ['paiements.'] },
  { nom: 'Barème', prefixes: ['baremes.'] },
  { nom: 'Rapports', prefixes: ['rapports.'] },
  { nom: 'Administration', prefixes: ['audit.', 'utilisateurs.', 'superadmins.', 'parametres.'] },
]

const LIBELLES: Record<Permission, string> = {
  'dashboard.consulter': 'Consulter le tableau de bord',
  'personnel.consulter': 'Consulter les agents',
  'personnel.gerer': 'Gérer les agents (créer / modifier / importer)',
  'om.consulter': 'Consulter les ordres de mission',
  'om.retranscrire': 'Retranscrire une mission SEMO',
  'om.valider': 'Contrôler (étape 1)',
  'om.numeroter': "Numéroter l'état de paiement",
  'om.viser': 'Apposer le visa DAF (étape 2)',
  'om.approuver': 'Valider — Directeur Général (étape 3)',
  'om.rejeter': 'Rejeter avec motif',
  'paiements.consulter': 'Consulter les lots et versements',
  'paiements.generer': 'Générer les fichiers MTN / Moov',
  'paiements.confirmer': 'Confirmer un dépôt effectué',
  'baremes.consulter': 'Consulter le barème',
  'baremes.modifier': 'Modifier le barème',
  'rapports.consulter': 'Consulter les rapports',
  'audit.consulter': "Consulter le journal d'audit",
  'utilisateurs.gerer': 'Gérer les utilisateurs',
  'superadmins.gerer': 'Gérer les super administrateurs',
  'parametres.gerer': 'Gérer les paramètres système',
}

const modules = MODULES.map((mod) => ({
  nom: mod.nom,
  permissions: TOUTES_PERMISSIONS
    .filter((p) => mod.prefixes.some((prefixe) => p.startsWith(prefixe)))
    .map((p) => ({
      cle: p,
      label: LIBELLES[p],
      valeurs: roleKeys.map((r) => ROLE_PERMISSIONS[r].includes(p)),
    })),
})).filter((mod) => mod.permissions.length > 0)
</script>

<template>
  <AppLayout
    title="Matrice des permissions"
    subtitle="Droits par profil — reflet exact de la configuration serveur"
  >
    <AppCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
              <th class="px-4 py-3">Permission</th>
              <th v-for="r in roleKeys" :key="r" class="px-3 py-3 text-center">
                {{ ROLE_LABELS[r] }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <template v-for="mod in modules" :key="mod.nom">
              <tr class="bg-anatt-50/40">
                <td
                  :colspan="roleKeys.length + 1"
                  class="px-4 py-2 font-display font-bold text-anatt-700 text-xs uppercase"
                >
                  {{ mod.nom }}
                </td>
              </tr>
              <tr v-for="perm in mod.permissions" :key="perm.cle" class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-body text-gray-700">
                  {{ perm.label }}
                  <span class="block text-[10px] text-gray-400">{{ perm.cle }}</span>
                </td>
                <td v-for="(v, i) in perm.valeurs" :key="i" class="px-3 py-2.5 text-center">
                  <span v-if="v" class="text-anatt-500 font-bold">✓</span>
                  <span v-else class="text-gray-200">—</span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <p class="text-[11px] text-gray-400 font-body mt-3">
        Le super administrateur a accès implicite à tout (Gate::before) ; les clés
        <code>superadmins.gerer</code> et <code>parametres.gerer</code> lui restent exclusives.
      </p>
    </AppCard>
  </AppLayout>
</template>
