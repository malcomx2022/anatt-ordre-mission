<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  currentPage: number
  lastPage: number
  total: number
}>()

const emit = defineEmits<{
  'page-change': [page: number]
}>()

const pages = computed(() => {
  const p: number[] = []
  const start = Math.max(1, props.currentPage - 2)
  const end = Math.min(props.lastPage, props.currentPage + 2)
  for (let i = start; i <= end; i++) p.push(i)
  return p
})
</script>

<template>
  <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100">
    <p class="text-sm text-gray-500 font-body">{{ total }} résultat(s)</p>
    <div class="flex items-center gap-1">
      <button
        :disabled="currentPage <= 1"
        class="px-3 py-1.5 rounded-lg text-sm font-display disabled:opacity-40"
        @click="emit('page-change', currentPage - 1)"
      >
        Préc.
      </button>
      <button
        v-for="page in pages"
        :key="page"
        class="w-9 h-9 rounded-lg text-sm font-display"
        :class="page === currentPage ? 'bg-anatt-500 text-white' : 'text-gray-600 hover:bg-gray-100'"
        @click="emit('page-change', page)"
      >
        {{ page }}
      </button>
      <button
        :disabled="currentPage >= lastPage"
        class="px-3 py-1.5 rounded-lg text-sm font-display disabled:opacity-40"
        @click="emit('page-change', currentPage + 1)"
      >
        Suiv.
      </button>
    </div>
  </div>
</template>
