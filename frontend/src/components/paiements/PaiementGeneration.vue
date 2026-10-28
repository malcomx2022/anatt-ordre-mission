<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppAlert from '@/components/shared/AppAlert.vue'
import AppCombobox from '@/components/shared/AppCombobox.vue'
import OperateurBadge from '@/components/paiements/OperateurBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { missionsService } from '@/services/missions'
import { paiementsService, type GenerationAsync, type StatutJobLot } from '@/services/paiements'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { usePartAvance } from '@/composables/usePartAvance'
import type { LotPaiement, LotPreview, Operateur, TrancheVersement } from '@/types'

const props = defineProps<{ operateurInitial?: Operateur }>()
const toast = useToast()

const missionId = ref<number | null>(null)
const tranche = ref<TrancheVersement>('AVANCE')
const operateur = ref<Operateur>(props.operateurInitial ?? 'MTN')

const apercu = ref<LotPreview | null>(null)
const lot = ref<LotPaiement | null>(null)
const chargement = ref(false)
const action = ref(false)

// Part d'avance de la mission choisie (celle de ses OM, renvoyée par l'aperçu) ;
// avant tout choix, ou pour une mission exonérée, le réglage courant (réactif).
const { partAvance } = usePartAvance()
const avancePct = computed(() => apercu.value?.part_avance ?? partAvance.value)
const reliquatPct = computed(() => 100 - avancePct.value)

const fmt = (v: number) => v.toLocaleString('fr-FR')
const extension = computed(() => (operateur.value === 'MTN' ? '.txt' : '.xlsx'))
const confirme = computed(() => lot.value?.statut === 'CONFIRME')
// Dernier lot confirmé ET rien de nouveau à payer : la sélection est soldée.
const solde = computed(() => confirme.value && !apercu.value?.nb_lignes)
// Dernier lot confirmé mais des versements devenus payables depuis : le serveur
// les met dans un lot complémentaire (séquence suivante), sans réécrire le lot confirmé.
const complementaire = computed(() => confirme.value && !solde.value)
// Opérateur désactivé dans Paramètres › Paiements : aperçu consultable, génération refusée.
const operateurFerme = computed(() => apercu.value?.operateur_actif === false)
const peutGenerer = computed(
  () => !!apercu.value && apercu.value.nb_lignes > 0 && !suivi.value && !operateurFerme.value,
)

// Sélecteur de mission : recherche serveur débouncée (plus de chargement de
// TOUS les ordres pour dériver la liste — la liste grandit sans limite).
const chercherMissions = (q: string) => missionsService.search(q)

async function rafraichir() {
  if (!missionId.value) { apercu.value = null; lot.value = null; return }
  chargement.value = true
  try {
    const sel = { mission_id: missionId.value, tranche: tranche.value, operateur: operateur.value }
    apercu.value = await paiementsService.previsualiser(sel)
    const { items } = await paiementsService.lots(sel)
    lot.value = items[0] ?? null
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    chargement.value = false
  }
}

watch([missionId, tranche, operateur], rafraichir)

// ── Génération asynchrone des gros lots (> seuil serveur) ──
// L'API répond 202 + tracking_id : on sonde GET /paiements/lots/job/{id} jusqu'à
// TERMINE (puis téléchargement du lot) ou ERREUR. Le sondage est arrêté au
// démontage de l'écran.
const INTERVALLE_SONDAGE_MS = 2000
/** Tolérance tant que le worker n'a pas pris la tâche (cache encore vide). */
const MAX_SONDAGES_INTROUVABLE = 30

interface SuiviGeneration {
  trackingId: string
  nbLignes: number
  statut: StatutJobLot['statut']
  nomParDefaut: string
  libelle: string
}

const suivi = ref<SuiviGeneration | null>(null)
let minuteur: ReturnType<typeof setInterval> | null = null
let sondageEnVol = false
let sondagesIntrouvables = 0

const libelleSuivi = computed(() => {
  switch (suivi.value?.statut) {
    case 'EN_COURS': return 'Génération du fichier en cours…'
    default: return 'Génération en file d’attente…'
  }
})

function arreterSuivi() {
  if (minuteur) {
    clearInterval(minuteur)
    minuteur = null
  }
}

function terminerSuivi() {
  arreterSuivi()
  suivi.value = null
}

function demarrerSuivi(reponse: GenerationAsync, nomParDefaut: string, libelle: string) {
  arreterSuivi()
  sondagesIntrouvables = 0
  suivi.value = { trackingId: reponse.tracking_id, nbLignes: reponse.nb_lignes, statut: 'INTROUVABLE', nomParDefaut, libelle }
  minuteur = setInterval(() => { void sonder() }, INTERVALLE_SONDAGE_MS)
}

async function sonder() {
  const courant = suivi.value
  if (!courant || sondageEnVol) return
  sondageEnVol = true
  try {
    const etat = await paiementsService.statutJob(courant.trackingId)
    // Écran démonté ou suivi remplacé pendant la requête : on ignore la réponse.
    if (suivi.value !== courant) return
    courant.statut = etat.statut

    if (etat.statut === 'TERMINE') {
      terminerSuivi()
      if (etat.lot_id) {
        await paiementsService.telechargerLot(etat.lot_id, etat.fichier ?? courant.nomParDefaut)
      }
      toast.success(`${courant.libelle} généré et téléchargé.`)
      await rafraichir()
    } else if (etat.statut === 'ERREUR') {
      terminerSuivi()
      toast.error(etat.message || 'La génération du fichier a échoué. Régénération possible.')
      await rafraichir()
    } else if (etat.statut === 'INTROUVABLE' && ++sondagesIntrouvables >= MAX_SONDAGES_INTROUVABLE) {
      terminerSuivi()
      toast.error('Génération introuvable : la file de traitement ne répond pas. Réessayez plus tard.')
      await rafraichir()
    }
  } catch (e) {
    terminerSuivi()
    toast.error(getErrorMessage(e))
  } finally {
    sondageEnVol = false
  }
}

onBeforeUnmount(terminerSuivi)

async function generer() {
  if (!missionId.value) return
  action.value = true
  const libelle = `Fichier ${operateur.value} ${extension.value}`
  const nomParDefaut = `${operateur.value}_${tranche.value}${extension.value}`
  try {
    const asynchrone = await paiementsService.generer({ mission_id: missionId.value, tranche: tranche.value, operateur: operateur.value })
    if (asynchrone) {
      // Gros lot : pas une erreur — la génération continue en arrière-plan.
      toast.show(asynchrone.message ?? `Gros lot (${asynchrone.nb_lignes} lignes) : génération lancée en arrière-plan.`, 'info')
      demarrerSuivi(asynchrone, nomParDefaut, libelle)
      return
    }
    toast.success(`${libelle} généré et téléchargé.`)
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
    // En file sync, l'échec d'un gros lot passe en ERREUR le lot GENERE de la même
    // sélection (failed()) : on recharge, sinon « Confirmer » resterait affiché.
    await rafraichir()
  } finally {
    action.value = false
  }
}

async function confirmer() {
  if (!lot.value) return
  action.value = true
  try {
    await paiementsService.confirmer(lot.value.id)
    toast.success('Paiement confirmé : versements soldés et horodatés.')
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function marquerErreur() {
  if (!lot.value) return
  action.value = true
  try {
    await paiementsService.marquerErreur(lot.value.id)
    toast.warning('Lot marqué en erreur. Régénération possible.')
    await rafraichir()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <!-- Sélection -->
    <AppCard>
      <h2 class="font-display font-bold text-gray-800 mb-4">Sélection du lot</h2>
      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Mission</label>
          <AppCombobox
            :model-value="missionId"
            :fetcher="chercherMissions"
            placeholder="Rechercher une mission (objet, destination)…"
            @change="(o) => (missionId = o ? Number(o.value) : null)"
          />
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Tranche</label>
          <select v-model="tranche" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
<option value="AVANCE">Avance ({{ avancePct }} %)</option>
             <option value="RELIQUAT">Reliquat ({{ reliquatPct }} %)</option>
          </select>
        </div>
        <div>
          <label class="text-[11px] text-gray-500 font-body block mb-1">Opérateur</label>
          <select v-model="operateur" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body focus:outline-none focus:border-anatt-500">
            <option value="MTN">MTN (.txt)</option>
            <option value="MOOV">Moov (.xlsx)</option>
          </select>
        </div>
      </div>
    </AppCard>

    <!-- Statut du lot existant -->
    <AppAlert v-if="operateurFerme" type="warning">
      Les paiements <strong>{{ operateur }}</strong> sont désactivés dans les Paramètres : aucun fichier ne peut être
      généré pour cet opérateur. Les lots déjà générés restent confirmables.
    </AppAlert>

    <AppAlert v-if="lot" :type="confirme ? 'success' : lot.statut === 'ERREUR' ? 'error' : 'info'">
      <div class="flex items-center gap-2">
        <span>Lot {{ operateur }} / {{ tranche }} :</span>
        <PaiementStatusBadge :statut="lot.statut" />
        <span v-if="lot.nom_fichier" class="text-xs">· {{ lot.nom_fichier }}</span>
        <span v-if="confirme && lot.confirme_le" class="text-xs">· confirmé le {{ new Date(lot.confirme_le).toLocaleString('fr-FR') }}</span>
      </div>
    </AppAlert>

    <!-- Suivi d'une génération asynchrone (gros lot) -->
    <AppAlert v-if="suivi" type="info">
      <div class="space-y-2" role="status" aria-live="polite">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
          </svg>
          <span class="font-display font-semibold">{{ libelleSuivi }}</span>
          <span class="text-xs">· {{ suivi.nbLignes }} ligne(s) · le téléchargement démarrera automatiquement</span>
        </div>
        <div class="h-1.5 w-full rounded-full bg-blue-100 overflow-hidden">
          <div class="h-full rounded-full bg-blue-500 transition-all duration-500"
               :class="suivi.statut === 'EN_COURS' ? 'w-2/3 animate-pulse' : 'w-1/4'"></div>
        </div>
      </div>
    </AppAlert>

    <!-- Aperçu des bénéficiaires -->
    <AppCard v-if="missionId">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-bold text-gray-800">Aperçu des bénéficiaires</h2>
        <OperateurBadge :operateur="operateur" />
      </div>

      <p v-if="chargement" class="text-center text-gray-400 font-body py-6">Chargement…</p>

      <template v-else-if="apercu">
        <div v-if="!apercu.nb_lignes" class="text-center text-gray-400 font-body py-6">
          <span v-if="confirme">Tous les versements de ce lot sont confirmés (rien à régénérer).</span>
          <span v-else>Aucun versement éligible pour cette sélection.</span>
        </div>
        <div v-else>
          <div class="overflow-x-auto">
            <table class="w-full text-sm font-body">
              <thead>
                <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
                  <th class="py-2">Réf. SEMO</th>
                  <th>Bénéficiaire</th>
                  <th>Téléphone</th>
                  <th class="text-right">Montant</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="b in apercu.beneficiaires" :key="b.versement_id" class="border-b border-gray-50">
                  <td class="py-2 font-mono text-xs">{{ b.reference_semo }}</td>
                  <td>{{ b.beneficiaire }}</td>
                  <td class="font-mono text-xs">{{ b.telephone }}</td>
                  <td class="text-right font-semibold">{{ fmt(b.montant) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-gray-100 font-display font-bold text-anatt-700">
                  <td class="py-2" colspan="3">{{ apercu.nb_lignes }} ligne(s)</td>
                  <td class="text-right">{{ fmt(apercu.total) }} FCFA</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>

      <!-- Actions -->
      <div class="flex flex-wrap items-center justify-end gap-3 mt-5 pt-4 border-t border-gray-100">
        <template v-if="solde">
          <span class="text-sm font-display font-semibold text-green-600">Lot confirmé — réinclusion bloquée (anti-double-paiement).</span>
        </template>
        <template v-else>
          <AppButton v-if="lot?.statut === 'GENERE'" variant="outline" :loading="action" @click="marquerErreur">Marquer en erreur</AppButton>
          <AppButton :disabled="!peutGenerer" :loading="action" @click="generer">
            {{ complementaire ? 'Générer le lot complémentaire' : lot ? 'Régénérer le fichier' : 'Générer le fichier' }}{{ extension }}
          </AppButton>
          <AppButton v-if="lot?.statut === 'GENERE'" :loading="action" @click="confirmer">Confirmer le paiement</AppButton>
        </template>
      </div>
    </AppCard>
  </div>
</template>
