<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppButton from '@/components/shared/AppButton.vue'

const router = useRouter()
const loading = ref(false)

const form = ref({
  nom: '',
  prenom: '',
  email: '',
  telephone: '',
  direction: '',
  fonction: '',
  role: '',
  password: '',
  forceChange: true,
  sendEmail: true,
  activateNow: true,
})

const roles = [
  { value: 'ADMIN', label: 'Super Admin', desc: 'Acces complet au systeme, gestion des utilisateurs et configuration', color: 'red', perms: ['Tout'] },
  { value: 'DIRECTEUR', label: 'Validateur DG', desc: 'Approbation finale des ordres de mission', color: 'green', perms: ['Missions (lecture)', 'Approbation DG', 'Rapports'] },
  { value: 'CHEF_SERVICE', label: 'Chef de Service', desc: 'Validation des ordres de mission de son service', color: 'blue', perms: ['Missions (lecture)', 'Validation N1', 'Rapports'] },
  { value: 'DRH', label: 'Gestionnaire RH', desc: 'Gestion du personnel, taux per diem, paiements', color: 'amber', perms: ['Personnel', 'Missions', 'Paiements', 'Baremes'] },
  { value: 'AGENT', label: 'Agent', desc: 'Consultation uniquement, soumission de ses propres missions', color: 'gray', perms: ['Dashboard', 'Mes missions'] },
]

const selectedRole = computed(() => roles.find(r => r.value === form.value.role))

const directions = ['Direction Generale', 'Direction Technique', 'Direction Administrative', 'Direction Financiere', 'Service Informatique', 'Comptabilite']

const passwordStrength = computed(() => {
  const p = form.value.password
  let s = 0
  if (p.length >= 8) s++
  if (/[A-Z]/.test(p)) s++
  if (/[0-9]/.test(p)) s++
  if (/[^A-Za-z0-9]/.test(p)) s++
  return s
})

const strengthLabel = computed(() => ['', 'Faible', 'Moyen', 'Bon', 'Fort'][passwordStrength.value] || '')
const strengthColor = computed(() => ['', 'bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'][passwordStrength.value] || 'bg-gray-200')

function generatePassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz0123456789@#$!%&'
  let pwd = ''
  for (let i = 0; i < 14; i++) pwd += chars[Math.floor(Math.random() * chars.length)]
  form.value.password = pwd
}

async function handleSubmit() {
  loading.value = true
  setTimeout(() => {
    loading.value = false
    router.push('/admin/utilisateurs')
  }, 1500)
}
</script>

<template>
  <AppLayout title="Nouvel Utilisateur" subtitle="Creer un nouveau compte d'acces au systeme">
    <template #header-actions>
      <AppButton variant="outline" @click="router.push('/admin/utilisateurs')">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        Retour
      </AppButton>
    </template>

    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Informations personnelles -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up">
        <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Informations personnelles
        </h3>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Nom <span class="text-red-500">*</span></label>
            <input v-model="form.nom" type="text" placeholder="Nom de famille" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Prenom(s) <span class="text-red-500">*</span></label>
            <input v-model="form.prenom" type="text" placeholder="Prenom(s)" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Email professionnel <span class="text-red-500">*</span></label>
            <input v-model="form.email" type="email" placeholder="prenom.nom@anatt.bj" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Telephone</label>
            <input v-model="form.telephone" type="tel" placeholder="+229 XX XX XX XX" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Direction</label>
            <select v-model="form.direction" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body text-gray-700 focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500">
              <option value="">Selectionner...</option>
              <option v-for="d in directions" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Fonction</label>
            <input v-model="form.fonction" type="text" placeholder="Intitule du poste" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
          </div>
        </div>
      </div>

      <!-- Role et permissions -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-100">
        <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
          Role et permissions
        </h3>
        <div class="grid grid-cols-2 gap-3">
          <label
            v-for="role in roles"
            :key="role.value"
            class="border-2 rounded-xl p-4 flex items-start gap-3 cursor-pointer transition-all hover:border-anatt-500"
            :class="[
              form.role === role.value ? 'border-anatt-500 bg-anatt-50' : 'border-gray-200',
              role.value === 'AGENT' ? 'col-span-2' : ''
            ]"
          >
            <input v-model="form.role" type="radio" :value="role.value" class="mt-1">
            <div>
              <p class="text-sm font-display font-bold text-gray-800">{{ role.label }}</p>
              <p class="text-[11px] text-gray-500 font-body mt-0.5">{{ role.desc }}</p>
              <div class="flex flex-wrap gap-1 mt-2">
                <span
                  v-for="perm in role.perms"
                  :key="perm"
                  class="px-1.5 py-0.5 text-[9px] font-display font-semibold rounded"
                  :class="`bg-${role.color}-100 text-${role.color}-600`"
                >{{ perm }}</span>
              </div>
            </div>
          </label>
        </div>
      </div>

      <!-- Securite -->
      <div class="bg-white rounded-2xl border border-gray-100 p-6 animate-fade-up delay-200">
        <h3 class="text-base font-display font-bold text-gray-800 mb-5 flex items-center gap-2">
          <svg class="w-5 h-5 text-anatt-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Securite du compte
        </h3>
        <div class="space-y-4">
          <div>
            <label class="block text-xs font-display font-medium text-gray-600 mb-1.5">Mot de passe initial <span class="text-red-500">*</span></label>
            <div class="relative">
              <input v-model="form.password" type="password" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-body pr-24 focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500 transition-all">
              <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-display hover:bg-gray-200 transition-colors" @click="generatePassword">
                Generer
              </button>
            </div>
            <div class="flex items-center gap-4 mt-2">
              <div class="flex items-center gap-1">
                <div v-for="i in 4" :key="i" class="w-8 h-1 rounded" :class="i <= passwordStrength ? strengthColor : 'bg-gray-200'" />
              </div>
              <span v-if="passwordStrength > 0" class="text-[11px] font-display font-medium" :class="passwordStrength >= 3 ? 'text-green-600' : passwordStrength >= 2 ? 'text-yellow-600' : 'text-red-600'">{{ strengthLabel }}</span>
            </div>
          </div>
          <label class="flex items-center gap-3">
            <input v-model="form.forceChange" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <span class="text-sm text-gray-700 font-body">Forcer le changement de mot de passe a la premiere connexion</span>
          </label>
          <label class="flex items-center gap-3">
            <input v-model="form.sendEmail" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <span class="text-sm text-gray-700 font-body">Envoyer les identifiants par email</span>
          </label>
          <label class="flex items-center gap-3">
            <input v-model="form.activateNow" type="checkbox" class="w-4 h-4 text-anatt-500 rounded border-gray-300">
            <span class="text-sm text-gray-700 font-body">Activer le compte immediatement</span>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-between">
        <AppButton variant="outline" @click="router.push('/admin/utilisateurs')">Annuler</AppButton>
        <div class="flex gap-3">
          <AppButton variant="outline">Enregistrer en brouillon</AppButton>
          <AppButton :loading="loading" @click="handleSubmit">Creer l'utilisateur</AppButton>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
