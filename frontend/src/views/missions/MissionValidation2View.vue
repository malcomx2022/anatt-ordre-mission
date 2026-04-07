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
  valide_par_chef_nom: 'Marie DOSSOU',
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
  <AppLayout title="Approbation Direction Generale" subtitle="Validation finale DG">
    <div class="max-w-3xl space-y-6">
      <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        <p class="text-sm text-green-700 font-body">Valide par le chef de service <strong>{{ mission.valide_par_chef_nom }}</strong></p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display font-bold text-gray-800">{{ mission.numero }}</h2>
          <AppBadge variant="success">VALIDE N1</AppBadge>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm font-body">
          <div><span class="text-gray-500">Agent:</span> {{ mission.agent.nom_complet }}</div>
          <div><span class="text-gray-500">Objet:</span> {{ mission.objet }}</div>
          <div><span class="text-gray-500">Destination:</span> {{ mission.destination }}</div>
          <div><span class="text-gray-500">Periode:</span> {{ mission.date_depart }} au {{ mission.date_retour }} ({{ mission.nb_jours }}j)</div>
        </div>
        <div class="mt-4 bg-anatt-50 rounded-xl p-4 text-center">
          <p class="text-sm text-gray-600 font-body">Montant total per diem</p>
          <p class="text-2xl font-display font-bold text-anatt-600">{{ mission.montant_per_diem.toLocaleString() }} FCFA</p>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 p-6">
        <h2 class="font-display font-bold text-gray-800 mb-4">Decision du Directeur General</h2>
        <div class="mb-4">
          <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Commentaire</label>
          <textarea v-model="commentaire" rows="3" placeholder="Commentaire optionnel..." class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"></textarea>
        </div>
        <div class="flex gap-3">
          <AppButton :loading="loading" @click="approve">Approuver (DG)</AppButton>
          <AppButton variant="danger" :loading="loading" :disabled="commentaire.length < 10" @click="reject">Rejeter</AppButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
