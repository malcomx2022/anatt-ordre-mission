<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AgentForm from '@/components/agents/AgentForm.vue'
import { agentsService } from '@/services/agents'
import { useToast } from '@/composables/useToast'
import { getValidationErrors } from '@/composables/useApi'
import type { AgentFormData } from '@/types'

const router = useRouter()
const toast = useToast()
const submitting = ref(false)
const serverErrors = ref<Record<string, string[]>>({})

const form = reactive<AgentFormData>({
  nom: '',
  prenoms: '',
  matricule: '',
  ifu: '',
  grade: '',
  indice: null,
  salaire_brut: null,
  categorie: undefined,
  telephone: '',
  operateur: 'MTN',
  actif: true,
})

async function submit() {
  submitting.value = true
  serverErrors.value = {}
  try {
    const agent = await agentsService.create(form)
    toast.success('Agent créé avec succès.')
    router.push(`/agents/${agent.id}`)
  } catch (e) {
    serverErrors.value = getValidationErrors(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppLayout title="Nouvel agent" subtitle="Référentiel du personnel">
    <div class="max-w-3xl mx-auto">
      <AppCard>
        <AgentForm
          :form="form"
          mode="create"
          :submitting="submitting"
          :server-errors="serverErrors"
          @submit="submit"
          @cancel="router.push('/agents')"
        />
      </AppCard>
    </div>
  </AppLayout>
</template>
