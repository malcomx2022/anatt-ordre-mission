<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import { baremesService } from '@/services/baremes'
import { useToast } from '@/composables/useToast'
import type { Bareme, ZoneCode } from '@/types'

const toast = useToast()
const loading = ref(true)
const saving = ref(false)
const baremes = ref<Bareme[]>([])
const modifies = ref<Record<number, number>>({})

const ZONES: { value: ZoneCode; label: string }[] = [
  { value: 'INTERIEUR', label: 'Intérieur' },
  { value: 'EXT_ZONE_I', label: 'Zone I' },
  { value: 'EXT_ZONE_II', label: 'Zone II' },
  { value: 'EXT_ZONE_III', label: 'Zone III' },
]
const zone = ref<ZoneCode>('INTERIEUR')

const fmt = (n: number) => n.toLocaleString('fr-FR')

function baremesZone() {
  return baremes.value.filter((b) => b.zone_code === zone.value)
}

function onEdit(b: Bareme, valeur: string) {
  const n = Number(valeur)
  if (!Number.isNaN(n) && n !== b.taux_journalier) modifies.value[b.id] = n
  else delete modifies.value[b.id]
}

async function enregistrer() {
  const ids = Object.keys(modifies.value).map(Number)
  if (!ids.length) { toast.warning('Aucune modification.'); return }
  saving.value = true
  try {
    await Promise.all(ids.map((id) => baremesService.update(id, { taux_journalier: modifies.value[id]! })))
    toast.success(`${ids.length} taux mis à jour.`)
    modifies.value = {}
    baremes.value = await baremesService.list()
  } catch {
    toast.error("Échec de l'enregistrement.")
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    baremes.value = await baremesService.list()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppLayout title="Modifier le barème" subtitle="Taux journaliers (FCFA)">
    <template #header-actions>
      <router-link to="/taux" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Barème</router-link>
    </template>

    <div class="flex flex-wrap gap-2 mb-6">
      <button v-for="z in ZONES" :key="z.value" class="px-4 py-2.5 rounded-xl text-sm font-display font-semibold" :class="zone === z.value ? 'bg-anatt-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'" @click="zone = z.value">{{ z.label }}</button>
    </div>

    <AppCard>
      <p v-if="loading" class="text-center text-gray-400 font-body py-8">Chargement…</p>
      <div v-else>
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
              <th class="py-2">Catégorie</th><th>Type</th><th class="text-right">Taux journalier (FCFA)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in baremesZone()" :key="b.id" class="border-b border-gray-50">
              <td class="py-2 font-display font-semibold text-anatt-700">{{ b.categorie }}</td>
              <td>{{ b.type_prise_en_charge }}</td>
              <td class="text-right">
                <input
                  type="number" min="0" :value="b.taux_journalier"
                  class="w-32 px-3 py-1.5 rounded-lg border border-gray-200 text-right text-sm font-body focus:outline-none focus:border-anatt-500"
                  :class="modifies[b.id] !== undefined ? 'border-gold-400 bg-gold-50' : ''"
                  @input="onEdit(b, ($event.target as HTMLInputElement).value)"
                >
              </td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end mt-5">
          <AppButton :loading="saving" @click="enregistrer">Enregistrer les modifications</AppButton>
        </div>
      </div>
    </AppCard>
  </AppLayout>
</template>
