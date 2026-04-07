<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'

const route = useRoute()
const router = useRouter()

const mission = ref({
  id: Number(route.params.id),
  numero: 'OM-2026-0042',
  objet: 'Formation sur les systemes de transport intelligent',
  destination: 'Parakou',
  date_depart: '2026-04-10',
  date_retour: '2026-04-14',
  nb_jours: 5,
  montant_per_diem: 125000,
  statut: 'SOUMIS' as const,
  agent: { nom_complet: 'Jean KOUASSI', matricule: 'ANT-001', service: 'Direction Technique', categorie: 'A1' },
  taux: { destination_type: 'LOCALE', montant_journalier: 25000 },
  created_at: '2026-04-07T10:30:00',
})

const timeline = ref([
  { action: 'Creation', date: '07/04/2026 10:30', user: 'Gislain A.', statut: 'BROUILLON' },
  { action: 'Soumission', date: '07/04/2026 11:00', user: 'Gislain A.', statut: 'SOUMIS' },
])

const statutVariant: Record<string, 'info' | 'warning' | 'success' | 'danger'> = {
  BROUILLON: 'info', SOUMIS: 'warning', VALIDE_N1: 'success', APPROUVE_DG: 'success', PAYE: 'success', REJETE: 'danger',
}
</script>

<template>
  <AppLayout :title="'Ordre ' + mission.numero" :subtitle="mission.objet">
    <template #header-actions>
      <AppButton v-if="mission.statut === 'BROUILLON'" variant="outline" @click="router.push(`/ordres/${mission.id}/modifier`)">Modifier</AppButton>
      <AppButton v-if="mission.statut === 'SOUMIS'" @click="router.push(`/ordres/${mission.id}/valider-n1`)">Valider N1</AppButton>
      <AppButton variant="outline" @click="router.push(`/ordres/${mission.id}/pdf`)">PDF</AppButton>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main info -->
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-display font-bold text-gray-800">Informations generales</h2>
            <AppBadge :variant="statutVariant[mission.statut]">{{ mission.statut }}</AppBadge>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div><p class="text-xs text-gray-500 font-display mb-1">Numero</p><p class="text-sm font-body font-medium">{{ mission.numero }}</p></div>
            <div><p class="text-xs text-gray-500 font-display mb-1">Destination</p><p class="text-sm font-body font-medium">{{ mission.destination }}</p></div>
            <div><p class="text-xs text-gray-500 font-display mb-1">Date depart</p><p class="text-sm font-body font-medium">{{ mission.date_depart }}</p></div>
            <div><p class="text-xs text-gray-500 font-display mb-1">Date retour</p><p class="text-sm font-body font-medium">{{ mission.date_retour }}</p></div>
            <div><p class="text-xs text-gray-500 font-display mb-1">Duree</p><p class="text-sm font-body font-medium">{{ mission.nb_jours }} jour(s)</p></div>
            <div><p class="text-xs text-gray-500 font-display mb-1">Type destination</p><p class="text-sm font-body font-medium">{{ mission.taux.destination_type }}</p></div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6">
          <h2 class="font-display font-bold text-gray-800 mb-4">Per Diem</h2>
          <div class="bg-anatt-50 rounded-xl p-4 flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 font-body">{{ mission.nb_jours }} jours x {{ mission.taux.montant_journalier.toLocaleString() }} FCFA</p>
            </div>
            <p class="text-2xl font-display font-bold text-anatt-600">{{ mission.montant_per_diem.toLocaleString() }} FCFA</p>
          </div>
        </div>

        <!-- Timeline -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
          <h2 class="font-display font-bold text-gray-800 mb-4">Historique</h2>
          <div class="space-y-4">
            <div v-for="(event, i) in timeline" :key="i" class="flex gap-4">
              <div class="flex flex-col items-center">
                <div class="w-3 h-3 rounded-full bg-anatt-500"></div>
                <div v-if="i < timeline.length - 1" class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
              </div>
              <div class="pb-4">
                <p class="text-sm font-display font-semibold text-gray-800">{{ event.action }}</p>
                <p class="text-xs text-gray-500 font-body">{{ event.date }} par {{ event.user }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar: Agent info -->
      <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
          <h2 class="font-display font-bold text-gray-800 mb-4">Agent</h2>
          <div class="text-center mb-4">
            <div class="w-16 h-16 bg-anatt-100 rounded-full mx-auto flex items-center justify-center text-xl font-display font-bold text-anatt-600">
              {{ mission.agent.nom_complet.split(' ').map((n: string) => n[0]).join('') }}
            </div>
            <p class="text-sm font-display font-semibold mt-2">{{ mission.agent.nom_complet }}</p>
            <p class="text-xs text-gray-500 font-body">{{ mission.agent.matricule }}</p>
          </div>
          <div class="space-y-2 text-sm font-body">
            <div class="flex justify-between"><span class="text-gray-500">Service</span><span>{{ mission.agent.service }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Categorie</span><span>{{ mission.agent.categorie }}</span></div>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6">
          <h2 class="font-display font-bold text-gray-800 mb-4">Actions</h2>
          <div class="space-y-2">
            <AppButton class="w-full" variant="outline" @click="router.push(`/ordres/${mission.id}/pdf`)">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              Voir le PDF
            </AppButton>
            <AppButton class="w-full" variant="outline" @click="router.push(`/ordres/${mission.id}/historique`)">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Historique complet
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
