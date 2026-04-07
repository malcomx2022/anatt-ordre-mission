<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppBadge from '@/components/shared/AppBadge.vue'
import AppModal from '@/components/shared/AppModal.vue'

const router = useRouter()
const step = ref(1)
const loading = ref(false)
const showSuccessModal = ref(false)
const searchBeneficiaire = ref('')
const showSuggestions = ref(false)

// Form state
const form = ref({
  semo: 'SEMO-4523',
  objet: '',
  lieu_depart: 'Cotonou',
  destination: '',
  date_depart: '',
  date_retour: '',
  transport: '',
  notes: '',
})

// Transport modes
const transports = [
  { value: 'vehicule_service', label: 'Vehicule de service', icon: 'car' },
  { value: 'location', label: 'Location', icon: 'key' },
  { value: 'transport_public', label: 'Transport public', icon: 'bus' },
  { value: 'avion', label: 'Avion', icon: 'plane' },
]

// Mock agents database
const agentsDB = ref([
  { id: 1, initiales: 'MV', nom: 'MAGBE Victorin', matricule: 'MAT-0045', role: 'Cadre', salaire: 850000, categorie: '>=800K', color: 'blue', telephone: '229 99 70 74 80' },
  { id: 2, initiales: 'AP', nom: 'ADJOVI Pierre', matricule: 'MAT-0023', role: 'Agent', salaire: 320000, categorie: '<400K', color: 'green', telephone: '229 01 66 33 55' },
  { id: 3, initiales: 'KJ', nom: 'KONE Jean', matricule: 'MAT-0067', role: 'Agent', salaire: 320000, categorie: '<400K', color: 'amber', telephone: '229 97 12 34 56' },
  { id: 4, initiales: 'HC', nom: 'HOUNGBEDJI Claude', matricule: 'MAT-0034', role: 'Cadre', salaire: 900000, categorie: '>=800K', color: 'indigo', telephone: '229 96 78 90 12' },
  { id: 5, initiales: 'TB', nom: 'TOSSOU Beatrice', matricule: 'MAT-0078', role: 'Agent', salaire: 280000, categorie: '<400K', color: 'pink', telephone: '229 95 23 45 67' },
])

// Taux per diem par categorie
const tauxParCategorie: Record<string, { repas1: number; repas2: number; nuitee: number }> = {
  '>=800K': { repas1: 15000, repas2: 25000, nuitee: 45000 },
  '400-800K': { repas1: 12000, repas2: 20000, nuitee: 35000 },
  '<400K': { repas1: 10000, repas2: 15000, nuitee: 30000 },
}

// Selected beneficiaires
interface Beneficiaire {
  id: number
  initiales: string
  nom: string
  matricule: string
  role: string
  salaire: number
  categorie: string
  color: string
  telephone: string
  jours_1_repas: number
  jours_2_repas: number
  nuitees: number
  operateur: 'MTN' | 'MOOV'
}

const beneficiaires = ref<Beneficiaire[]>([])

// Computed
const nbJours = computed(() => {
  if (!form.value.date_depart || !form.value.date_retour) return 0
  const d1 = new Date(form.value.date_depart)
  const d2 = new Date(form.value.date_retour)
  return Math.max(0, Math.ceil((d2.getTime() - d1.getTime()) / 86400000) + 1)
})

const filteredAgents = computed(() => {
  if (!searchBeneficiaire.value) return agentsDB.value
  const q = searchBeneficiaire.value.toLowerCase()
  return agentsDB.value.filter(a =>
    a.nom.toLowerCase().includes(q) || a.matricule.toLowerCase().includes(q)
  )
})

function getTaux(categorie: string) {
  return tauxParCategorie[categorie] || tauxParCategorie['<400K']
}

function beneficiaireTotal(b: Beneficiaire): number {
  const t = getTaux(b.categorie)
  return (b.jours_1_repas * t.repas1) + (b.jours_2_repas * t.repas2) + (b.nuitees * t.nuitee)
}

const totalGeneral = computed(() => beneficiaires.value.reduce((s, b) => s + beneficiaireTotal(b), 0))
const avance = computed(() => Math.round(totalGeneral.value * 0.33))
const reliquat = computed(() => totalGeneral.value - avance.value)

const step1Valid = computed(() => form.value.semo && form.value.objet && form.value.lieu_depart && form.value.destination && form.value.date_depart && form.value.date_retour && form.value.transport)
const step2Valid = computed(() => beneficiaires.value.length > 0)

const checklist = computed(() => [
  { label: 'Informations mission completes', ok: !!step1Valid.value },
  { label: 'Au moins un beneficiaire', ok: beneficiaires.value.length > 0 },
  { label: 'Numeros de telephone valides', ok: beneficiaires.value.every(b => b.telephone) },
  { label: 'Operateurs selectionnes', ok: beneficiaires.value.every(b => b.operateur) },
])

const allChecksOk = computed(() => checklist.value.every(c => c.ok))

// Methods
function nextStep() { if (step.value < 3) step.value++ }
function prevStep() { if (step.value > 1) step.value-- }

function addBeneficiaire(agent: typeof agentsDB.value[0]) {
  if (beneficiaires.value.find(b => b.id === agent.id)) return
  beneficiaires.value.push({
    ...agent,
    jours_1_repas: 1,
    jours_2_repas: 1,
    nuitees: Math.max(0, nbJours.value - 1),
    operateur: 'MTN',
  })
  searchBeneficiaire.value = ''
  showSuggestions.value = false
}

function removeBeneficiaire(id: number) {
  beneficiaires.value = beneficiaires.value.filter(b => b.id !== id)
}

function formatMontant(val: number): string {
  return val.toLocaleString('fr-FR')
}

function handleSubmit() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    showSuccessModal.value = true
  }, 1500)
}

function handleSaveDraft() {
  router.push('/ordres')
}

const stepLabels = ['Informations', 'Beneficiaires', 'Recapitulatif']
const stepSubLabels = ['Details de la mission', 'Selection agents', 'Validation finale']
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
