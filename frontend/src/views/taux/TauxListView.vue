<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { baremesService } from '@/services/baremes'
import { useAuth } from '@/composables/useAuth'
import { CATEGORIES, CATEGORIE_LABELS, typesPourZone } from '@/types'
import type { Bareme, Categorie, ZoneCode, TypePriseEnCharge } from '@/types'

const { canManageBaremes } = useAuth()
const loading = ref(true)
const baremes = ref<Bareme[]>([])
const zone = ref<ZoneCode>('INTERIEUR')

const ZONES: { value: ZoneCode; label: string }[] = [
  { value: 'INTERIEUR', label: 'Intérieur (Bénin)' },
  { value: 'EXT_ZONE_I', label: 'Extérieur — Zone I' },
  { value: 'EXT_ZONE_II', label: 'Extérieur — Zone II' },
  { value: 'EXT_ZONE_III', label: 'Extérieur — Zone III' },
]

const TYPE_LABELS: Record<TypePriseEnCharge, string> = {
  UN_REPAS: 'Un repas', DEUX_REPAS: 'Deux repas', JOURNEE_COMPLETE: 'Journée complète',
  LOGE_NOURRI: 'Logé + nourri', LOGE_SEUL: 'Logé seul', NOURRI_SEUL: 'Nourri seul',
}

const types = computed(() => typesPourZone(zone.value))
const fmt = (n: number) => n.toLocaleString('fr-FR')

function taux(categorie: Categorie, type: TypePriseEnCharge): number | null {
  const b = baremes.value.find((x) => x.categorie === categorie && x.zone_code === zone.value && x.type_prise_en_charge === type)
  return b ? b.taux_journalier : null
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
  <AppLayout title="Barème des indemnités" subtitle="Taux journaliers ANaTT par catégorie, zone et prise en charge">
    <template #header-actions>
      <router-link v-if="canManageBaremes()" to="/taux/configurer" class="px-4 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-semibold hover:bg-anatt-600">Modifier le barème</router-link>
    </template>

    <div class="flex flex-wrap gap-2 mb-6">
      <button
        v-for="z in ZONES" :key="z.value"
        class="px-4 py-2.5 rounded-xl text-sm font-display font-semibold"
        :class="zone === z.value ? 'bg-anatt-500 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'"
        @click="zone = z.value"
      >{{ z.label }}</button>
    </div>

    <AppCard>
      <p v-if="loading" class="text-center text-gray-400 font-body py-8">Chargement…</p>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-display text-gray-500 uppercase">
              <th class="px-4 py-3">Catégorie</th>
              <th v-for="t in types" :key="t" class="px-4 py-3 text-right">{{ TYPE_LABELS[t] }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="c in CATEGORIES" :key="c" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-display font-bold text-anatt-700">{{ CATEGORIE_LABELS[c] }}</td>
              <td v-for="t in types" :key="t" class="px-4 py-3 text-right">
                <span v-if="taux(c, t) !== null" class="font-display font-semibold text-gray-800">{{ fmt(taux(c, t)!) }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
        <p class="text-[11px] text-gray-400 font-body mt-3">Montants en FCFA / jour. EXT_ZONE_II et EXT_ZONE_III ont des taux identiques.</p>
      </div>
    </AppCard>
  </AppLayout>
</template>
