<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- édition directe de l'objet formulaire réactif fourni par la vue */
import { computed, ref, watch } from 'vue'
import AppButton from '@/components/shared/AppButton.vue'
import { categorieDeduite, operateurPour, telephoneValide } from '@/utils/benin'
import { CATEGORIES, CATEGORIE_LABELS } from '@/types'
import type { AgentFormData } from '@/types'

const props = defineProps<{
  form: AgentFormData
  mode: 'create' | 'edit'
  submitting?: boolean
  serverErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{ submit: []; cancel: [] }>()


const categorieSuggeree = computed(() =>
  categorieDeduite(props.form.indice ?? null, props.form.salaire_brut ?? null),
)

// Renseigne la catégorie déduite tant que l'utilisateur ne l'a pas fixée.
const categorieTouchee = ref(false)
watch(categorieSuggeree, (sugg) => {
  if (sugg && !categorieTouchee.value) props.form.categorie = sugg
})

const operateurDeduit = computed(() => operateurPour(props.form.telephone))
const telOk = computed(() => telephoneValide(props.form.telephone))
const operateurIncoherent = computed(() =>
  telOk.value && operateurDeduit.value !== null && operateurDeduit.value !== props.form.operateur,
)

function appliquerOperateurDeduit() {
  if (operateurDeduit.value) props.form.operateur = operateurDeduit.value
}

function err(champ: string): string | null {
  return props.serverErrors?.[champ]?.[0] ?? null
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="emit('submit')">
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Nom</label>
        <input v-model="form.nom" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
        <p v-if="err('nom')" class="text-[11px] text-red-500 mt-1">{{ err('nom') }}</p>
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Prénoms</label>
        <input v-model="form.prenoms" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
        <p v-if="err('prenoms')" class="text-[11px] text-red-500 mt-1">{{ err('prenoms') }}</p>
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Matricule</label>
        <input v-model="form.matricule" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
        <p v-if="err('matricule')" class="text-[11px] text-red-500 mt-1">{{ err('matricule') }}</p>
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">IFU</label>
        <input v-model="form.ifu" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Grade</label>
        <input v-model="form.grade" type="text" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
      </div>
      <div></div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Indice</label>
        <input v-model.number="form.indice" type="number" min="0" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Salaire brut (FCFA)</label>
        <input v-model.number="form.salaire_brut" type="number" min="0" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body focus:outline-none focus:border-anatt-500">
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">
          Catégorie
          <span v-if="categorieSuggeree" class="text-anatt-500">(déduite : {{ CATEGORIE_LABELS[categorieSuggeree] }})</span>
        </label>
        <select v-model="form.categorie" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500" @change="categorieTouchee = true"
>
          <option :value="undefined" disabled>Choisir…</option>
          <option v-for="c in CATEGORIES" :key="c" :value="c">{{ CATEGORIE_LABELS[c] }}</option>
        </select>
        <p v-if="err('indice') || err('categorie')" class="text-[11px] text-red-500 mt-1">{{ err('indice') || err('categorie') }}</p>
      </div>
      <div></div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Téléphone (10 chiffres, 01…)</label>
        <input
          v-model="form.telephone"
          type="text"
          maxlength="10"
          class="w-full px-4 py-2.5 rounded-xl border text-sm font-body focus:outline-none focus:border-anatt-500"
          :class="form.telephone && !telOk ? 'border-red-400' : 'border-gray-200'"
        >
        <p v-if="form.telephone && !telOk" class="text-[11px] text-red-500 mt-1">Format invalide : 10 chiffres débutant par 01.</p>
        <p v-else-if="err('telephone')" class="text-[11px] text-red-500 mt-1">{{ err('telephone') }}</p>
      </div>
      <div>
        <label class="text-[11px] text-gray-500 font-body block mb-1">Opérateur</label>
        <select v-model="form.operateur" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
          <option value="MTN">MTN</option>
          <option value="MOOV">MOOV</option>
        </select>
        <p v-if="operateurIncoherent" class="text-[11px] text-gold-600 mt-1">
          Le numéro correspond à {{ operateurDeduit }}.
          <button type="button" class="underline font-semibold" @click="appliquerOperateurDeduit">Appliquer {{ operateurDeduit }}</button>
        </p>
      </div>
    </div>

    <label v-if="mode === 'edit'" class="flex items-center gap-2 text-sm font-body text-gray-700">
      <input v-model="form.actif" type="checkbox" class="rounded text-anatt-500 focus:ring-anatt-500">
      Agent actif
    </label>

    <div class="flex justify-end gap-3 pt-2">
      <AppButton type="button" variant="ghost" @click="emit('cancel')">Annuler</AppButton>
      <AppButton type="submit" :loading="submitting" :disabled="operateurIncoherent || (!!form.telephone && !telOk)">
        {{ mode === 'create' ? "Créer l'agent" : 'Enregistrer' }}
      </AppButton>
    </div>
  </form>
</template>
