<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'

const route = useRoute()
const router = useRouter()
const commentaire = ref('')
const loading = ref(false)

const mission = ref({
  numero: 'OM-2026-0042', objet: 'Formation sur les systemes de transport intelligent',
  destination: 'Parakou', date_depart: '2026-04-10', date_retour: '2026-04-14',
  nb_jours: 5, montant_per_diem: 125000,
  agent: { nom_complet: 'Jean KOUASSI', matricule: 'ANT-001', service: 'Direction Technique' },
})

async function approve() {
  loading.value = true
  setTimeout(() => { router.push('/ordres') }, 1000)
}

async function reject() {
  if (commentaire.value.length < 10) return
  loading.value = true
  setTimeout(() => { router.push('/ordres') }, 1000)
}
</script>

<template>
  <AppLayout title="Validation Chef de Service" subtitle="Validation N+1">
    <div class="max-w-3xl space-y-6">
      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display font-bold text-gray-800">{{ mission.numero }}</h2>
          <AppBadge variant="warning">SOUMIS</AppBadge>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm font-body">
          <div><span class="text-gray-500">Agent:</span> {{ mission.agent.nom_complet }}</div>
          <div><span class="text-gray-500">Service:</span> {{ mission.agent.service }}</div>
          <div><span class="text-gray-500">Objet:</span> {{ mission.objet }}</div>
          <div><span class="text-gray-500">Destination:</span> {{ mission.destination }}</div>
          <div><span class="text-gray-500">Periode:</span> {{ mission.date_depart }} au {{ mission.date_retour }}</div>
          <div><span class="text-gray-500">Montant:</span> <strong class="text-anatt-600">{{ mission.montant_per_diem.toLocaleString() }} FCFA</strong></div>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <h2 class="font-display font-bold text-gray-800 mb-4">Decision</h2>
        <div class="mb-4">
          <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Commentaire (obligatoire pour rejet)</label>
          <textarea v-model="commentaire" rows="3" placeholder="Ajoutez un commentaire..." class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"></textarea>
        </div>
        <div class="flex gap-3">
          <AppButton :loading="loading" @click="approve">Approuver</AppButton>
          <AppButton variant="danger" :loading="loading" :disabled="commentaire.length < 10" @click="reject">Rejeter</AppButton>
          <AppButton variant="ghost" @click="router.back()">Annuler</AppButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
