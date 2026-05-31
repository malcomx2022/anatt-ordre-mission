<script setup lang="ts">
// Les OM sont retranscrits depuis SEMO et ne sont pas éditables individuellement.
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const toast = useToast()

onMounted(() => {
  toast.warning("Les ordres de mission ne sont pas modifiables individuellement.")
  router.replace({ name: 'mission-detail', params: { id: route.params.id } })
})
</script>

<template>
  <div class="p-8 text-center text-gray-400 font-body">Redirection vers la fiche de l'ordre de mission…</div>
</template>
