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
  <AppLayout title="Nouvel Ordre de Mission" :subtitle="`Etape ${step} sur 3 — ${stepLabels[step - 1]}`">
    <template #header-actions>
      <span class="text-xs text-gray-400 font-body">Ref. auto : OM-2026-000795</span>
    </template>

    <!-- Wizard Progress -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-6 animate-fade-up">
      <div class="flex items-center justify-between max-w-2xl mx-auto">
        <template v-for="s in 3" :key="s">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center font-display font-bold text-sm" :class="s < step ? 'bg-anatt-500 text-white' : s === step ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-400'">
              <svg v-if="s < step" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
              <span v-else>{{ s }}</span>
            </div>
            <div class="hidden sm:block">
              <p class="text-sm font-display" :class="s <= step ? 'font-semibold text-anatt-600' : 'font-medium text-gray-400'">{{ stepLabels[s - 1] }}</p>
              <p class="text-[11px]" :class="s < step ? 'text-gray-400' : s === step ? 'text-gray-400' : 'text-gray-300'">{{ s < step ? 'Complete' : stepSubLabels[s - 1] }}</p>
            </div>
          </div>
          <div v-if="s < 3" class="flex-1 h-0.5 mx-4 max-w-[100px]" :class="s < step ? 'bg-anatt-500' : 'bg-gray-200'" />
        </template>
      </div>
    </div>

    <!-- ============ STEP 1: INFORMATIONS ============ -->
    <div v-if="step === 1" class="max-w-4xl animate-fade-up space-y-6">
      <!-- References -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
          References
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Reference SEMO <span class="text-red-500">*</span></label>
            <input v-model="form.semo" type="text" placeholder="Ex: SEMO-4523" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
            <p class="text-[11px] text-gray-400 font-body mt-1">Numero du Systeme Electronique de Mouvement d'Ordre</p>
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Objet de la mission <span class="text-red-500">*</span></label>
            <input v-model="form.objet" type="text" placeholder="Ex: Inspection technique routiere" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
        </div>
      </AppCard>

      <!-- Itineraire -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          Itineraire
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Lieu de depart <span class="text-red-500">*</span></label>
            <input v-model="form.lieu_depart" type="text" placeholder="Ex: Cotonou" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Destination <span class="text-red-500">*</span></label>
            <input v-model="form.destination" type="text" placeholder="Ex: Parakou" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
        </div>
      </AppCard>

      <!-- Periode -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          Periode de la mission
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de debut <span class="text-red-500">*</span></label>
            <input v-model="form.date_depart" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Date de fin <span class="text-red-500">*</span></label>
            <input v-model="form.date_retour" type="date" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-sm font-display font-medium text-gray-700 mb-1.5">Duree</label>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-anatt-50 border border-anatt-200 rounded-xl">
              <svg class="w-4 h-4 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span class="text-sm font-display font-bold text-anatt-700">{{ nbJours > 0 ? nbJours + ' jour(s)' : '--' }}</span>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Transport -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-2-2.2-3.3C13 5.6 12 5 11 5H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
          Mode de transport <span class="text-red-500">*</span>
        </h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <label
            v-for="t in transports" :key="t.value"
            class="border-2 rounded-xl p-4 text-center cursor-pointer transition-all card-hover"
            :class="form.transport === t.value ? 'border-anatt-500 bg-anatt-50' : 'border-gray-200 hover:border-anatt-300'"
          >
            <input v-model="form.transport" type="radio" :value="t.value" class="sr-only">
            <svg v-if="t.icon === 'car'" class="w-6 h-6 mx-auto mb-2" :class="form.transport === t.value ? 'text-anatt-600' : 'text-gray-400'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-2-2.2-3.3C13 5.6 12 5 11 5H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
            <svg v-if="t.icon === 'key'" class="w-6 h-6 mx-auto mb-2" :class="form.transport === t.value ? 'text-anatt-600' : 'text-gray-400'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.3 9.3"/><path d="m18.5 5.5-2 2"/></svg>
            <svg v-if="t.icon === 'bus'" class="w-6 h-6 mx-auto mb-2" :class="form.transport === t.value ? 'text-anatt-600' : 'text-gray-400'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/></svg>
            <svg v-if="t.icon === 'plane'" class="w-6 h-6 mx-auto mb-2" :class="form.transport === t.value ? 'text-anatt-600' : 'text-gray-400'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
            <p class="text-xs font-display font-semibold" :class="form.transport === t.value ? 'text-anatt-700' : 'text-gray-600'">{{ t.label }}</p>
          </label>
        </div>
      </AppCard>

      <!-- Notes -->
      <AppCard>
        <h2 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
          Notes / Observations
        </h2>
        <textarea v-model="form.notes" rows="3" placeholder="Informations complementaires sur la mission..." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all resize-none"></textarea>
      </AppCard>

      <!-- Actions Step 1 -->
      <div class="flex items-center justify-between">
        <AppButton variant="ghost" @click="handleSaveDraft">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
          Sauvegarder brouillon
        </AppButton>
        <AppButton :disabled="!step1Valid" @click="nextStep">
          Suivant : Beneficiaires
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </AppButton>
      </div>
    </div>

    <!-- ============ STEP 2: BENEFICIAIRES (E3) ============ -->
    <div v-if="step === 2" class="animate-fade-up">
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Colonne principale -->
        <div class="xl:col-span-2 space-y-6">
          <!-- Recherche beneficiaire -->
          <AppCard>
            <h2 class="text-base font-display font-bold text-gray-800 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
              Ajouter un beneficiaire
            </h2>
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                v-model="searchBeneficiaire"
                type="text"
                placeholder="Rechercher par nom, matricule..."
                class="w-full pl-9 pr-4 py-3 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all"
                @focus="showSuggestions = true"
                @blur="setTimeout(() => showSuggestions = false, 200)"
              >
              <!-- Autocomplete dropdown -->
              <div v-if="showSuggestions && searchBeneficiaire.length > 0 && filteredAgents.length > 0" class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-xl mt-1 shadow-lg z-20 max-h-60 overflow-y-auto">
                <div
                  v-for="agent in filteredAgents"
                  :key="agent.id"
                  class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
                  @mousedown.prevent="addBeneficiaire(agent)"
                >
                  <div class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-display font-bold" :class="`bg-${agent.color}-100 text-${agent.color}-600`">{{ agent.initiales }}</div>
                  <div class="flex-1">
                    <p class="text-sm font-display font-medium text-gray-800">{{ agent.nom }}</p>
                    <p class="text-[11px] text-gray-400 font-body">{{ agent.matricule }} - {{ agent.role }} - {{ formatMontant(agent.salaire) }} FCFA</p>
                  </div>
                  <span class="text-[10px] bg-gray-100 text-gray-500 px-2 py-1 rounded-lg font-body">{{ agent.categorie }}</span>
                </div>
              </div>
            </div>
          </AppCard>

          <!-- Beneficiaires selectionnes -->
          <AppCard>
            <div class="flex items-center justify-between mb-5">
              <h2 class="text-base font-display font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-anatt-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Beneficiaires selectionnes
                <span class="text-xs bg-anatt-100 text-anatt-700 px-2 py-0.5 rounded-full ml-1 font-display">{{ beneficiaires.length }}</span>
              </h2>
            </div>

            <!-- Empty state -->
            <div v-if="beneficiaires.length === 0" class="text-center py-8 border-2 border-dashed border-gray-200 rounded-xl">
              <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
              <p class="text-sm text-gray-400 font-body">Recherchez et ajoutez des beneficiaires ci-dessus</p>
            </div>

            <!-- Beneficiaire cards -->
            <div v-for="b in beneficiaires" :key="b.id" class="border border-gray-200 rounded-xl p-5 mb-4 last:mb-0">
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg flex items-center justify-center text-xs font-display font-bold" :class="`bg-${b.color}-100 text-${b.color}-600`">{{ b.initiales }}</div>
                  <div>
                    <p class="font-display font-semibold text-gray-800">{{ b.nom }}</p>
                    <p class="text-[11px] text-gray-400 font-body">{{ b.role }} - {{ formatMontant(b.salaire) }} FCFA - Categorie <span class="font-semibold text-anatt-600">{{ b.categorie }}</span></p>
                  </div>
                </div>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors" @click="removeBeneficiaire(b.id)">
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
              </div>
              <!-- Ventilation des jours -->
              <div class="bg-gray-50 rounded-lg p-4">
                <p class="text-xs font-display font-semibold text-gray-500 uppercase mb-3">Ventilation des jours (mission: {{ nbJours }} jours)</p>
                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label class="text-[11px] text-gray-500 font-body block mb-1">Jours 1 repas</label>
                    <div class="flex items-center gap-2">
                      <input v-model.number="b.jours_1_repas" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500">
                      <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(getTaux(b.categorie).repas1) }} = <strong class="text-gray-700">{{ formatMontant(b.jours_1_repas * getTaux(b.categorie).repas1) }} F</strong></span>
                    </div>
                  </div>
                  <div>
                    <label class="text-[11px] text-gray-500 font-body block mb-1">Jours 2 repas</label>
                    <div class="flex items-center gap-2">
                      <input v-model.number="b.jours_2_repas" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500">
                      <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(getTaux(b.categorie).repas2) }} = <strong class="text-gray-700">{{ formatMontant(b.jours_2_repas * getTaux(b.categorie).repas2) }} F</strong></span>
                    </div>
                  </div>
                  <div>
                    <label class="text-[11px] text-gray-500 font-body block mb-1">Nuitees</label>
                    <div class="flex items-center gap-2">
                      <input v-model.number="b.nuitees" type="number" min="0" :max="nbJours" class="w-16 px-3 py-2 border border-gray-200 rounded-lg text-sm font-body text-center focus:outline-none focus:border-anatt-500">
                      <span class="text-[11px] text-gray-400 font-body">x {{ formatMontant(getTaux(b.categorie).nuitee) }} = <strong class="text-gray-700">{{ formatMontant(b.nuitees * getTaux(b.categorie).nuitee) }} F</strong></span>
                    </div>
                  </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-200 flex justify-end">
                  <p class="text-sm font-display font-bold text-anatt-700">Total : {{ formatMontant(beneficiaireTotal(b)) }} FCFA</p>
                </div>
              </div>
            </div>
          </AppCard>

          <!-- Actions Step 2 -->
          <div class="flex items-center justify-between">
            <AppButton variant="outline" @click="prevStep">
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
              Precedent
            </AppButton>
            <AppButton :disabled="!step2Valid" @click="nextStep">
              Suivant : Recapitulatif
              <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </AppButton>
          </div>
        </div>

        <!-- Sidebar: Resume mission -->
        <div class="space-y-6">
          <AppCard>
            <h3 class="text-sm font-display font-bold text-gray-800 mb-4">Resume de la mission</h3>
            <div class="space-y-3 text-sm font-body">
              <div class="flex justify-between"><span class="text-gray-500">SEMO</span><span class="font-mono font-medium">{{ form.semo }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Itineraire</span><span class="font-medium">{{ form.lieu_depart }} → {{ form.destination }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Duree</span><span class="font-medium text-anatt-600">{{ nbJours }} jour(s)</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Transport</span><span class="font-medium">{{ transports.find(t => t.value === form.transport)?.label || '--' }}</span></div>
            </div>
          </AppCard>

          <AppCard>
            <h3 class="text-sm font-display font-bold text-gray-800 mb-4">Bareme de reference</h3>
            <div class="space-y-2">
              <div v-for="(rates, cat) in tauxParCategorie" :key="cat" class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs font-display font-bold text-gray-700 mb-1">{{ cat }}</p>
                <div class="grid grid-cols-3 gap-1 text-[10px] text-gray-500 font-body">
                  <span>1 repas: {{ formatMontant(rates.repas1) }}</span>
                  <span>2 repas: {{ formatMontant(rates.repas2) }}</span>
                  <span>Nuitee: {{ formatMontant(rates.nuitee) }}</span>
                </div>
              </div>
            </div>
          </AppCard>

          <!-- Recap financier -->
          <div v-if="beneficiaires.length > 0" class="bg-gradient-to-br from-anatt-600 to-anatt-800 rounded-2xl p-5 text-white">
            <h3 class="text-sm font-display font-bold mb-4">Recap financier</h3>
            <div class="space-y-2 text-sm font-body">
              <div class="flex justify-between"><span class="text-anatt-200">Beneficiaires</span><span class="font-display font-bold">{{ beneficiaires.length }}</span></div>
              <div class="flex justify-between"><span class="text-anatt-200">Total general</span><span class="font-display font-bold text-lg">{{ formatMontant(totalGeneral) }} F</span></div>
              <div class="border-t border-white/20 pt-2 mt-2">
                <div class="flex justify-between"><span class="text-anatt-200">Avance (33%)</span><span class="font-display font-semibold">{{ formatMontant(avance) }} F</span></div>
                <div class="flex justify-between"><span class="text-anatt-200">Reliquat (67%)</span><span class="font-display font-semibold">{{ formatMontant(reliquat) }} F</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ STEP 3: RECAPITULATIF (E4+E5) ============ -->
    <div v-if="step === 3" class="animate-fade-up">
      <p class="text-center text-gray-400 py-12 font-body">Step 3 - A venir (E4+E5)</p>
    </div>
  </AppLayout>
</template>
