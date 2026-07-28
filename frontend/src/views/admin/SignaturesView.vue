<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import { signaturesService } from '@/services/etats'
import { api } from '@/services/http'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import type { PosteSignataire, SignatairesParametres } from '@/types'

const toast = useToast()

const signataires = ref<SignatairesParametres | null>(null)
const loading = ref(true)
const enregistrement = ref(false)

/** Identités saisies, indexées par poste. */
const identites = ref<Record<PosteSignataire, { nom: string; prenoms: string; fonction: string }>>({
  controleur: { nom: '', prenoms: '', fonction: '' },
  daf: { nom: '', prenoms: '', fonction: '' },
  dg: { nom: '', prenoms: '', fonction: '' },
})

const postes: { cle: PosteSignataire; titre: string; etape: string }[] = [
  { cle: 'controleur', titre: 'Contrôleur', etape: 'Étape 1 — contrôle de conformité' },
  { cle: 'daf', titre: "Directeur de l'Administration et des Finances", etape: 'Étape 2 — visa « Vu »' },
  { cle: 'dg', titre: 'Directeur Général', etape: 'Étape 3 — validation finale' },
]

async function charger() {
  loading.value = true
  try {
    const [sigs, params] = await Promise.all([
      signaturesService.list(),
      api.get('/api/admin/settings').then((r) => r.data?.data ?? {}),
    ])

    signataires.value = sigs

    for (const { cle } of postes) {
      identites.value[cle] = {
        nom: params[`${cle}Nom`] ?? sigs[cle]?.nom ?? '',
        prenoms: params[`${cle}Prenoms`] ?? sigs[cle]?.prenoms ?? '',
        fonction: params[`${cle}Fonction`] ?? '',
      }
    }
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(charger)

/** Les identités vivent dans les paramètres généraux ; l'image a sa propre route. */
async function enregistrerIdentites() {
  enregistrement.value = true
  try {
    const charge: Record<string, string> = {}
    for (const { cle } of postes) {
      charge[`${cle}Nom`] = identites.value[cle].nom.trim()
      charge[`${cle}Prenoms`] = identites.value[cle].prenoms.trim()
      charge[`${cle}Fonction`] = identites.value[cle].fonction.trim()
    }

    await api.put('/api/admin/settings', charge)
    toast.success('Identités des signataires enregistrées.')
    await charger()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    enregistrement.value = false
  }
}

async function televerser(poste: PosteSignataire, evenement: Event) {
  const input = evenement.target as HTMLInputElement
  const fichier = input.files?.[0]
  if (!fichier) return

  try {
    signataires.value = await signaturesService.televerser(poste, fichier)
    toast.success('Signature enregistrée.')
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    // Permet de re-sélectionner le même fichier après une erreur.
    input.value = ''
  }
}

async function supprimer(poste: PosteSignataire) {
  try {
    signataires.value = await signaturesService.supprimer(poste)
    toast.success('Signature supprimée.')
  } catch (e) {
    toast.error(getErrorMessage(e))
  }
}
</script>

<template>
  <AppLayout
    title="Signatures électroniques"
    subtitle="Signataires du circuit de validation des états de paiement"
  >
    <template #header-actions>
      <router-link
        to="/admin/parametres"
        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
      >
        ← Paramètres
      </router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <div v-else class="space-y-6">
      <AppAlert type="info">
        Ces identités et signatures sont celles des <strong>titulaires en poste</strong>.
        Elles sont imprimées sur l’état de paiement, indépendamment du compte qui
        effectue la validation — lequel reste enregistré au journal d’audit.
        L’image n’est jamais accessible par une adresse publique : elle est intégrée
        au PDF au moment de sa production.
      </AppAlert>

      <AppCard v-for="poste in postes" :key="poste.cle">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="font-display font-bold text-gray-800">{{ poste.titre }}</h3>
            <p class="text-xs text-gray-500 font-body">{{ poste.etape }}</p>
          </div>
          <span
            class="px-3 py-1 rounded-full text-[11px] font-bold"
            :class="signataires?.[poste.cle]?.signature_enregistree
              ? 'bg-anatt-50 text-anatt-600'
              : 'bg-gold-50 text-gold-600'"
          >
            {{ signataires?.[poste.cle]?.signature_enregistree ? 'Signature enregistrée' : 'Aucune signature' }}
          </span>
        </div>

        <div class="mt-5 grid sm:grid-cols-3 gap-4">
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Prénoms</label>
            <input
              v-model="identites[poste.cle].prenoms"
              type="text"
              placeholder="Euloge D."
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body
                     focus:outline-none focus:border-anatt-500"
            />
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">Nom</label>
            <input
              v-model="identites[poste.cle].nom"
              type="text"
              placeholder="ATOHOUN"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body
                     focus:outline-none focus:border-anatt-500"
            />
          </div>
          <div>
            <label class="text-[11px] text-gray-500 font-body block mb-1">
              Fonction imprimée
            </label>
            <input
              v-model="identites[poste.cle].fonction"
              type="text"
              :placeholder="poste.titre"
              class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-body
                     focus:outline-none focus:border-anatt-500"
            />
          </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
          <label
            class="px-5 py-2.5 rounded-xl border-2 border-anatt-500 text-anatt-600 font-display
                   font-semibold text-sm hover:bg-anatt-500 hover:text-white transition-all
                   duration-200 cursor-pointer"
          >
            {{ signataires?.[poste.cle]?.signature_enregistree ? 'Remplacer la signature' : 'Téléverser la signature' }}
            <input
              type="file"
              accept="image/png,image/jpeg"
              class="hidden"
              @change="televerser(poste.cle, $event)"
            />
          </label>
          <AppButton
            v-if="signataires?.[poste.cle]?.signature_enregistree"
            variant="ghost"
            @click="supprimer(poste.cle)"
          >
            Supprimer
          </AppButton>
          <span class="text-xs text-gray-400 font-body">PNG ou JPEG, 500 Ko maximum</span>
        </div>
      </AppCard>

      <div class="flex justify-end">
        <AppButton :loading="enregistrement" @click="enregistrerIdentites">
          Enregistrer les identités
        </AppButton>
      </div>
    </div>
  </AppLayout>
</template>
