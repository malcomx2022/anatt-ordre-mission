<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'

const route = useRoute()
const router = useRouter()
const loading = ref(false)

const form = ref({
  objet: 'Formation sur les systemes de transport intelligent',
  destination: 'Parakou',
  date_depart: '2026-04-10',
  date_retour: '2026-04-14',
  taux_id: '1',
})

async function handleSubmit() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    router.push(`/ordres/${route.params.id}`)
  }, 1000)
}
</script>

<template>
  <AppLayout title="Modifier l'ordre de mission" subtitle="Modification possible uniquement en brouillon">
    <div class="bg-white rounded-2xl border border-gray-100 p-6 max-w-2xl">
      <form @submit.prevent="handleSubmit">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Objet de la mission</label>
            <input v-model="form.objet" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Destination</label>
              <input v-model="form.destination" type="text" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Taux per diem</label>
              <select v-model="form.taux_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
                <option value="1">LOCALE - 25 000 FCFA/jour</option>
                <option value="2">REGIONALE - 50 000 FCFA/jour</option>
                <option value="3">INTERNATIONALE - 100 000 FCFA/jour</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de depart</label>
              <input v-model="form.date_depart" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
            </div>
            <div>
              <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de retour</label>
              <input v-model="form.date_retour" type="date" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
            </div>
          </div>
        </div>
        <div class="flex justify-between mt-6">
          <AppButton variant="outline" type="button" @click="router.back()">Annuler</AppButton>
          <AppButton :loading="loading" type="submit">Enregistrer</AppButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
