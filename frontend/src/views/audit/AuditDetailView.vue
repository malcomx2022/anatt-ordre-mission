<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import { useApi } from '@/composables/useApi'
import type { AuditLog } from '@/types'

const route = useRoute()
const router = useRouter()
const api = useApi()
const loadingData = ref(true)

const audit = ref<AuditLog | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get(`/api/audit/${route.params.id}`)
    audit.value = data.data || null
  } catch {
    // Geree par useApi
  } finally {
    loadingData.value = false
  }
})

const metadata = computed(() => {
  if (!audit.value) return []
  return [
    { label: 'Date & Heure', value: formatDateTime(audit.value.created_at) },
    { label: 'Utilisateur', value: audit.value.utilisateur || '--', initials: getInitials(audit.value.utilisateur || '') },
    { label: 'Adresse IP', value: audit.value.ip_address || '--', mono: true },
    { label: 'Action', value: audit.value.action },
  ]
})

const detailEntries = computed(() => {
  if (!audit.value?.details) return []
  const d = typeof audit.value.details === 'string' ? JSON.parse(audit.value.details) : audit.value.details
  return Object.entries(d).map(([key, val]) => ({ label: key, value: String(val) }))
})

function formatDateTime(iso: string): string {
  if (!iso) return '--'
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}

function getInitials(name: string): string {
  return name?.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) || '??'
}

function getActionColor(action: string): { bg: string; color: string } {
  if (action?.includes('CREATE')) return { bg: 'bg-blue-100', color: 'text-blue-600' }
  if (action?.includes('VALIDATE') || action?.includes('APPROVE')) return { bg: 'bg-green-100', color: 'text-green-600' }
  if (action?.includes('REJECT') || action?.includes('DELETE')) return { bg: 'bg-red-100', color: 'text-red-600' }
  if (action?.includes('UPDATE')) return { bg: 'bg-amber-100', color: 'text-amber-600' }
  if (action === 'LOGIN') return { bg: 'bg-cyan-100', color: 'text-cyan-600' }
  return { bg: 'bg-gray-100', color: 'text-gray-600' }
}
</script>

<template>
  <AppLayout :title="'Detail de l\'action'" :subtitle="audit ? `#${audit.id} - ${audit.action}` : 'Chargement...'">
    <template #header-actions>
      <button class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-body text-gray-600 hover:bg-gray-50 flex items-center gap-1" @click="router.push('/audit')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
        Retour au journal
      </button>
    </template>

    <!-- Loading -->
    <div v-if="loadingData" class="flex items-center justify-center py-24">
      <svg class="w-8 h-8 animate-spin text-anatt-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
    </div>

    <div v-else-if="!audit" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
      <p class="text-sm text-gray-400 font-body">Action d'audit introuvable.</p>
    </div>

    <!-- Action Summary -->
    <div v-else class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
      <div class="flex items-start justify-between mb-6">
        <div class="flex items-start gap-4">
          <div :class="[getActionColor(audit.action).bg, 'w-14 h-14 rounded-2xl flex items-center justify-center']">
            <svg :class="[getActionColor(audit.action).color, 'w-6 h-6']" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
          </div>
          <div>
            <h2 class="text-lg font-display font-bold text-gray-900">{{ audit.action }}</h2>
            <p class="text-sm text-gray-500 font-body mt-1">Cible : {{ audit.cible }}</p>
            <div class="flex items-center gap-3 mt-3">
              <span :class="[getActionColor(audit.action).bg, getActionColor(audit.action).color, 'px-2.5 py-1 text-xs font-display font-semibold rounded-lg']">{{ audit.action }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Metadata Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl">
        <div v-for="meta in metadata" :key="meta.label">
          <p class="text-[10px] uppercase text-gray-400 font-display font-semibold mb-1">{{ meta.label }}</p>
          <div v-if="meta.initials" class="flex items-center gap-2">
            <div class="w-6 h-6 bg-anatt-100 rounded-md flex items-center justify-center text-[9px] font-display font-bold text-anatt-700">{{ meta.initials }}</div>
            <p class="text-sm font-display font-semibold text-gray-800">{{ meta.value }}</p>
          </div>
          <p v-else :class="meta.mono ? 'font-mono' : 'font-display font-semibold'" class="text-sm text-gray-800">{{ meta.value }}</p>
        </div>
      </div>
    </div>

    <!-- Before / After Comparison -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up delay-100">
      <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
        Details de l'action
      </h3>
      <div v-if="detailEntries.length > 0" class="space-y-2">
        <div v-for="entry in detailEntries" :key="entry.label" class="bg-gray-50 border-l-[3px] border-anatt-500 p-3 rounded-r-lg">
          <p class="text-[10px] uppercase text-gray-400 font-display font-semibold">{{ entry.label }}</p>
          <p class="text-sm font-display font-semibold text-gray-800">{{ entry.value }}</p>
        </div>
      </div>
      <p v-else class="text-sm text-gray-400 font-body">Aucun detail supplementaire pour cette action.</p>
    </div>
  </AppLayout>
</template>
