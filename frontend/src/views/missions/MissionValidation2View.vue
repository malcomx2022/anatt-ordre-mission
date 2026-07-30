<script setup lang="ts">
// Workflow unifié : plus de double validation N1/DG. Redirection vers la fiche OM.
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

onMounted(() => {
  router.replace({ name: 'mission-detail', params: { id: route.params.id } })
})
</script>

<template>
  <div class="p-8 text-center text-gray-400 font-body">Redirection vers la fiche de l'ordre de mission…</div>
</template>
