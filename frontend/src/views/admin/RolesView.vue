<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { rolesService } from '@/services'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import type { Permission } from '@/utils/permissions'
import type { UserRole } from '@/types'

/*
 * Matrice rôle → permissions ÉDITABLE (RBAC flexible).
 *
 * Elle était auparavant dérivée en dur de ROLE_PERMISSIONS ; elle vient
 * désormais du serveur (GET /api/admin/roles) et reflète les personnalisations.
 * L'ADMIN (utilisateurs.gerer) la consulte ; seul le SUPER_ADMIN (roles.gerer)
 * peut la modifier. Le serveur reste l'arbitre : garde-fous et 422 y sont posés.
 */
const { can } = useAuth()
const toast = useToast()

const editable = computed(() => can('roles.gerer'))

const loading = ref(true)
const saving = ref(false)
const roles = ref<{ cle: UserRole; libelle: string; verrouille: boolean; personnalise: boolean }[]>([])
const permissions = ref<Permission[]>([])
const nonAssignables = ref<Permission[]>([])

/** État courant éditable : role → set de permissions cochées. */
const matrice = ref<Record<string, Set<Permission>>>({})
/** Instantané serveur, pour détecter les colonnes modifiées. */
const original = ref<Record<string, string>>({})

/** Libellés lisibles + regroupement par module (ordre d'affichage). */
const MODULES: { nom: string; prefixes: string[] }[] = [
  { nom: 'Tableau de bord', prefixes: ['dashboard.'] },
  { nom: 'Personnel', prefixes: ['personnel.'] },
  { nom: 'Ordres de mission', prefixes: ['om.'] },
  { nom: 'Paiements', prefixes: ['paiements.'] },
  { nom: 'Barème', prefixes: ['baremes.'] },
  { nom: 'Rapports', prefixes: ['rapports.'] },
  { nom: 'Administration', prefixes: ['audit.', 'utilisateurs.', 'superadmins.', 'parametres.', 'roles.'] },
]

const LIBELLES: Record<string, string> = {
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
  'roles.gerer': 'Modifier la matrice des permissions',
}

const modules = computed(() =>
  MODULES.map((mod) => ({
    nom: mod.nom,
    permissions: permissions.value
      .filter((p) => mod.prefixes.some((prefixe) => p.startsWith(prefixe)))
      .map((p) => ({ cle: p, label: LIBELLES[p] ?? p })),
  })).filter((mod) => mod.permissions.length > 0),
)

/** Une case est-elle cochée pour ce rôle ? Le SUPER_ADMIN a tout. */
function coche(role: { cle: UserRole; verrouille: boolean }, perm: Permission): boolean {
  if (role.verrouille) return true
  return matrice.value[role.cle]?.has(perm) ?? false
}

/** Case modifiable : édition activée, colonne non verrouillée, clé attribuable. */
function modifiable(role: { verrouille: boolean }, perm: Permission): boolean {
  return editable.value && !role.verrouille && !nonAssignables.value.includes(perm)
}

function basculer(role: { cle: UserRole; verrouille: boolean }, perm: Permission): void {
  if (!modifiable(role, perm)) return
  const set = matrice.value[role.cle] ?? (matrice.value[role.cle] = new Set())
  if (set.has(perm)) set.delete(perm)
  else set.add(perm)
}

/** Signature stable d'une colonne, pour comparer à l'instantané serveur. */
function signature(role: UserRole): string {
  return [...(matrice.value[role] ?? [])].sort().join('|')
}

function estModifie(role: UserRole): boolean {
  return original.value[role] !== undefined && signature(role) !== original.value[role]
}

const rolesModifies = computed(() => roles.value.filter((r) => !r.verrouille && estModifie(r.cle)))
const aDesModifications = computed(() => rolesModifies.value.length > 0)

function appliquer(data: { matrice: Record<string, Permission[]>; roles: typeof roles.value }): void {
  matrice.value = {}
  original.value = {}
  for (const r of data.roles) {
    const perms = (data.matrice[r.cle] ?? []) as Permission[]
    matrice.value[r.cle] = new Set(perms)
    original.value[r.cle] = [...perms].sort().join('|')
  }
}

async function charger() {
  loading.value = true
  try {
    const data = await rolesService.matrice()
    roles.value = data.profils
    permissions.value = data.permissions
    nonAssignables.value = data.non_assignables
    appliquer({ matrice: data.matrice, roles: data.profils })
  } catch {
    toast.error('Impossible de charger la matrice des permissions.')
  } finally {
    loading.value = false
  }
}

async function enregistrer() {
  if (!aDesModifications.value) return
  saving.value = true
  try {
    for (const r of rolesModifies.value) {
      const perms = [...(matrice.value[r.cle] ?? [])]
      await rolesService.remplacer(r.cle, perms)
      original.value[r.cle] = signature(r.cle)
      r.personnalise = true
    }
    toast.success('Matrice des permissions enregistrée.')
  } catch (e) {
    const message =
      (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    const premier = message?.errors ? Object.values(message.errors)[0]?.[0] : message?.message
    toast.error(premier ?? "L'enregistrement a échoué.")
  } finally {
    saving.value = false
  }
}

async function reinitialiser(role: { cle: UserRole; libelle: string }) {
  saving.value = true
  try {
    const perms = await rolesService.reinitialiser(role.cle)
    matrice.value[role.cle] = new Set(perms)
    original.value[role.cle] = [...perms].sort().join('|')
    const ref = roles.value.find((r) => r.cle === role.cle)
    if (ref) ref.personnalise = false
    toast.success(`Profil « ${role.libelle} » réinitialisé au défaut.`)
  } catch {
    toast.error('La réinitialisation a échoué.')
  } finally {
    saving.value = false
  }
}

onMounted(charger)
</script>

<template>
  <AppLayout
    title="Matrice des permissions"
    :subtitle="editable
      ? 'Ajustez les droits par profil — le super administrateur conserve un accès total'
      : 'Droits par profil — reflet de la configuration serveur (lecture seule)'"
  >
    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <template v-else>
      <AppCard>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
                <th class="px-4 py-3">Permission</th>
                <th v-for="r in roles" :key="r.cle" class="px-3 py-3 text-center">
                  <span>{{ r.libelle }}</span>
                  <span
                    v-if="r.personnalise && !r.verrouille"
                    class="block text-[9px] font-body normal-case text-gold-600"
                  >personnalisé</span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <template v-for="mod in modules" :key="mod.nom">
                <tr class="bg-anatt-50/40">
                  <td
                    :colspan="roles.length + 1"
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
                  <td v-for="r in roles" :key="r.cle" class="px-3 py-2.5 text-center">
                    <input
                      v-if="modifiable(r, perm.cle)"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-anatt-500 focus:ring-anatt-500/40 cursor-pointer"
                      :checked="coche(r, perm.cle)"
                      @change="basculer(r, perm.cle)"
                    />
                    <span v-else-if="coche(r, perm.cle)" class="text-anatt-500 font-bold">✓</span>
                    <span v-else class="text-gray-200">—</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <p class="text-[11px] text-gray-400 font-body mt-3">
          Le super administrateur a accès implicite à tout (colonne verrouillée) ; les clés
          <code>superadmins.gerer</code>, <code>parametres.gerer</code> et <code>roles.gerer</code>
          lui restent exclusives. Un profil qui valide l’état (<code>om.approuver</code>) ne peut pas
          aussi générer ou confirmer les paiements (séparation ordonnateur/payeur).
        </p>
      </AppCard>

      <!-- Barre d'actions : seulement en mode édition -->
      <div v-if="editable" class="mt-4 flex flex-wrap items-center gap-3">
        <AppButton :disabled="!aDesModifications || saving" :loading="saving" @click="enregistrer">
          Enregistrer les modifications
          <span v-if="aDesModifications">({{ rolesModifies.length }})</span>
        </AppButton>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="r in roles.filter((x) => x.personnalise && !x.verrouille)"
            :key="r.cle"
            type="button"
            :disabled="saving"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-display text-gray-600
                   hover:bg-gray-50 disabled:opacity-50"
            @click="reinitialiser(r)"
          >
            Réinitialiser {{ r.libelle }}
          </button>
        </div>
      </div>
    </template>
  </AppLayout>
</template>
