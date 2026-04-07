<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'

const router = useRouter()

const currentStep = ref(1)
const selectedFile = ref<File | null>(null)
const fileName = ref('')
const fileSize = ref('')

const previewRows = ref([
  { num: 1, valid: true, matricule: 'MAT-0088', nom: 'AZONHON', prenoms: 'Claude', grade: 'Agent', salaire: '380 000', tel_mtn: '22996123456', operateur: 'MTN', error: '' },
  { num: 2, valid: true, matricule: 'MAT-0089', nom: 'BELLO', prenoms: 'Fatimata', grade: 'Cadre', salaire: '750 000', tel_mtn: '22997654321', operateur: 'MTN', error: '' },
  { num: 3, valid: false, matricule: 'MAT-0045', nom: 'CISSE', prenoms: 'Moussa', grade: 'Agent', salaire: '320 000', tel_mtn: '22901887766', operateur: 'MOOV', error: 'Matricule deja existant' },
  { num: 4, valid: true, matricule: 'MAT-0090', nom: 'DAGA', prenoms: 'Irene', grade: 'Chef Division', salaire: '520 000', tel_mtn: '22996789012', operateur: 'MTN', error: '' },
  { num: 5, valid: false, matricule: 'MAT-0091', nom: 'EKUE', prenoms: 'Pascal', grade: 'Agent', salaire: '350 000', tel_mtn: '123456', operateur: 'MTN', error: 'Numero MTN invalide' }
])

const errors = ref([
  'Ligne 3: Matricule MAT-0045 deja existant (MAGBE Victorin)',
  'Ligne 5: Numero MTN invalide (format attendu: 229XXXXXXXX)',
  'Ligne 12: Champ grade manquant'
])

const validCount = 42
const errorCount = 3
const totalLines = 45

const expectedColumns = [
  { name: 'matricule *', required: true },
  { name: 'nom *', required: true },
  { name: 'prenoms *', required: true },
  { name: 'grade *', required: true },
  { name: 'salaire_base *', required: true },
  { name: 'tel_mtn', required: false },
  { name: 'tel_moov', required: false },
  { name: 'operateur_principal *', required: true }
]

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0]
    fileName.value = target.files[0].name
    fileSize.value = `${Math.round(target.files[0].size / 1024)} Ko -- ${totalLines} lignes detectees`
  }
}

function triggerFileInput() {
  const input = document.getElementById('fileInput') as HTMLInputElement
  input?.click()
}

function resetFile() {
  selectedFile.value = null
  fileName.value = ''
  fileSize.value = ''
}

function goToStep(step: number) {
  currentStep.value = step
}
</script>

<template>
  <AppLayout title="Import du Personnel" subtitle="Importer depuis un fichier Excel">
    <template #header-actions>
      <router-link
        to="/agents"
        class="w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center hover:bg-gray-50"
      >
        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      </router-link>
    </template>

    <div class="max-w-4xl">
      <!-- Etapes -->
      <div class="flex items-center gap-4 mb-8">
        <div class="flex items-center gap-2">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-display font-bold"
            :class="currentStep >= 1 ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-500'"
          >1</div>
          <span
            class="text-sm font-display font-medium"
            :class="currentStep >= 1 ? 'text-anatt-700' : 'text-gray-500'"
          >Upload</span>
        </div>
        <div class="flex-1 h-0.5" :class="currentStep >= 2 ? 'bg-anatt-500' : 'bg-gray-200'"></div>
        <div class="flex items-center gap-2">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-display font-bold"
            :class="currentStep >= 2 ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-500'"
          >2</div>
          <span
            class="text-sm font-display font-medium"
            :class="currentStep >= 2 ? 'text-anatt-700' : 'text-gray-500'"
          >Previsualisation</span>
        </div>
        <div class="flex-1 h-0.5" :class="currentStep >= 3 ? 'bg-anatt-500' : 'bg-gray-200'"></div>
        <div class="flex items-center gap-2">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-display font-bold"
            :class="currentStep >= 3 ? 'bg-anatt-500 text-white' : 'bg-gray-200 text-gray-500'"
          >3</div>
          <span
            class="text-sm font-display font-medium"
            :class="currentStep >= 3 ? 'text-anatt-700' : 'text-gray-500'"
          >Resultat</span>
        </div>
      </div>

      <!-- Etape 1: Upload -->
      <div v-if="currentStep === 1" class="bg-white rounded-2xl border border-gray-100 p-6">
        <h3 class="text-base font-display font-bold text-gray-800 mb-2">Charger le fichier Excel</h3>
        <p class="text-sm font-body text-gray-500 mb-6">Formats acceptes: .xlsx, .xls -- Taille max: 5 Mo</p>

        <!-- Modele telechargeable -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h2"/><path d="M8 17h2"/><path d="M14 13h2"/><path d="M14 17h2"/></svg>
            <div>
              <p class="text-sm font-display font-medium text-green-800">Modele de fichier</p>
              <p class="text-xs font-body text-green-600">Telechargez le template pour le remplir</p>
            </div>
          </div>
          <button class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-display font-medium hover:bg-green-700 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Telecharger
          </button>
        </div>

        <!-- Zone de drop -->
        <div
          class="border-2 border-dashed border-gray-300 rounded-2xl p-12 text-center cursor-pointer hover:border-anatt-500 hover:bg-anatt-50 transition-all"
          @click="triggerFileInput"
        >
          <input
            id="fileInput"
            type="file"
            accept=".xlsx,.xls"
            class="hidden"
            @change="handleFileSelect"
          >
          <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
            <svg class="w-8 h-8 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </div>
          <p class="text-sm font-display font-medium text-gray-700 mb-1">Glissez-deposez votre fichier ici</p>
          <p class="text-xs font-body text-gray-500">ou cliquez pour selectionner</p>
        </div>

        <!-- Fichier selectionne -->
        <div
          v-if="selectedFile"
          class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-4 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div>
              <p class="text-sm font-display font-medium text-gray-800">{{ fileName }}</p>
              <p class="text-xs font-body text-gray-500">{{ fileSize }}</p>
            </div>
          </div>
          <button class="text-red-500 hover:text-red-700" @click="resetFile">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <!-- Colonnes attendues -->
        <div class="mt-6 bg-gray-50 rounded-xl p-4">
          <p class="text-sm font-display font-medium text-gray-700 mb-3 flex items-center gap-1">
            <svg class="w-4 h-4 text-anatt-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Colonnes attendues dans le fichier
          </p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
            <span
              v-for="col in expectedColumns"
              :key="col.name"
              class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-center font-mono"
            >
              {{ col.name }}
            </span>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button
            class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 transition-colors flex items-center gap-2"
            :class="{ 'opacity-50 cursor-not-allowed': !selectedFile }"
            :disabled="!selectedFile"
            @click="goToStep(2)"
          >
            Suivant
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
      </div>

      <!-- Etape 2: Previsualisation -->
      <div v-if="currentStep === 2">
        <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-display font-bold text-gray-800">Previsualisation ({{ totalLines }} lignes)</h3>
            <div class="flex gap-3 text-sm">
              <span class="text-green-600 font-display font-medium flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                {{ validCount }} valides
              </span>
              <span class="text-red-600 font-display font-medium flex items-center gap-1">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ errorCount }} erreurs
              </span>
            </div>
          </div>
          <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-gray-50 text-left text-gray-500 uppercase tracking-wider font-display">
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Statut</th>
                  <th class="px-3 py-2">Matricule</th>
                  <th class="px-3 py-2">Nom</th>
                  <th class="px-3 py-2">Prenoms</th>
                  <th class="px-3 py-2">Grade</th>
                  <th class="px-3 py-2">Salaire</th>
                  <th class="px-3 py-2">Tel MTN</th>
                  <th class="px-3 py-2">Operateur</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 font-body">
                <tr
                  v-for="row in previewRows"
                  :key="row.num"
                  :class="row.valid ? 'bg-green-50/50' : 'bg-red-50'"
                >
                  <td class="px-3 py-2">{{ row.num }}</td>
                  <td class="px-3 py-2">
                    <svg
                      v-if="row.valid"
                      class="w-4 h-4 text-green-500"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    >
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <svg
                      v-else
                      class="w-4 h-4 text-red-500"
                      viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                      :title="row.error"
                    >
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                  </td>
                  <td class="px-3 py-2 font-mono" :class="{ 'text-red-600 font-bold': !row.valid && row.error.includes('Matricule') }">{{ row.matricule }}</td>
                  <td class="px-3 py-2">{{ row.nom }}</td>
                  <td class="px-3 py-2">{{ row.prenoms }}</td>
                  <td class="px-3 py-2">{{ row.grade }}</td>
                  <td class="px-3 py-2">{{ row.salaire }}</td>
                  <td class="px-3 py-2 font-mono" :class="{ 'text-red-600 font-bold': !row.valid && row.error.includes('Numero') }">{{ row.tel_mtn }}</td>
                  <td class="px-3 py-2">{{ row.operateur }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mt-4 bg-red-50 border border-red-200 rounded-xl p-3">
            <p class="text-sm font-display font-medium text-red-800 mb-2 flex items-center gap-1">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Erreurs detectees ({{ errorCount }} lignes)
            </p>
            <ul class="text-xs font-body text-red-700 space-y-1">
              <li v-for="(err, idx) in errors" :key="idx">-- {{ err }}</li>
            </ul>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <button
            class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2"
            @click="goToStep(1)"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Retour
          </button>
          <div class="flex gap-3">
            <button class="px-4 py-2.5 border border-amber-300 text-amber-700 rounded-xl text-sm font-display font-medium hover:bg-amber-50">
              Ignorer erreurs ({{ validCount }} lignes)
            </button>
            <button
              class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 flex items-center gap-2"
              @click="goToStep(3)"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Importer {{ validCount }} agents
            </button>
          </div>
        </div>
      </div>

      <!-- Etape 3: Resultat -->
      <div v-if="currentStep === 3" class="bg-white rounded-2xl border border-gray-100 p-8 text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full mx-auto mb-6 flex items-center justify-center">
          <svg class="w-10 h-10 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3 class="text-xl font-display font-bold text-gray-800 mb-2">Import termine !</h3>
        <p class="text-sm font-body text-gray-500 mb-6">{{ validCount }} agents ont ete importes avec succes.</p>
        <div class="grid grid-cols-3 gap-4 max-w-md mx-auto mb-8">
          <div class="bg-green-50 rounded-xl p-4">
            <p class="text-2xl font-display font-bold text-green-700">{{ validCount }}</p>
            <p class="text-xs font-body text-green-600">Importes</p>
          </div>
          <div class="bg-red-50 rounded-xl p-4">
            <p class="text-2xl font-display font-bold text-red-700">{{ errorCount }}</p>
            <p class="text-xs font-body text-red-600">Ignores</p>
          </div>
          <div class="bg-blue-50 rounded-xl p-4">
            <p class="text-2xl font-display font-bold text-blue-700">0</p>
            <p class="text-xs font-body text-blue-600">Mis a jour</p>
          </div>
        </div>
        <div class="flex justify-center gap-3">
          <button class="px-6 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
            Voir rapport detaille
          </button>
          <router-link
            to="/agents"
            class="px-6 py-2.5 bg-anatt-500 text-white rounded-xl text-sm font-display font-medium hover:bg-anatt-600 flex items-center gap-2"
          >
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            Voir le personnel
          </router-link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
