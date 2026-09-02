<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { usersService } from '@/services'
import type { UserPermissions } from '@/services/users'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import type { Permission } from '@/utils/permissions'
import { ROLE_LABELS } from '@/types'

/*
 * Overrides de permissions PAR UTILISATEUR (RBAC flexible — niveau 2).
 *
 * En plus de son profil, un utilisateur peut se voir accorder ou retirer une
 * permission. L'écran affiche l'EFFECTIF (base ± overrides) sous forme de cases ;
 * au moment d'enregistrer, on en déduit les accords (cochée hors base) et les
 * retraits (base décochée). Édition réservée au SUPER_ADMIN (roles.gerer) ;
 * l'ADMIN consulte en lecture seule.
 */
const route = useRoute()
const router = useRouter()
const { can } = useAuth()
const toast = useToast()

const userId = Number(route.params.id)
const editable = computed(() => can('roles.gerer'))

const loading = ref(true)
const saving = ref(false)
const data = ref<UserPermissions | null>(null)

/** État courant éditable : ensemble des permissions cochées (effectif). */
const coche = ref<Set<Permission>>(new Set())
/** Base du profil (référence pour dériver accords/retraits). */
const base = ref<Set<Permission>>(new Set())

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

const modules = computed(() => {
  const toutes = [...base.value, ...(data.value?.effectives ?? []), ...(data.value?.granted ?? [])]
  const catalogue = data.value ? [...new Set([...data.value.base, ...data.value.granted, ...data.value.revoked, ...toutes])] : []
  // On affiche l'ensemble complet des clés connues via le catalogue serveur (base + non_assignables inclus).
  const clesConnues = data.value
    ? [...new Set([...data.value.base, ...data.value.non_assignables, ...data.value.effectives, ...catalogue,
        ...Object.keys(LIBELLES) as Permission[]])]
    : []
  return MODULES.map((mod) => ({
    nom: mod.nom,
    permissions: (clesConnues as Permission[])
      .filter((p) => mod.prefixes.some((prefixe) => p.startsWith(prefixe)))
      .sort()
      .map((p) => ({ cle: p, label: LIBELLES[p] ?? p })),
  })).filter((mod) => mod.permissions.length > 0)
})

const nonAssignables = computed(() => new Set(data.value?.non_assignables ?? []))

function estCoche(perm: Permission): boolean {
  return coche.value.has(perm)
}

function estBase(perm: Permission): boolean {
  return base.value.has(perm)
}

function modifiable(perm: Permission): boolean {
  return editable.value && !nonAssignables.value.has(perm)
}

function basculer(perm: Permission): void {
  if (!modifiable(perm)) return
  if (coche.value.has(perm)) coche.value.delete(perm)
  else coche.value.add(perm)
}

/** Accords = cochées hors base ; retraits = base décochées. */
const accords = computed(() => [...coche.value].filter((p) => !base.value.has(p)).sort())
const retraits = computed(() => [...base.value].filter((p) => !coche.value.has(p)).sort())

const original = ref('')
function signature(): string {
  return [...coche.value].sort().join('|')
}
const modifie = computed(() => signature() !== original.value)

async function charger() {
  loading.value = true
  try {
    const d = await usersService.permissions(userId)
    data.value = d
    base.value = new Set(d.base)
    coche.value = new Set(d.effectives)
    original.value = [...coche.value].sort().join('|')
  } catch {
    toast.error("Impossible de charger les permissions de l'utilisateur.")
  } finally {
    loading.value = false
  }
}

async function enregistrer() {
  if (!modifie.value || !data.value) return
  saving.value = true
  try {
    const effectives = await usersService.updatePermissions(userId, accords.value, retraits.value)
    coche.value = new Set(effectives)
    original.value = [...coche.value].sort().join('|')
    // Rafraîchit accords/retraits stockés côté serveur.
    data.value = { ...data.value, granted: accords.value, revoked: retraits.value, effectives }
    toast.success('Permissions de l’utilisateur enregistrées.')
  } catch (e) {
    const payload = (e as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data
    const premier = payload?.errors ? Object.values(payload.errors)[0]?.[0] : payload?.message
    toast.error(premier ?? "L'enregistrement a échoué.")
  } finally {
    saving.value = false
  }
}

function reinitialiser() {
  coche.value = new Set(base.value)
}

onMounted(charger)
</script>

<template>
  <AppLayout title="Permissions de l'utilisateur" subtitle="Ajuster les droits d'une personne au-delà de son profil">
    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <template v-else-if="data">
      <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
        <div class="text-sm font-body text-gray-600">
          Profil : <span class="font-display font-semibold text-gray-800">{{ ROLE_LABELS[data.role] ?? data.role }}</span>
          <span class="text-gray-400"> — les cases cochées forment l'accès effectif.</span>
        </div>
        <button
          type="button"
          class="text-xs font-display text-anatt-600 hover:underline"
          @click="router.back()"
        >← Retour</button>
      </div>

      <AppCard v-if="data.super_admin">
        <p class="font-body text-gray-600 text-sm">
          Cet utilisateur est <strong>super administrateur</strong> : il a accès à tout.
          Les overrides n'ont aucun effet sur lui.
        </p>
      </AppCard>

      <template v-else>
        <AppCard>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
                  <th class="px-4 py-3">Permission</th>
                  <th class="px-3 py-3 text-center">Profil</th>
                  <th class="px-3 py-3 text-center">Accès effectif</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <template v-for="mod in modules" :key="mod.nom">
                  <tr class="bg-anatt-50/40">
                    <td colspan="3" class="px-4 py-2 font-display font-bold text-anatt-700 text-xs uppercase">{{ mod.nom }}</td>
                  </tr>
                  <tr v-for="perm in mod.permissions" :key="perm.cle" class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 font-body text-gray-700">
                      {{ perm.label }}
                      <span class="block text-[10px] text-gray-400">{{ perm.cle }}</span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                      <span v-if="estBase(perm.cle)" class="text-gray-400" title="Donnée par le profil">●</span>
                      <span v-else class="text-gray-200">—</span>
                    </td>
                    <td class="px-3 py-2.5 text-center">
                      <input
                        v-if="modifiable(perm.cle)"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-anatt-500 focus:ring-anatt-500/40 cursor-pointer"
                        :checked="estCoche(perm.cle)"
                        @change="basculer(perm.cle)"
                      />
                      <span v-else-if="estCoche(perm.cle)" class="text-anatt-500 font-bold">✓</span>
                      <span v-else class="text-gray-200">—</span>
                      <span
                        v-if="estCoche(perm.cle) && !estBase(perm.cle)"
                        class="ml-1 text-[9px] font-body text-gold-600 align-middle"
                      >accordé</span>
                      <span
                        v-else-if="!estCoche(perm.cle) && estBase(perm.cle)"
                        class="ml-1 text-[9px] font-body text-red-500 align-middle"
                      >retiré</span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <p class="text-[11px] text-gray-400 font-body mt-3">
            Colonne « Profil » = droits hérités du rôle. Une case cochée hors profil = <strong>accordé</strong> ;
            une case du profil décochée = <strong>retiré</strong>. Les clés réservées au super administrateur
            ne sont pas attribuables ; un utilisateur qui valide l'état (<code>om.approuver</code>) ne peut pas
            aussi générer/confirmer les paiements.
          </p>
        </AppCard>

        <div v-if="editable" class="mt-4 flex flex-wrap items-center gap-3">
          <AppButton :disabled="!modifie || saving" :loading="saving" @click="enregistrer">
            Enregistrer
          </AppButton>
          <button
            type="button"
            :disabled="saving"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-display text-gray-600 hover:bg-gray-50 disabled:opacity-50"
            @click="reinitialiser"
          >
            Aligner sur le profil (retirer les overrides)
          </button>
        </div>
      </template>
    </template>
  </AppLayout>
</template>
