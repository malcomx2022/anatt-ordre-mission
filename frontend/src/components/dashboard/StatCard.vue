<script setup lang="ts">
defineProps<{
  label: string
  value: string | number
  trend?: string
  trendUp?: boolean
  iconBg: string
  iconColor: string
  tags?: { text: string; color: string }[]
}>()
</script>

<template>
  <div class="stat-card card-hover cursor-pointer animate-fade-in bg-white rounded-2xl p-5 border border-gray-100">
    <div class="flex items-start justify-between mb-3">
      <div class="w-11 h-11 rounded-xl flex items-center justify-center" :class="iconBg">
        <slot name="icon" />
      </div>
      <span v-if="trend" class="text-xs font-display font-semibold px-2 py-1 rounded-lg" :class="trendUp ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'">
        {{ trend }}
      </span>
    </div>
    <p class="text-2xl font-display font-bold text-gray-800">{{ value }}</p>
    <p class="text-sm font-body text-gray-500">{{ label }}</p>
    <div v-if="tags?.length" class="mt-3 flex gap-2 text-[11px]">
      <span v-for="tag in tags" :key="tag.text" class="px-2 py-0.5 rounded" :class="tag.color">{{ tag.text }}</span>
    </div>
  </div>
</template>
