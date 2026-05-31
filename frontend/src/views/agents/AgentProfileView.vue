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
  'dashboard.view': 'Tableau de bord',
  'agents.read': 'Consulter le personnel',
  'agents.write': 'Gérer le personnel',
  'agents.import': 'Importer le personnel',
  'ordres.read': 'Consulter les OM',
  'missions.create': 'Retranscrire les missions',
  'ordres.validate': 'Valider les OM',
  'ordres.reject': 'Rejeter les OM',
  'paiements.read': 'Consulter les paiements',
  'paiements.generate': 'Générer les fichiers de paiement',
  'paiements.confirm': 'Confirmer les paiements',
  'baremes.read': 'Consulter le barème',
  'baremes.write': 'Gérer le barème',
  'rapports.read': 'Consulter les rapports',
  'rapports.export': 'Exporter les rapports',
  'audit.read': "Consulter le journal d'audit",
  'admin.users': 'Gestion des utilisateurs',
  'admin.settings': 'Paramètres système',
  'admin.backups': 'Sauvegardes',
}

const permissionsLisibles = computed(() =>
  permissions.value.map((p) => PERMISSION_LABELS[p]).filter((l): l is string => !!l),
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
