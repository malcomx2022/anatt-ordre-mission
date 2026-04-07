<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppCard from '@/components/shared/AppCard.vue'
import { usePaiements } from '@/composables/usePaiements'
import type { Paiement, OperateurPaiement } from '@/types'

const router = useRouter()
const { generateMtn, loading } = usePaiements()

const currentStep = ref(1)
const typePaiement = ref('AVANCE')
const dateDebut = ref('2026-03-01')
const dateFin = ref('2026-03-31')
const validOnly = ref(true)

interface PaiementCandidat {
  id: number
  refOM: string
  agent: string
  montant: number
  telephone: string
  statut: 'OK' | 'DOUBLON'
  selected: boolean
}

const candidats = ref<PaiementCandidat[]>([
  { id: 1, refOM: 'OM-000793', agent: 'MAGBE Victorin', montant: 20000, telephone: '22999707480', statut: 'OK', selected: true },
  { id: 2, refOM: 'OM-000791', agent: 'SAOSSI Paterne', montant: 29333, telephone: '22997123456', statut: 'OK', selected: true },
  { id: 3, refOM: 'OM-000790', agent: 'KONE Jean-Baptiste', montant: 13333, telephone: '22996456789', statut: 'OK', selected: true },
  { id: 4, refOM: 'OM-000788', agent: 'GOUDJO Felicien', montant: 16667, telephone: '22999887766', statut: 'OK', selected: true },
  { id: 5, refOM: 'OM-000795', agent: 'AHOUANSOU Eric', montant: 15000, telephone: '22999707480', statut: 'DOUBLON', selected: false },
])

const allSelected = computed({
  get: () => candidats.value.filter(c => c.statut === 'OK').every(c => c.selected),
  set: (val: boolean) => { candidats.value.forEach(c => { if (c.statut === 'OK') c.selected = val }) }
})

const selectedCount = computed(() => candidats.value.filter(c => c.selected).length)
const totalAmount = computed(() => candidats.value.filter(c => c.selected).reduce((s, c) => s + c.montant, 0))
const hasDoublon = computed(() => candidats.value.some(c => c.statut === 'DOUBLON'))

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}

function selectAll() { candidats.value.forEach(c => c.selected = true) }
function deselectAll() { candidats.value.forEach(c => c.selected = false) }
function excludeDoublons() { candidats.value.filter(c => c.statut === 'DOUBLON').forEach(c => c.selected = false) }

function goToStep(step: number) {
  currentStep.value = step
}

const lotGenere = ref({
  reference: 'LOT-MTN-20260405-001',
  beneficiaires: 4,
  montantTotal: '79 333',
  format: 'Excel (.xlsx)',
})
</script>

<template>
  <AppLayout title="Generation Fichiers Paiement MTN" subtitle="Creer des fichiers MTN (.xlsx) pour paiements de masse">
    <template #header-actions>
      <AppButton variant="outline" @click="router.push('/paiements')">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Historique Lots
      </AppButton>
    </template>

    <!-- Stepper -->
    <div class="flex items-center justify-center mb-8 animate-fade-up">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-display font-bold" :class="currentStep >= 1 ? (currentStep > 1 ? 'bg-anatt-500 text-white' : 'bg-anatt-500 text-white') : 'bg-gray-200 text-gray-500'">
          <svg v-if="currentStep > 1" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>1</span>
        </div>
        <span class="text-sm font-display font-medium" :class="currentStep >= 1 ? 'text-anatt-500' : 'text-gray-400'">Filtrage</span>
        <div class="w-16 h-0.5" :class="currentStep > 1 ? 'bg-anatt-500' : 'bg-gray-200'"></div>
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-display font-bold" :class="currentStep >= 2 ? (currentStep > 2 ? 'bg-anatt-500 text-white' : 'bg-anatt-500 text-white') : 'bg-gray-200 text-gray-500'">
          <svg v-if="currentStep > 2" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span v-else>2</span>
        </div>
        <span class="text-sm font-display font-medium" :class="currentStep >= 2 ? 'text-anatt-500' : 'text-gray-400'">Selection</span>
        <div class="w-16 h-0.5" :class="currentStep > 2 ? 'bg-anatt-500' : 'bg-gray-200'"></div>
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-display font-bold" :class="currentStep >= 3 ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-500'">
          <span>3</span>
        </div>
        <span class="text-sm font-display font-medium" :class="currentStep >= 3 ? 'text-anatt-500' : 'text-gray-400'">Generation</span>
      </div>
    </div>

    <!-- Step 1: Filtrage -->
    <div v-if="currentStep === 1" class="animate-fade-up">
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Criteres de Filtrage
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Operateur</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 px-4 py-3 border-2 border-yellow-400 bg-yellow-50 rounded-xl cursor-pointer flex-1">
                <input type="radio" value="MTN" checked disabled class="rounded-full border-gray-300 text-anatt-500">
                <span class="text-sm font-display font-bold text-yellow-800">MTN MoMo</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Type de Paiement <span class="text-red-500">*</span></label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 px-4 py-3 rounded-xl cursor-pointer flex-1 border-2 transition-all duration-200" :class="typePaiement === 'AVANCE' ? 'border-anatt-400 bg-anatt-50' : 'border-gray-200 hover:bg-anatt-50/30'">
                <input type="radio" v-model="typePaiement" value="AVANCE" class="rounded-full border-gray-300 text-anatt-500">
                <span class="text-sm font-display font-medium" :class="typePaiement === 'AVANCE' ? 'text-anatt-800' : 'text-gray-700'">Avance (1/3)</span>
              </label>
              <label class="flex items-center gap-2 px-4 py-3 rounded-xl cursor-pointer flex-1 border-2 transition-all duration-200" :class="typePaiement === 'RELIQUAT' ? 'border-anatt-400 bg-anatt-50' : 'border-gray-200 hover:bg-anatt-50/30'">
                <input type="radio" v-model="typePaiement" value="RELIQUAT" class="rounded-full border-gray-300 text-anatt-500">
                <span class="text-sm font-display font-medium" :class="typePaiement === 'RELIQUAT' ? 'text-anatt-800' : 'text-gray-700'">Reliquat (2/3)</span>
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Periode du</label>
            <input v-model="dateDebut" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Au</label>
            <input v-model="dateFin" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200">
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2">
          <input v-model="validOnly" type="checkbox" id="validOnlyMtn" class="rounded border-gray-300 text-anatt-500">
          <label for="validOnlyMtn" class="text-sm font-body text-gray-700">Uniquement les OMs valides</label>
        </div>
      </div>
      <div class="flex justify-end">
        <AppButton @click="goToStep(2)">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          Rechercher les paiements
        </AppButton>
      </div>
    </div>

    <!-- Step 2: Selection -->
    <div v-if="currentStep === 2" class="animate-fade-up">
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-base font-display font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12H3"/><path d="m16 6-4 6 4 6"/><path d="M21 12h-4"/></svg>
            Resultats -- <span class="text-anatt-500">{{ candidats.length }} paiements trouves</span>
          </h3>
          <div class="flex gap-2">
            <button @click="selectAll" class="text-xs font-display px-3 py-1.5 bg-anatt-50 text-anatt-500 rounded-lg hover:bg-anatt-100 transition-colors">Tout selectionner</button>
            <button @click="deselectAll" class="text-xs font-display px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors">Tout deselectionner</button>
          </div>
        </div>

        <!-- Doublon alert -->
        <div v-if="hasDoublon" class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 text-amber-500 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span class="text-sm font-body text-amber-700">1 doublon detecte -- <strong>OM-000795</strong> (meme numero que OM-000793)</span>
          <button @click="excludeDoublons" class="ml-auto text-xs font-display px-3 py-1 bg-amber-200 text-amber-800 rounded-lg hover:bg-amber-300 transition-colors">Exclure doublons</button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs font-display text-gray-500 uppercase tracking-wider border-b border-gray-100">
                <th class="pb-3 pr-3"><input type="checkbox" v-model="allSelected" class="rounded border-gray-300 text-anatt-500"></th>
                <th class="pb-3 pr-3">Ref OM</th>
                <th class="pb-3 pr-3">Agent</th>
                <th class="pb-3 pr-3">Montant</th>
                <th class="pb-3 pr-3">Telephone</th>
                <th class="pb-3 pr-3">Operateur</th>
                <th class="pb-3">Statut</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="c in candidats" :key="c.id" class="hover:bg-gray-50 transition-colors" :class="c.statut === 'DOUBLON' ? 'bg-red-50/50' : ''">
                <td class="py-3 pr-3"><input type="checkbox" v-model="c.selected" class="rounded border-gray-300 text-anatt-500"></td>
                <td class="py-3 pr-3">
                  <span class="font-mono text-xs px-2 py-1 rounded" :class="c.statut === 'DOUBLON' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'">{{ c.refOM }}</span>
                </td>
                <td class="py-3 pr-3 font-body font-medium" :class="c.statut === 'DOUBLON' ? 'text-red-700' : 'text-gray-800'">{{ c.agent }}</td>
                <td class="py-3 pr-3 font-display font-semibold text-gray-800">{{ formatMontant(c.montant) }}</td>
                <td class="py-3 pr-3 font-mono text-xs" :class="c.statut === 'DOUBLON' ? 'text-red-600' : 'text-gray-600'">{{ c.telephone }}</td>
                <td class="py-3 pr-3"><span class="bg-yellow-100 text-yellow-800 text-xs font-display font-bold px-2 py-0.5 rounded">MTN</span></td>
                <td class="py-3">
                  <span v-if="c.statut === 'OK'" class="text-anatt-600 text-xs flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    OK
                  </span>
                  <span v-else class="text-red-600 text-xs flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Doublon
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recap -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
          Recapitulatif
        </h3>
        <div class="grid sm:grid-cols-3 gap-4">
          <div class="bg-anatt-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-anatt-500">{{ selectedCount }}</p>
            <p class="text-xs font-body text-anatt-600">Beneficiaires selectionnes</p>
          </div>
          <div class="bg-blue-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-blue-700">{{ formatMontant(totalAmount) }}</p>
            <p class="text-xs font-body text-blue-600">Montant total (FCFA)</p>
          </div>
          <div class="bg-yellow-50 rounded-xl p-4 text-center">
            <p class="text-2xl font-display font-bold text-yellow-700">MTN</p>
            <p class="text-xs font-body text-yellow-600">Format : Excel (.xlsx)</p>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between">
        <AppButton variant="outline" @click="goToStep(1)">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Retour filtrage
        </AppButton>
        <AppButton @click="goToStep(3)">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
          Generer le fichier MTN
        </AppButton>
      </div>
    </div>

    <!-- Step 3: Success -->
    <div v-if="currentStep === 3" class="animate-fade-up">
      <div class="max-w-lg mx-auto text-center">
        <div class="bg-white rounded-2xl border border-gray-100 p-8 mb-6">
          <div class="w-20 h-20 bg-anatt-50 rounded-full mx-auto mb-5 flex items-center justify-center">
            <svg class="w-10 h-10 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h2 class="text-xl font-display font-bold text-gray-800 mb-2">Fichier genere avec succes !</h2>
          <p class="text-sm font-body text-gray-500 mb-6">Le lot de paiement a ete cree et le fichier est pret au telechargement.</p>
          <div class="bg-gray-50 rounded-xl p-4 mb-6 text-left space-y-2">
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Reference lot :</span><span class="font-mono font-bold text-gray-800">{{ lotGenere.reference }}</span></div>
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Operateur :</span><span class="font-display font-bold text-yellow-700">MTN MoMo</span></div>
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Type :</span><span class="font-body font-medium text-gray-700">{{ typePaiement === 'AVANCE' ? 'Avance (1/3)' : 'Reliquat (2/3)' }}</span></div>
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Beneficiaires :</span><span class="font-display font-bold text-gray-800">{{ lotGenere.beneficiaires }}</span></div>
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Montant total :</span><span class="font-display font-bold text-anatt-500">{{ lotGenere.montantTotal }} FCFA</span></div>
            <div class="flex justify-between text-sm"><span class="font-body text-gray-500">Format :</span><span class="font-body font-medium text-gray-700">{{ lotGenere.format }}</span></div>
          </div>
          <div class="flex gap-3">
            <AppButton class="flex-1">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Telecharger le fichier
            </AppButton>
            <AppButton variant="outline" class="flex-1" @click="router.push('/paiements/statuts')">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              Voir le lot
            </AppButton>
          </div>
        </div>
        <div class="flex gap-3 justify-center">
          <button @click="goToStep(1)" class="px-5 py-2.5 text-sm font-display text-anatt-500 font-medium hover:text-anatt-600 transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Generer un autre fichier
          </button>
          <button @click="router.push('/paiements')" class="px-5 py-2.5 text-sm font-display text-gray-500 font-medium hover:text-gray-700 transition-colors flex items-center gap-1">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Voir tous les lots
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
