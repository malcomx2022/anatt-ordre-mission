<script setup lang="ts">
defineProps<{
  search: string
  grade: string
  operateur: string
  statut: string
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  'update:grade': [value: string]
  'update:operateur': [value: string]
  'update:statut': [value: string]
}>()
</script>

<template>
  <div class="bg-white rounded-2xl border border-gray-100 p-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="lg:col-span-2 relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input
          :value="search"
          type="text"
          placeholder="Rechercher par nom, matricule..."
          class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:border-anatt-500 focus:ring-2 focus:ring-anatt-500/20"
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        >
      </div>
      <select :value="grade" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500" @change="emit('update:grade', ($event.target as HTMLSelectElement).value)">
        <option value="">Tous les grades</option>
        <option>Directeur</option><option>Chef Service</option><option>Cadre</option><option>Agent</option>
      </select>
      <select :value="operateur" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500" @change="emit('update:operateur', ($event.target as HTMLSelectElement).value)">
        <option value="">Tous operateurs</option>
        <option>MTN</option><option>MOOV</option>
      </select>
      <select :value="statut" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-body text-gray-700 focus:outline-none focus:border-anatt-500" @change="emit('update:statut', ($event.target as HTMLSelectElement).value)">
        <option value="">Tous statuts</option>
        <option>Actif</option><option>Inactif</option>
      </select>
    </div>
  </div>
</template>
