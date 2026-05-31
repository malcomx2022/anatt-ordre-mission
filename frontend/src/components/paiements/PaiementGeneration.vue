<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { ordresService } from '@/services/ordres'
import { paiementsService } from '@/services/paiements'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import type { LotPaiement, LotPreview, Operateur, TrancheVersement } from '@/types'

const props = defineProps<{ operateurInitial?: Operateur }>()
const toast = useToast()

interface MissionItem { id: number; label: string }

const missions = ref<MissionItem[]>([])
const missionId = ref<number | null>(null)
const tranche = ref<TrancheVersement>('AVANCE')
const operateur = ref<Operateur>(props.operateurInitial ?? 'MTN')

const apercu = ref<LotPreview | null>(null)
const lot = ref<LotPaiement | null>(null)
const chargement = ref(false)
const action = ref(false)

const fmt = (v: number) => v.toLocaleString('fr-FR')
const extension = computed(() => (operateur.value === 'MTN' ? '.txt' : '.xlsx'))
const confirme = computed(() => lot.value?.statut === 'CONFIRME')
const peutGenerer = computed(() => !!apercu.value && apercu.value.nb_lignes > 0 && !confirme.value)

onMounted(async () => {
  await chargerMissions()
})

async function chargerMissions() {
  const { items } = await ordresService.list({ per_page: 100 })
  const map = new Map<number, MissionItem>()
  for (const o of items) {
    if (o.mission && !map.has(o.mission.id)) {
      map.set(o.mission.id, { id: o.mission.id, label: `${o.mission.objet} — ${o.mission.destination}` })
    }
  }
  missions.value = [...map.values()]
}

async function rafraichir() {
  if (!missionId.value) { apercu.value = null; lot.value = null; return }
  chargement.value = true
  try {
    const sel = { mission_id: missionId.value, tranche: tranche.value, operateur: operateur.value }
    apercu.value = await paiementsService.previsualiser(sel)
    const { items } = await paiementsService.lots(sel)
    lot.value = items[0] ?? null
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    chargement.value = false
  }
}

watch([missionId, tranche, operateur], rafraichir)

async function generer() {
  if (!missionId.value) return
  action.value = true
  try {
    await paiementsService.generer({ mission_id: missionId.value, tranche: tranche.value, operateur: operateur.value })
    toast.success(`Fichier ${operateur.value} ${extension.value} généré et téléchargé.`)
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function confirmer() {
  if (!lot.value) return
  action.value = true
  try {
    await paiementsService.confirmer(lot.value.id)
    toast.success('Paiement confirmé : versements soldés et horodatés.')
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function marquerErreur() {
  if (!lot.value) return
  action.value = true
  try {
    await paiementsService.marquerErreur(lot.value.id)
    toast.warning('Lot marqué en erreur. Régénération possible.')
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Sélection -->
    <AppCard>
      <h2 class="font-display font-bold text-gray-800 mb-4">Sélection du lot</h2>
      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Mission</label>
          <select v-model.number="missionId" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
            <option :value="null" disabled>Choisir une mission…</option>
            <option v-for="m in missions" :key="m.id" :value="m.id">{{ m.label }}</option>
          </select>
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Tranche</label>
          <select v-model="tranche" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
            <option value="AVANCE">Avance (33,33 %)</option>
            <option value="RELIQUAT">Reliquat (66,67 %)</option>
          </select>
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Opérateur</label>
          <select v-model="operateur" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
            <option value="MTN">MTN (.txt)</option>
            <option value="MOOV">Moov (.xlsx)</option>
          </select>
        </div>
      </div>
    </AppCard>

    <!-- Statut du lot existant -->
    <AppAlert v-if="lot" :type="confirme ? 'success' : lot.statut === 'ERREUR' ? 'error' : 'info'">
      <div class="flex items-center gap-2">
        <span>Lot {{ operateur }} / {{ tranche }} :</span>
        <PaiementStatusBadge :statut="lot.statut" />
        <span v-if="lot.nom_fichier" class="text-xs">· {{ lot.nom_fichier }}</span>
        <span v-if="confirme && lot.confirme_le" class="text-xs">· confirmé le {{ new Date(lot.confirme_le).toLocaleString('fr-FR') }}</span>
      </div>
    </AppAlert>

    <!-- Aperçu des bénéficiaires -->
    <AppCard v-if="missionId">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-bold text-gray-800">Aperçu des bénéficiaires</h2>
        <OperateurBadge :operateur="operateur" />
      </div>

      <p v-if="chargement" class="text-center text-gray-400 font-body py-6">Chargement…</p>

      <template v-else-if="apercu">
        <div v-if="!apercu.nb_lignes" class="text-center text-gray-400 font-body py-6">
          <span v-if="confirme">Tous les versements de ce lot sont confirmés (rien à régénérer).</span>
          <span v-else>Aucun versement éligible pour cette sélection.</span>
        </div>
        <div v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-sm font-body">
              <thead>
                <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                  <th class="py-2">Réf. SEMO</th>
                  <th>Bénéficiaire</th>
                  <th>Téléphone</th>
                  <th class="text-right">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in apercu.beneficiaires" :key="b.versement_id" class="border-b border-gray-50">
                  <td class="py-2 font-mono text-xs">{{ b.reference_semo }}</td>
                  <td>{{ b.beneficiaire }}</td>
                  <td class="font-mono text-xs">{{ b.telephone }}</td>
                  <td class="text-right font-semibold">{{ fmt(b.montant) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-100 font-display font-bold text-anatt-700">
                  <td class="py-2" colspan="3">{{ apercu.nb_lignes }} ligne(s)</td>
                  <td class="text-right">{{ fmt(apercu.total) }} FCFA</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-end gap-3 mt-5 pt-4 border-t border-gray-100">
        <template v-if="confirme">
          <span class="text-sm font-display font-semibold text-green-600">Lot confirmé — réinclusion bloquée (anti-double-paiement).</span>
        </template>
        <template v-else>
          <AppButton v-if="lot?.statut === 'GENERE'" variant="outline" :loading="action" @click="marquerErreur">Marquer en erreur</AppButton>
          <AppButton :disabled="!peutGenerer" :loading="action" @click="generer">
            {{ lot ? 'Régénérer le fichier' : 'Générer le fichier' }}{{ extension }}
          </AppButton>
          <AppButton v-if="lot?.statut === 'GENERE'" :loading="action" @click="confirmer">Confirmer le paiement</AppButton>
        </template>
      </div>
    </AppCard>
  </div>
</template>
