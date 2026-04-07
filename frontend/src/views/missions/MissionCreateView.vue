<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'

const router = useRouter()
const step = ref(1)
const loading = ref(false)

const form = ref({
  agent_id: '',
  objet: '',
  destination: '',
  date_depart: '',
  date_retour: '',
  taux_id: '',
})

const agents = ref([
  { id: 1, nom_complet: 'Jean KOUASSI', matricule: 'ANT-001', service: 'Direction Technique', categorie: 'A1' },
  { id: 2, nom_complet: 'Sylvie ADJOVI', matricule: 'ANT-002', service: 'Direction Administrative', categorie: 'A2' },
  { id: 3, nom_complet: 'Marc TOSSOU', matricule: 'ANT-003', service: 'Service Informatique', categorie: 'B' },
])

const taux = ref([
  { id: 1, categorie: 'A1', destination_type: 'LOCALE', montant_journalier: 25000 },
  { id: 2, categorie: 'A1', destination_type: 'REGIONALE', montant_journalier: 50000 },
  { id: 3, categorie: 'A1', destination_type: 'INTERNATIONALE', montant_journalier: 100000 },
])

const nbJours = computed(() => {
  if (!form.value.date_depart || !form.value.date_retour) return 0
  const d1 = new Date(form.value.date_depart)
  const d2 = new Date(form.value.date_retour)
  return Math.max(0, Math.ceil((d2.getTime() - d1.getTime()) / 86400000) + 1)
})

const selectedTaux = computed(() => taux.value.find(t => t.id === Number(form.value.taux_id)))
const montant = computed(() => nbJours.value * (selectedTaux.value?.montant_journalier || 0))
const selectedAgent = computed(() => agents.value.find(a => a.id === Number(form.value.agent_id)))

function nextStep() { if (step.value < 3) step.value++ }
function prevStep() { if (step.value > 1) step.value-- }

async function handleSubmit() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    router.push('/ordres')
  }, 1500)
}
</script>

<template>
  <AppLayout title="Nouvel Ordre de Mission" subtitle="Creation en 3 etapes">
    <!-- Steps indicator -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
      <div class="flex items-center justify-between max-w-2xl mx-auto">
        <div v-for="s in 3" :key="s" class="flex items-center" :class="s < 3 ? 'flex-1' : ''">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-display font-bold text-sm" :class="s <= step ? 'bg-anatt-500 text-white' : 'bg-gray-100 text-gray-400'">
              {{ s }}
            </div>
            <span class="text-sm font-display hidden sm:block" :class="s <= step ? 'text-gray-800 font-semibold' : 'text-gray-400'">
              {{ s === 1 ? 'Agent' : s === 2 ? 'Details' : 'Confirmation' }}
            </span>
          </div>
          <div v-if="s < 3" class="flex-1 h-0.5 mx-4" :class="s < step ? 'bg-anatt-500' : 'bg-gray-200'" />
        </div>
      </div>
    </div>

    <!-- Step 1: Agent -->
    <div v-if="step === 1" class="bg-white rounded-2xl border border-gray-100 p-6">
      <h2 class="text-lg font-display font-bold text-gray-800 mb-4">Selection de l'agent</h2>
      <div class="mb-4">
        <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Agent</label>
        <select v-model="form.agent_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
          <option value="">Selectionner un agent</option>
          <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.nom_complet }} ({{ a.matricule }})</option>
        </select>
      </div>
      <div v-if="selectedAgent" class="bg-anatt-50 rounded-xl p-4 mb-4">
        <p class="text-sm font-body"><strong class="font-display">Service:</strong> {{ selectedAgent.service }}</p>
        <p class="text-sm font-body"><strong class="font-display">Categorie:</strong> {{ selectedAgent.categorie }}</p>
      </div>
      <div class="flex justify-end">
        <AppButton :disabled="!form.agent_id" @click="nextStep">Suivant</AppButton>
      </div>
    </div>

    <!-- Step 2: Details -->
    <div v-if="step === 2" class="bg-white rounded-2xl border border-gray-100 p-6">
      <h2 class="text-lg font-display font-bold text-gray-800 mb-4">Details de la mission</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Objet de la mission</label>
          <input v-model="form.objet" type="text" placeholder="Ex: Formation sur les systemes de transport" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
        </div>
        <div>
          <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Destination</label>
          <input v-model="form.destination" type="text" placeholder="Ex: Parakou" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
        </div>
        <div>
          <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Taux per diem</label>
          <select v-model="form.taux_id" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
            <option value="">Selectionner un taux</option>
            <option v-for="t in taux" :key="t.id" :value="t.id">{{ t.destination_type }} - {{ t.montant_journalier.toLocaleString() }} FCFA/jour</option>
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
      <div v-if="nbJours > 0 && selectedTaux" class="mt-4 bg-anatt-50 rounded-xl p-4">
        <p class="text-sm font-body"><strong class="font-display">Duree:</strong> {{ nbJours }} jour(s)</p>
        <p class="text-sm font-body"><strong class="font-display">Montant per diem:</strong> {{ montant.toLocaleString() }} FCFA</p>
      </div>
      <div class="flex justify-between mt-6">
        <AppButton variant="outline" @click="prevStep">Precedent</AppButton>
        <AppButton :disabled="!form.objet || !form.destination || !form.date_depart || !form.date_retour" @click="nextStep">Suivant</AppButton>
      </div>
    </div>

    <!-- Step 3: Confirmation -->
    <div v-if="step === 3" class="bg-white rounded-2xl border border-gray-100 p-6">
      <h2 class="text-lg font-display font-bold text-gray-800 mb-4">Recapitulatif</h2>
      <div class="space-y-3">
        <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500 font-body">Agent</span><span class="text-sm font-body font-medium">{{ selectedAgent?.nom_complet }}</span></div>
        <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500 font-body">Objet</span><span class="text-sm font-body font-medium">{{ form.objet }}</span></div>
        <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500 font-body">Destination</span><span class="text-sm font-body font-medium">{{ form.destination }}</span></div>
        <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500 font-body">Periode</span><span class="text-sm font-body font-medium">{{ form.date_depart }} au {{ form.date_retour }}</span></div>
        <div class="flex justify-between py-2 border-b border-gray-50"><span class="text-sm text-gray-500 font-body">Duree</span><span class="text-sm font-body font-medium">{{ nbJours }} jour(s)</span></div>
        <div class="flex justify-between py-2"><span class="text-sm text-gray-500 font-body">Montant per diem</span><span class="text-lg font-display font-bold text-anatt-600">{{ montant.toLocaleString() }} FCFA</span></div>
      </div>
      <div class="flex justify-between mt-6">
        <AppButton variant="outline" @click="prevStep">Precedent</AppButton>
        <AppButton :loading="loading" @click="handleSubmit">Creer l'ordre de mission</AppButton>
      </div>
    </div>
  </AppLayout>
</template>
