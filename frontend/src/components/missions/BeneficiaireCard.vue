<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- la carte édite l'objet bénéficiaire réactif fourni par le store */
import { ref, computed, watch } from 'vue'
import { agentsService } from '@/services/agents'
import AppCombobox from '@/components/shared/AppCombobox.vue'
import type { ComboOption } from '@/components/shared/combobox'
import { CATEGORIES, CATEGORIE_LABELS, categorieLabel, typesPourZone } from '@/types'
import type { Agent, BeneficiaireForm, TypePriseEnCharge, ZoneCode } from '@/types'
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

const TYPE_LABELS: Record<TypePriseEnCharge, string> = {
  UN_REPAS: 'Un repas',
  DEUX_REPAS: 'Deux repas',
  JOURNEE_COMPLETE: 'Journée complète',
  LOGE_NOURRI: 'Logé + nourri',
  LOGE_SEUL: 'Logé seul',
  NOURRI_SEUL: 'Nourri seul',
}

const typesDisponibles = computed(() => typesPourZone(props.zone))

// ── Répartition détaillée par type (état ANaTT) ───────────────────────
// Ex. 1 jour « un repas » + 6 jours « journée complète ». Si activée, elle
// remplace le couple (type unique × nb_jours).
const detaille = ref((props.beneficiaire.lignes?.length ?? 0) > 0)

function joursDe(type: TypePriseEnCharge): number {
  return props.beneficiaire.lignes?.find((l) => l.type === type)?.nb_jours ?? 0
}

function setJours(type: TypePriseEnCharge, valeur: number | string): void {
  const n = Math.max(0, Number(valeur) || 0)
  const arr = [...(props.beneficiaire.lignes ?? [])].filter((l) => l.type !== type)
  if (n > 0) arr.push({ type, nb_jours: n })
  props.beneficiaire.lignes = arr
}

watch(detaille, (actif) => {
  if (!actif) props.beneficiaire.lignes = []
})

// Bascule automatiquement en mode simple si la zone n'a qu'un seul type.
watch(() => props.zone, () => {
  props.beneficiaire.lignes = []
  detaille.value = false
})

// ── Autocomplétion agent (AppCombobox, mode distant) ──────────────────
// La recherche renvoie des ComboOption ; on conserve l'agent complet dans une
// map pour le réinjecter dans le formulaire au moment du choix.
const agentsTrouves = new Map<number, Agent>()

async function chercherAgents(q: string): Promise<ComboOption[]> {
  const agents = await agentsService.search(q)
  agentsTrouves.clear()
  return agents.map((a) => {
    agentsTrouves.set(a.id, a)
    return { value: a.id, label: `${a.nom} ${a.prenoms}`, hint: `${a.matricule} · ${categorieLabel(a.categorie)}` }
  })
}

function surChoixAgent(option: ComboOption | null): void {
  if (!option) { retirerAgent(); return }
  const agent = agentsTrouves.get(Number(option.value))
  if (agent) choisirAgent(agent)
}

function choisirAgent(agent: Agent) {
  props.beneficiaire.agent_id = agent.id
  props.beneficiaire.agent = agent
  // Catégorie reprise de SEMO/référentiel par défaut, modifiable ensuite.
  if (!props.beneficiaire.categorie) props.beneficiaire.categorie = agent.categorie
}

function retirerAgent() {
  props.beneficiaire.agent_id = null
  props.beneficiaire.agent = null
}

const labelAgent = computed(() => {
  const a = props.beneficiaire.agent
  return a ? (a.nom_complet || `${a.nom} ${a.prenoms}`) : ''
})

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
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Bénéficiaire (agent)</label>
        <AppCombobox
          :model-value="beneficiaire.agent_id"
          :fetcher="chercherAgents"
          :selected-label="labelAgent"
          placeholder="Nom, matricule ou IFU…"
          @change="surChoixAgent"
        />
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
          <option v-for="c in CATEGORIES" :key="c" :value="c">{{ CATEGORIE_LABELS[c] }}</option>
        </select>
      </div>

      <!-- Type de prise en charge (mode simple : type unique × nb jours) -->
      <template v-if="!detaille">
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
      </template>

      <!-- Montant transport (colonne TRANSPORT de l'état) -->
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Transport (FCFA) <span class="text-gray-300">(optionnel)</span></label>
        <input
          v-model.number="beneficiaire.montant_transport"
          type="number"
          min="0"
          step="500"
          placeholder="0"
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

    <!-- Répartition détaillée par type (état ANaTT) -->
    <div v-if="typesDisponibles.length > 1" class="mt-4">
      <label class="inline-flex items-center gap-2 text-[12px] font-body text-gray-600 cursor-pointer">
        <input v-model="detaille" type="checkbox" class="rounded border-gray-300 text-anatt-500 focus:ring-anatt-500">
        Répartition détaillée par type de repas (jours par type)
      </label>

      <div v-if="detaille" class="mt-3 grid gap-2" :style="{ gridTemplateColumns: `repeat(${Math.min(typesDisponibles.length, 4)}, minmax(0, 1fr))` }">
        <div v-for="t in typesDisponibles" :key="t">
          <label class="text-[11px] text-gray-500 font-body block mb-1">{{ TYPE_LABELS[t] }}</label>
          <input
            :value="joursDe(t)"
            type="number"
            min="0"
            placeholder="0 j"
            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500"
            @input="setJours(t, ($event.target as HTMLInputElement).value)"
          >
        </div>
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
        <template v-else-if="!perDiem.ok">
          <span class="text-xs text-red-500 font-body">Barème introuvable pour ce choix</span>
        </template>
        <template v-else>
          <p class="text-sm font-display font-bold text-anatt-700">
            Total : {{ fmt(perDiem.total) }} FCFA
            <span v-if="perDiem.taux !== null" class="text-[11px] text-gray-400 font-body">({{ perDiem.nb_jours }} j × {{ fmt(perDiem.taux) }})</span>
            <span v-else class="text-[11px] text-gray-400 font-body">({{ perDiem.nb_jours }} j répartis)</span>
          </p>
          <p class="text-[11px] text-gray-500 font-body">
            Per diem {{ fmt(perDiem.per_diem) }}<span v-if="perDiem.transport > 0"> · Transport {{ fmt(perDiem.transport) }}</span> · Avance {{ fmt(perDiem.avance) }} · Reliquat {{ fmt(perDiem.reliquat) }}
          </p>
        </template>
      </div>
    </div>
  </div>
</template>
