<script setup lang="ts">
defineProps<{
  currentStep: number
  steps: { label: string; subLabel: string }[]
}>()
</script>

<template>
  <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
    <div class="flex items-center justify-between max-w-2xl mx-auto">
      <template v-for="(step, i) in steps" :key="i">
        <div class="flex items-center gap-3">
          <div
            class="w-10 h-10 rounded-xl flex items-center justify-center font-display font-bold text-sm"
            :class="i + 1 < currentStep ? 'bg-anatt-500 text-white' : i + 1 === currentStep ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-400'"
          >
            <svg v-if="i + 1 < currentStep" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <span v-else>{{ i + 1 }}</span>
          </div>
          <div class="hidden sm:block">
            <p class="text-sm font-display" :class="i + 1 <= currentStep ? 'font-semibold text-anatt-600' : 'font-medium text-gray-400'">{{ step.label }}</p>
            <p class="text-[11px]" :class="i + 1 < currentStep ? 'text-gray-400' : 'text-gray-300'">{{ i + 1 < currentStep ? 'Complete' : step.subLabel }}</p>
          </div>
        </div>
        <div v-if="i < steps.length - 1" class="flex-1 h-0.5 mx-4 max-w-[100px]" :class="i + 1 < currentStep ? 'bg-anatt-500' : 'bg-gray-200'" />
      </template>
    </div>
  </div>
</template>
