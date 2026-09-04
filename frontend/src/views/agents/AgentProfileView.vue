<script setup lang="ts">
import { computed } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import { useAuth } from '@/composables/useAuth'
import type { Permission } from '@/composables/useAuth'
import { ROLE_LABELS } from '@/types'

const { user, role, permissions } = useAuth()

const initiales = computed(() =>
  (user.value?.name ?? '?').split(' ').map((n) => n[0]).join('').toUpperCase().slice(0, 2),
)

const PERMISSION_LABELS: Partial<Record<Permission, string>> = {
  'dashboard.consulter': 'Tableau de bord',
  'personnel.consulter': 'Consulter le personnel',
  'personnel.gerer': 'Gérer le personnel',
  'om.consulter': 'Consulter les OM',
  'om.retranscrire': 'Retranscrire les missions',
  'om.valider': 'Valider / rejeter les OM',
  'paiements.consulter': 'Consulter les paiements',
  'paiements.generer': 'Générer les fichiers de paiement',
  'paiements.confirmer': 'Confirmer les paiements',
  'baremes.consulter': 'Consulter le barème',
  'baremes.modifier': 'Modifier le barème',
  'rapports.consulter': 'Consulter les rapports',
  'audit.consulter': "Consulter le journal d'audit",
  'utilisateurs.gerer': 'Gérer les utilisateurs',
  'superadmins.gerer': 'Gérer les super administrateurs',
  'parametres.gerer': 'Paramètres système',
  'roles.gerer': 'Gérer la matrice des rôles',
}

// Repli sur la clé brute : aucune permission ne doit disparaître de l'affichage
// faute de libellé (ex. une clé ajoutée après cet écran).
const permissionsLisibles = computed(() =>
  permissions.value.map((p) => PERMISSION_LABELS[p] ?? p),
)
</script>

<template>
  <AppLayout title="Mon profil" subtitle="Compte utilisateur">
    <div class="max-w-3xl mx-auto space-y-6">
      <AppCard>
        <div class="flex items-center gap-5">
          <div class="w-20 h-20 bg-gradient-to-br from-anatt-500 to-anatt-700 rounded-2xl flex items-center justify-center text-2xl font-display font-bold text-white">
            {{ initiales }}
          </div>
          <div>
            <h2 class="text-xl font-display font-bold text-gray-800">{{ user?.name }}</h2>
            <p class="text-sm font-body text-gray-500">{{ user?.email }}</p>
            <AppBadge variant="info" class="mt-2">{{ role ? ROLE_LABELS[role] : '—' }}</AppBadge>
          </div>
        </div>
      </AppCard>

      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-4">Mes permissions</h3>
        <div class="flex flex-wrap gap-2">
          <span v-for="(label, i) in permissionsLisibles" :key="i" class="px-3 py-1.5 rounded-lg bg-anatt-50 text-anatt-700 text-xs font-display font-medium">
            {{ label }}
          </span>
        </div>
      </AppCard>
    </div>
  </AppLayout>
</template>
