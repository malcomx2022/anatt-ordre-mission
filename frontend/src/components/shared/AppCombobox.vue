<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue'
import type { ComboOption } from './combobox'

/**
 * Sélecteur RECHERCHABLE, conçu pour les listes qui grandissent (missions,
 * agents…) là où un <select> natif devient inutilisable.
 *
 * Deux modes :
 *  - LOCAL  : on fournit `options`, le filtrage se fait côté client.
 *  - DISTANT: on fournit `fetcher(q)`, appelé (débouncé) à chaque frappe.
 *
 * Pour les énumérations courtes et fixes (statut, zone, opérateur…), garder un
 * <select> natif : ce composant vise uniquement les listes volumineuses.
 */
const props = withDefaults(defineProps<{
  modelValue: string | number | null
  options?: ComboOption[]
  fetcher?: (q: string) => Promise<ComboOption[]>
  placeholder?: string
  /** Libellé de la valeur déjà sélectionnée (utile en mode distant au montage). */
  selectedLabel?: string
  minChars?: number
  debounce?: number
  disabled?: boolean
  clearable?: boolean
  noResultsText?: string
}>(), {
  options: undefined,
  fetcher: undefined,
  placeholder: 'Rechercher…',
  selectedLabel: '',
  minChars: 1,
  debounce: 250,
  disabled: false,
  clearable: true,
  noResultsText: 'Aucun résultat',
})

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | number | null): void
  (e: 'change', option: ComboOption | null): void
}>()

const distant = computed(() => typeof props.fetcher === 'function')

const racine = ref<HTMLElement | null>(null)
const champ = ref<HTMLInputElement | null>(null)
const requete = ref('')
const ouvert = ref(false)
const chargement = ref(false)
const surligne = ref(-1)
const resultats = ref<ComboOption[]>([])
/** Dernière option choisie (source du libellé affiché, surtout en mode distant). */
const choisie = ref<ComboOption | null>(null)

const libelleSelection = computed(
  () => choisie.value?.label
    ?? props.options?.find((o) => o.value === props.modelValue)?.label
    ?? (props.modelValue != null ? props.selectedLabel : ''),
)
const aUneValeur = computed(() => props.modelValue !== null && props.modelValue !== undefined && props.modelValue !== '')

let minuteur: ReturnType<typeof setTimeout> | null = null

watch(requete, (q) => {
  if (!ouvert.value) return
  if (minuteur) clearTimeout(minuteur)

  if (distant.value) {
    if (q.trim().length < props.minChars) { resultats.value = []; return }
    chargement.value = true
    minuteur = setTimeout(async () => {
      try {
        resultats.value = await props.fetcher!(q.trim())
      } catch {
        resultats.value = []
      } finally {
        chargement.value = false
        surligne.value = resultats.value.length ? 0 : -1
      }
    }, props.debounce)
  } else {
    const terme = q.trim().toLowerCase()
    resultats.value = !terme
      ? (props.options ?? [])
      : (props.options ?? []).filter((o) => o.label.toLowerCase().includes(terme)
        || (o.hint?.toLowerCase().includes(terme) ?? false))
    surligne.value = resultats.value.length ? 0 : -1
  }
})

async function ouvrir() {
  if (props.disabled) return
  ouvert.value = true
  requete.value = ''
  // Mode local : afficher toutes les options d'emblée.
  if (!distant.value) {
    resultats.value = props.options ?? []
    surligne.value = resultats.value.length ? 0 : -1
  } else {
    resultats.value = []
  }
  await nextTick()
  champ.value?.focus()
}

function fermer() {
  ouvert.value = false
  requete.value = ''
  resultats.value = []
  surligne.value = -1
}

function choisir(option: ComboOption) {
  choisie.value = option
  emit('update:modelValue', option.value)
  emit('change', option)
  fermer()
}

function effacer() {
  choisie.value = null
  emit('update:modelValue', null)
  emit('change', null)
  fermer()
}

function surClavier(e: KeyboardEvent) {
  if (!ouvert.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    surligne.value = Math.min(surligne.value + 1, resultats.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    surligne.value = Math.max(surligne.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const opt = resultats.value[surligne.value]
    if (opt) choisir(opt)
  } else if (e.key === 'Escape') {
    e.preventDefault()
    fermer()
  }
}

function surClicExterieur(e: MouseEvent) {
  if (racine.value && !racine.value.contains(e.target as Node)) fermer()
}

watch(ouvert, (o) => {
  if (o) document.addEventListener('mousedown', surClicExterieur)
  else document.removeEventListener('mousedown', surClicExterieur)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', surClicExterieur)
  if (minuteur) clearTimeout(minuteur)
})
</script>

<template>
  <div ref="racine" class="relative">
    <!-- Valeur sélectionnée (chip) -->
    <div
      v-if="aUneValeur && !ouvert"
      class="flex items-center justify-between px-3 py-2.5 rounded-xl border border-anatt-200 bg-anatt-50"
      :class="disabled ? 'opacity-60' : 'cursor-pointer'"
      role="button"
      tabindex="0"
      @click="ouvrir"
      @keydown.enter.prevent="ouvrir"
    >
      <span class="text-sm font-body text-gray-800 truncate">{{ libelleSelection || '—' }}</span>
      <button
        v-if="clearable && !disabled"
        type="button"
        class="ml-2 text-gray-400 hover:text-red-500 shrink-0"
        aria-label="Effacer la sélection"
        @click.stop="effacer"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
      </button>
    </div>

    <!-- Bouton d'ouverture quand rien n'est sélectionné et fermé -->
    <button
      v-else-if="!ouvert"
      type="button"
      :disabled="disabled"
      class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body text-gray-400 hover:border-anatt-500 disabled:opacity-60 transition-colors"
      @click="ouvrir"
    >
      <span class="truncate">{{ placeholder }}</span>
      <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
    </button>

    <!-- Champ de recherche + liste -->
    <div v-else>
      <input
        ref="champ"
        v-model="requete"
        type="text"
        role="combobox"
        aria-expanded="true"
        aria-autocomplete="list"
        :placeholder="placeholder"
        class="w-full px-3 py-2.5 rounded-xl border border-anatt-500 bg-white text-sm font-body focus:outline-none focus:ring-2 focus:ring-anatt-500/30"
        @keydown="surClavier"
      >
      <ul
        class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-auto"
        role="listbox"
      >
        <li v-if="chargement" class="px-3 py-2 text-sm font-body text-gray-400">Recherche…</li>
        <li
          v-else-if="!resultats.length"
          class="px-3 py-2 text-sm font-body text-gray-400"
        >{{ distant && requete.trim().length < minChars ? `Saisissez au moins ${minChars} caractère(s)…` : noResultsText }}</li>
        <li
          v-for="(o, i) in resultats"
          v-else
          :key="o.value"
          role="option"
          :aria-selected="i === surligne"
          class="px-3 py-2 text-sm font-body cursor-pointer flex items-center justify-between gap-2"
          :class="i === surligne ? 'bg-anatt-50 text-anatt-700' : 'hover:bg-gray-50'"
          @mouseenter="surligne = i"
          @click="choisir(o)"
        >
          <span class="truncate">{{ o.label }}</span>
          <span v-if="o.hint" class="text-[11px] text-gray-400 shrink-0">{{ o.hint }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>
