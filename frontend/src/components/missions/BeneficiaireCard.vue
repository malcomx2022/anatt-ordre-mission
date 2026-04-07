<script setup lang="ts">
defineProps<{
  initiales: string
  nom: string
  role: string
  salaire: number
  categorie: string
  color: string
  jours1Repas: number
  jours2Repas: number
  nuitees: number
  taux1Repas: number
  taux2Repas: number
  tauxNuitee: number
  nbJours: number
}>()

defineEmits<{
  remove: []
  'update:jours1Repas': [value: number]
  'update:jours2Repas': [value: number]
  'update:nuitees': [value: number]
}>()

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}
</script>

<template>
  <div class="border border-gray-200 rounded-xl p-5">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xs font-display font-bold" :class="`bg-${color}-100 text-${color}-600`">{{ initiales }}</div>
        <div>
          <p class="font-display font-semibold text-gray-800">{{ nom }}</p>
          <p class="text-[11px] text-gray-400 font-body">{{ role }} - {{ formatMontant(salaire) }} FCFA - Categorie <span class="font-semibold text-anatt-600">{{ categorie }}</span></p>
        </div>
      </div>
      <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" @click="$emit('remove')">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <div class="bg-gray-50 rounded-lg p-4">
      <p class="text-xs font-display font-semibold text-gray-500 uppercase mb-3">Ventilation des jours (mission: {{ nbJours }} jours)</p>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Jours 1 repas</label>
          <div class="flex items-center gap-2">
            <input :value="jours1Repas" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500" @input="$emit('update:jours1Repas', Number(($event.target as HTMLInputElement).value))">
            <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(taux1Repas) }} = <strong class="text-gray-700">{{ formatMontant(jours1Repas * taux1Repas) }} F</strong></span>
          </div>
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Jours 2 repas</label>
          <div class="flex items-center gap-2">
            <input :value="jours2Repas" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500" @input="$emit('update:jours2Repas', Number(($event.target as HTMLInputElement).value))">
            <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(taux2Repas) }} = <strong class="text-gray-700">{{ formatMontant(jours2Repas * taux2Repas) }} F</strong></span>
          </div>
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Nuitees</label>
          <div class="flex items-center gap-2">
            <input :value="nuitees" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500" @input="$emit('update:nuitees', Number(($event.target as HTMLInputElement).value))">
            <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(tauxNuitee) }} = <strong class="text-gray-700">{{ formatMontant(nuitees * tauxNuitee) }} F</strong></span>
          </div>
        </div>
      </div>
      <div class="mt-3 pt-3 border-t border-gray-200 flex justify-end">
        <p class="text-sm font-display font-bold text-anatt-700">Total : {{ formatMontant((jours1Repas * taux1Repas) + (jours2Repas * taux2Repas) + (nuitees * tauxNuitee)) }} FCFA</p>
      </div>
    </div>
  </div>
</template>
