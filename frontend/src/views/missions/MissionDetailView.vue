<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { useApi } from '@/composables/useApi'
import type { OrdreMission } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()

const mission = ref<OrdreMission | null>(null)
const loading = ref(true)
const timeline = ref<any[]>([])

onMounted(async () => {
  try {
    const [missionRes, historiqueRes] = await Promise.all([
      api.get(`/api/ordres/${route.params.id}`),
      api.get(`/api/ordres/${route.params.id}/historique`),
    ])
    mission.value = missionRes.data.data
    timeline.value = (historiqueRes.data.data || []).map((log: any) => ({
      icon: getTimelineIcon(log.action),
      color: getTimelineColor(log.action),
      titre: formatAction(log.action),
      date: formatDate(log.created_at),
      detail: log.user?.name || log.details?.validateur || '',
    }))
  } catch {
    // Erreur geree par useApi
  } finally {
    loading.value = false
  }
})

function formatDate(iso: string): string {
  if (!iso) return '--'
  const d = new Date(iso)
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatPeriode(depart: string, retour: string): string {
  if (!depart || !retour) return '--'
  return `${formatDate(depart).split(' ').slice(0, 3).join(' ')} - ${formatDate(retour).split(' ').slice(0, 3).join(' ')}`
}

function formatAction(action: string): string {
  const map: Record<string, string> = {
    CREATE_ORDRE_MISSION: 'Cree',
    SUBMIT_ORDRE_MISSION: 'Soumis pour validation',
    VALIDATE_N1: 'Valide par le chef de service',
    APPROVE_DG: 'Approuve par la DG',
    REJECT_ORDRE_MISSION: 'Rejete',
    GENERATE_PAYMENT: 'Paiement genere',
  }
  return map[action] || action
}

function getTimelineIcon(action: string): string {
  if (action.includes('VALIDATE') || action.includes('APPROVE')) return 'check'
  if (action.includes('PAYMENT') || action.includes('PAYE')) return 'coins'
  if (action.includes('SUBMIT')) return 'send'
  if (action.includes('REJECT')) return 'x'
  return 'plus'
}

function getTimelineColor(action: string): string {
  if (action.includes('APPROVE')) return 'bg-green-500'
  if (action.includes('VALIDATE')) return 'bg-anatt-500'
  if (action.includes('SUBMIT')) return 'bg-orange-500'
  if (action.includes('PAYMENT')) return 'bg-blue-500'
  if (action.includes('REJECT')) return 'bg-red-500'
  return 'bg-gray-400'
}

function handlePrint() {
  window.print()
}

function handleDuplicate() {
  router.push('/ordres/nouveau')
}
</script>

<template>
  <AppLayout :title="mission ? 'Ordre ' + mission.numero : 'Chargement...'" :subtitle="mission ? 'Cree le ' + formatDate(mission.created_at) : ''">
    <template #header-actions>
      <template v-if="mission">
        <AppButton v-if="mission.statut === 'BROUILLON'" variant="outline" @click="router.push(`/ordres/${mission.id}/modifier`)">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
          Modifier
        </AppButton>
        <AppButton v-if="mission.statut === 'APPROUVE_DG'" @click="router.push('/paiements/moov')">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg>
          Generer paiement
        </AppButton>
      </template>
    </template>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-if="mission" class="animate-fade-up grid grid-cols-1 xl:grid-cols-3 gap-6">
      <!-- Main content - left 2 cols -->
      <div class="xl:col-span-2 space-y-6">
        <!-- Mission details -->
        <AppCard>
          <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            Details de la mission
          </h2>
          <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 text-sm">
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Objet</p>
              <p class="font-body font-semibold">{{ mission.objet }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Destination</p>
              <p class="font-body font-semibold flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ mission.destination }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Agent</p>
              <p class="font-body font-semibold">{{ mission.agent ? `${mission.agent.prenom} ${mission.agent.nom}` : '--' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Date de debut</p>
              <p class="font-body font-semibold">{{ formatDate(mission.date_depart) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Date de fin</p>
              <p class="font-body font-semibold">{{ formatDate(mission.date_retour) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Duree</p>
              <p class="font-body font-semibold text-anatt-600">{{ mission.nb_jours }} jours</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Transport</p>
              <p class="font-body font-semibold flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-2-2.2-3.3C13 5.6 12 5 11 5H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                {{ mission.agent?.service || '--' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Categorie</p>
              <p class="font-body font-semibold">{{ mission.agent?.categorie || '--' }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 font-body mb-1">Statut</p>
              <AppBadge :variant="mission.statut === 'REJETE' ? 'danger' : mission.statut === 'SOUMIS' ? 'warning' : 'success'">{{ mission.statut }}</AppBadge>
            </div>
          </div>
        </AppCard>

        <!-- Beneficiaries table -->
        <AppCard>
          <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Beneficiaires et paiements
          </h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-50 text-left text-xs text-gray-500 uppercase font-display">
                  <th class="px-4 py-3">Agent</th>
                  <th class="px-4 py-3">Montant</th>
                  <th class="px-4 py-3">Avance</th>
                  <th class="px-4 py-3">Reliquat</th>
                  <th class="px-4 py-3">Operateur</th>
                  <th class="px-4 py-3">Statut paiement</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-if="mission.agent" class="hover:bg-anatt-50/30 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-display font-bold bg-anatt-100 text-anatt-600">
                        {{ (mission.agent.prenom?.[0] || '') + (mission.agent.nom?.[0] || '') }}
                      </div>
                      <div>
                        <p class="font-body font-medium">{{ mission.agent.prenom }} {{ mission.agent.nom }}</p>
                        <p class="text-[10px] text-gray-400 font-body">{{ mission.agent.categorie }} - {{ mission.agent.telephone }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 font-body font-bold">{{ Number(mission.montant_per_diem).toLocaleString() }} F</td>
                  <td class="px-4 py-3">
                    <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded text-xs font-body font-bold">{{ Math.round(Number(mission.montant_per_diem) * 0.33).toLocaleString() }} F</span>
                    <br><span class="text-[10px] text-gray-400 font-body">33%</span>
                  </td>
                  <td class="px-4 py-3">
                    <span class="bg-green-50 text-green-700 px-2 py-1 rounded text-xs font-body font-bold">{{ Math.round(Number(mission.montant_per_diem) * 0.67).toLocaleString() }} F</span>
                    <br><span class="text-[10px] text-gray-400 font-body">67%</span>
                  </td>
                  <td class="px-4 py-3">
                    <span v-if="mission.agent.numero_mtn" class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-1 rounded-lg">MTN</span>
                    <span v-else-if="mission.agent.numero_moov" class="bg-blue-100 text-blue-800 text-xs font-display font-bold px-2 py-1 rounded-lg">MOOV</span>
                    <span v-else class="text-xs text-gray-400">--</span>
                  </td>
                  <td class="px-4 py-3">
                    <AppBadge :variant="mission.statut === 'PAYE' ? 'success' : mission.statut === 'APPROUVE_DG' ? 'warning' : 'info'">
                      {{ mission.statut === 'PAYE' ? 'Paye' : mission.statut === 'APPROUVE_DG' ? 'En attente' : 'Non initie' }}
                    </AppBadge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <!-- Documents -->
        <AppCard>
          <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            Documents associes
          </h2>
          <div class="space-y-3">
            <div v-for="doc in [
              { nom: 'Ordre de mission PDF', fichier: mission.numero + '.pdf', taille: '--', type: 'pdf' },
            ]" :key="doc.fichier"
                 class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                     :class="doc.type === 'pdf' ? 'bg-red-100' : 'bg-green-100'">
                  <svg v-if="doc.type === 'pdf'" class="w-5 h-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                  <svg v-else class="w-5 h-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/></svg>
                </div>
                <div>
                  <p class="text-sm font-body font-medium text-gray-800">{{ doc.nom }}</p>
                  <p class="text-[11px] text-gray-400 font-body">{{ doc.fichier }} - {{ doc.taille }}</p>
                </div>
              </div>
              <button class="text-anatt-600 hover:text-anatt-700">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              </button>
            </div>
          </div>
        </AppCard>
      </div>

      <!-- Right column -->
      <div class="space-y-6">
        <!-- Financial summary -->
        <div class="bg-gradient-to-br from-anatt-600 to-anatt-800 rounded-2xl p-5 text-white animate-fade-up delay-100">
          <h3 class="font-display font-bold mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/></svg>
            Resume
          </h3>
          <div class="space-y-2 text-sm font-body">
            <div class="flex justify-between">
              <span class="text-anatt-200">Montant total</span>
              <span class="font-display font-bold text-lg">{{ Number(mission.montant_per_diem).toLocaleString() }} F</span>
            </div>
            <div class="flex justify-between">
              <span class="text-anatt-200">Avance (33%)</span>
              <span class="font-display font-bold">{{ Math.round(Number(mission.montant_per_diem) * 0.33).toLocaleString() }} F</span>
            </div>
            <div class="flex justify-between">
              <span class="text-anatt-200">Reliquat (67%)</span>
              <span class="font-display font-bold">{{ Math.round(Number(mission.montant_per_diem) * 0.67).toLocaleString() }} F</span>
            </div>
            <div class="border-t border-white/20 my-2"></div>
            <div class="flex justify-between">
              <span class="text-anatt-200">Duree</span>
              <span class="font-display font-bold text-gold-400">{{ mission.nb_jours }} jour(s)</span>
            </div>
          </div>
        </div>

        <!-- Timeline -->
        <AppCard>
          <h3 class="text-sm font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Historique
          </h3>
          <div class="space-y-4 relative">
            <div class="absolute top-2 bottom-2 left-[11px] w-0.5 bg-gray-200"></div>
            <div v-for="(event, i) in timeline" :key="i" class="relative flex gap-3">
              <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 z-10" :class="event.color">
                <svg v-if="event.icon === 'check'" class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <svg v-else-if="event.icon === 'coins'" class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
                <svg v-else-if="event.icon === 'send'" class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <svg v-else class="w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
              </div>
              <div>
                <p class="text-sm font-body font-medium text-gray-800">{{ event.titre }}</p>
                <p class="text-[11px] text-gray-400 font-body">{{ event.date }}</p>
                <p class="text-xs text-gray-500 font-body mt-1">{{ event.detail }}</p>
              </div>
            </div>
          </div>
        </AppCard>

        <!-- Quick actions -->
        <AppCard>
          <h3 class="text-sm font-display font-bold text-gray-800 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            Actions
          </h3>
          <div class="space-y-2">
            <button
              class="w-full px-4 py-2.5 text-left text-sm font-body hover:bg-gray-50 rounded-xl flex items-center gap-3 text-gray-700 transition-colors"
              @click="handlePrint"
            >
              <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
              Imprimer l'OM
            </button>
            <button
              class="w-full px-4 py-2.5 text-left text-sm font-body hover:bg-gray-50 rounded-xl flex items-center gap-3 text-gray-700 transition-colors"
              @click="router.push(`/ordres/${mission.id}/pdf`)"
            >
              <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
              Exporter en PDF
            </button>
            <button
              class="w-full px-4 py-2.5 text-left text-sm font-body hover:bg-gray-50 rounded-xl flex items-center gap-3 text-gray-700 transition-colors"
              @click="handleDuplicate"
            >
              <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
              Dupliquer cet OM
            </button>
          </div>
        </AppCard>
      </div>
    </div>
  </AppLayout>
</template>
