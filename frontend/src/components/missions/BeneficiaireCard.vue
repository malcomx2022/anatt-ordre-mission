<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- la carte édite l'objet bénéficiaire réactif fourni par le store */
import { ref, computed, watch } from 'vue'
import { agentsService } from '@/services/agents'
import { typesPourZone } from '@/types'
import type { Agent, BeneficiaireForm, Categorie, TypePriseEnCharge, ZoneCode } from '@/types'
import type { PerDiem } from '@/stores/ordres-mission'

const props = defineProps<{
  beneficiaire: BeneficiaireForm
  index: number
  zone: ZoneCode | ''
  nbJoursMission: number
  perDiem: PerDiem
  exonere: boolean
  doublon: boolean
}>()

defineEmits<{ remove: [] }>()

const CATEGORIES: Categorie[] = ['ORDRE_1', 'ORDRE_2', 'ORDRE_3']
const TYPE_LABELS: Record<TypePriseEnCharge, string> = {
  UN_REPAS: 'Un repas',
  DEUX_REPAS: 'Deux repas',
  JOURNEE_COMPLETE: 'Journée complète',
  LOGE_NOURRI: 'Logé + nourri',
  LOGE_SEUL: 'Logé seul',
  NOURRI_SEUL: 'Nourri seul',
}

const typesDisponibles = computed(() => typesPourZone(props.zone))

// ── Autocomplétion agent ──────────────────────────────────────────────
const recherche = ref('')
const suggestions = ref<Agent[]>([])
const ouvert = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

watch(recherche, (q) => {
  if (timer) clearTimeout(timer)
  if (!q || q.trim().length < 1) { suggestions.value = []; return }
  timer = setTimeout(async () => {
    try {
      suggestions.value = await agentsService.search(q.trim())
      ouvert.value = true
    } catch {
      suggestions.value = []
    }
  }, 250)
})

function choisirAgent(agent: Agent) {
  props.beneficiaire.agent_id = agent.id
  props.beneficiaire.agent = agent
  // Catégorie reprise de SEMO/référentiel par défaut, modifiable ensuite.
  if (!props.beneficiaire.categorie) props.beneficiaire.categorie = agent.categorie
  recherche.value = ''
  suggestions.value = []
  ouvert.value = false
}

function retirerAgent() {
  props.beneficiaire.agent_id = null
  props.beneficiaire.agent = null
}

const fmt = (v: number) => v.toLocaleString('fr-FR')
</script>

<template>
  <div
    class="border rounded-xl p-5 transition-colors"
    :class="doublon ? 'border-red-300 bg-red-50/40' : 'border-gray-200'"
  >
    <!-- En-tête : index, rôle, suppression -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-anatt-100 text-anatt-600 flex items-center justify-center text-xs font-display font-bold">
          {{ index + 1 }}
        </div>
        <select
          v-model="beneficiaire.role"
          class="px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-display font-semibold focus:outline-none focus:border-anatt-500"
        >
          <option value="PARTICIPANT">Participant</option>
          <option value="CAV">CAV (conducteur)</option>
        </select>
      </div>
      <button
        type="button"
        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors"
        @click="$emit('remove')"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <!-- Agent (autocomplétion) -->
      <div class="relative">
        <label class="text-[11px] text-gray-500 font-body block mb-1">Bénéficiaire (agent)</label>
        <div v-if="beneficiaire.agent" class="flex items-center justify-between px-3 py-2 rounded-lg border border-anatt-200 bg-anatt-50">
          <span class="text-sm font-body text-gray-800">
            {{ beneficiaire.agent.nom_complet || (beneficiaire.agent.nom + ' ' + beneficiaire.agent.prenoms) }}
            <span class="text-[11px] text-gray-400">· {{ beneficiaire.agent.matricule }}</span>
          </span>
          <button type="button" class="text-gray-400 hover:text-red-500" @click="retirerAgent">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
        <template v-else>
          <input
            v-model="recherche"
            type="text"
            placeholder="Nom, matricule ou IFU…"
            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500"
            @focus="ouvert = true"
          >
          <ul
            v-if="ouvert && suggestions.length"
            class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-auto"
          >
            <li
              v-for="a in suggestions"
              :key="a.id"
              class="px-3 py-2 text-sm font-body hover:bg-anatt-50 cursor-pointer flex justify-between"
              @click="choisirAgent(a)"
            >
              <span>{{ a.nom }} {{ a.prenoms }}</span>
              <span class="text-[11px] text-gray-400">{{ a.matricule }} · {{ a.categorie }}</span>
            </li>
          </ul>
        </template>
      </div>

      <!-- Référence SEMO -->
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Référence SEMO</label>
        <input
          v-model="beneficiaire.reference_semo"
          type="text"
          placeholder="ex. 0541405636"
          class="w-full px-3 py-2 rounded-lg border text-sm font-body focus:outline-none focus:border-anatt-500"
          :class="doublon ? 'border-red-400' : 'border-gray-200'"
        >
        <p v-if="doublon" class="text-[11px] text-red-500 mt-1">Référence en doublon dans cette mission.</p>
      </div>

      <!-- Catégorie (reprise SEMO, modifiable) -->
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Catégorie <span class="text-gray-300">(reprise SEMO)</span></label>
        <select
          v-model="beneficiaire.categorie"
          class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500"
        >
          <option :value="undefined" disabled>Choisir…</option>
          <option v-for="c in CATEGORIES" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>

      <!-- Type de prise en charge (dépend de la zone) -->
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Type de prise en charge</label>
        <select
          v-model="beneficiaire.type_prise_en_charge"
          class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500"
        >
          <option value="" disabled>Choisir…</option>
          <option v-for="t in typesDisponibles" :key="t" :value="t">{{ TYPE_LABELS[t] }}</option>
        </select>
      </div>

      <!-- Nombre de jours -->
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Nombre de jours</label>
        <input
          v-model.number="beneficiaire.nb_jours"
          type="number"
          min="1"
          :placeholder="`Mission : ${nbJoursMission}`"
          class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500"
        >
      </div>

      <!-- Conducteur à bord (mention logistique, participants) -->
      <div v-if="beneficiaire.role === 'PARTICIPANT'">
        <label class="text-[11px] text-gray-500 font-body block mb-1">Conducteur à bord <span class="text-gray-300">(mention)</span></label>
        <input
          v-model="beneficiaire.conducteur_a_bord"
          type="text"
          placeholder="Nom du conducteur"
          class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500"
        >
      </div>
    </div>

    <!-- Téléphone / opérateur (auto depuis l'agent) + per diem temps réel -->
    <div class="mt-4 pt-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-3">
      <div v-if="beneficiaire.agent" class="text-[11px] text-gray-500 font-body">
        {{ beneficiaire.agent.telephone }}
        <span class="px-2 py-0.5 ml-1 rounded-full font-bold" :class="beneficiaire.agent.operateur === 'MTN' ? 'bg-gold-50 text-gold-600' : 'bg-blue-50 text-blue-600'">
          {{ beneficiaire.agent.operateur }}
        </span>
      </div>
      <div class="text-right ml-auto">
        <template v-if="exonere">
          <span class="text-xs font-display font-bold text-gray-400">Exonéré (prise en charge tiers)</span>
        </template>
        <template v-else-if="perDiem.taux === null">
          <span class="text-xs text-red-500 font-body">Barème introuvable pour ce choix</span>
        </template>
        <template v-else>
          <p class="text-sm font-display font-bold text-anatt-700">
            Per diem : {{ fmt(perDiem.total) }} FCFA
            <span class="text-[11px] text-gray-400 font-body">({{ perDiem.nb_jours }} j × {{ fmt(perDiem.taux) }})</span>
          </p>
          <p class="text-[11px] text-gray-500 font-body">
            Avance {{ fmt(perDiem.avance) }} · Reliquat {{ fmt(perDiem.reliquat) }}
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
