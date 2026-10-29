<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  accept?: string
  label?: string
  description?: string
}>()

const emit = defineEmits<{
  'file-selected': [file: File]
}>()

const isDragging = ref(false)

function onDrop(e: DragEvent) {
  isDragging.value = false
  const file = e.dataTransfer?.files[0]
  if (file) emit('file-selected', file)
}

function onFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) emit('file-selected', file)
}
</script>

<template>
  <div
    class="border-2 border-dashed rounded-xl p-8 text-center transition-colors"
    :class="isDragging ? 'border-anatt-500 bg-anatt-50' : 'border-gray-200'"
    @dragover.prevent="isDragging = true"
    @dragleave="isDragging = false"
    @drop.prevent="onDrop"
  >
    <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
    <p class="font-display font-medium text-gray-600 mb-2">{{ label || 'Deposez un fichier ici' }}</p>
    <p class="text-sm text-gray-400 font-body mb-4">{{ description || 'ou cliquez pour sélectionner' }}</p>
    <input type="file" :accept="accept || '.csv,.txt,.xlsx'" class="hidden" id="fileUpload" @change="onFileChange">
    <label for="fileUpload" class="cursor-pointer px-6 py-2.5 bg-anatt-500 text-white font-display font-semibold text-sm rounded-xl hover:bg-anatt-600 transition-all">
      Choisir un fichier
    </label>
  </div>
</template>
