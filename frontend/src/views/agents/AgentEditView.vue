<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AgentForm from '@/components/agents/AgentForm.vue'
import { agentsService } from '@/services/agents'
import { useToast } from '@/composables/useToast'
import { getValidationErrors } from '@/composables/useApi'
import type { AgentFormData } from '@/types'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const id = Number(route.params.id)

const loading = ref(true)
const submitting = ref(false)
const serverErrors = ref<Record<string, string[]>>({})

const form = reactive<AgentFormData>({
  nom: '', prenoms: '', matricule: '', ifu: '', grade: '',
  indice: null, salaire_brut: null, categorie: undefined,
  telephone: '', operateur: 'MTN', actif: true,
})

onMounted(async () => {
  try {
    const a = await agentsService.get(id)
    Object.assign(form, {
      nom: a.nom, prenoms: a.prenoms, matricule: a.matricule,
      ifu: a.ifu ?? '', grade: a.grade ?? '', indice: a.indice ?? null,
      salaire_brut: a.salaire_brut ?? null, categorie: a.categorie,
      telephone: a.telephone, operateur: a.operateur, actif: a.actif,
    })
  } finally {
    loading.value = false
  }
})

async function submit() {
  submitting.value = true
  serverErrors.value = {}
  try {
    await agentsService.update(id, form)
    toast.success('Agent mis à jour.')
    router.push(`/agents/${id}`)
  } catch (e) {
    serverErrors.value = getValidationErrors(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppLayout title="Modifier l'agent" subtitle="Référentiel du personnel">
    <div class="max-w-3xl mx-auto">
      <AppCard>
        <p v-if="loading" class="text-center text-gray-400 font-body py-8">Chargement…</p>
        <AgentForm
          v-else
          :form="form"
          mode="edit"
          :submitting="submitting"
          :server-errors="serverErrors"
          @submit="submit"
          @cancel="router.push(`/agents/${id}`)"
        />
      </AppCard>
    </div>
  </AppLayout>
</template>
