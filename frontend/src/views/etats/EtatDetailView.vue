<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppModal from '@/components/shared/AppModal.vue'
import EtatStatusBadge from '@/components/etats/EtatStatusBadge.vue'
import { etatsService } from '@/services/etats'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { partAvance } from '@/composables/usePartAvance'
import type { EtatPaiement, EtatsMission, TrancheEtat } from '@/types'

const route = useRoute()
const toast = useToast()
const { canCreerMissions, canValiderOM, canViserOM, canApprouverOM, canRejeterOM, canNumeroterEtat } =
  useAuth()
const missionId = Number(route.params.missionId)

const etats = ref<EtatsMission | null>(null)
const trancheActive = ref<TrancheEtat>('AVANCE')
const loading = ref(true)
const action = ref(false)
const showRejet = ref(false)
const motif = ref('')
const numero = ref('')

// Tolère une valeur manquante : un montant absent ne doit jamais faire planter
// le rendu de tout l'écran.
const fmt = (v: number) => (v ?? 0).toLocaleString('fr-FR')

/** L'état de la tranche affichée (null tant que son récapitulatif n'existe pas). */
const etat = computed<EtatPaiement | null>(() =>
  trancheActive.value === 'AVANCE' ? etats.value?.avance ?? null : etats.value?.reliquat ?? null,
)

/** L'état des avances a bouclé son circuit : le reliquat peut démarrer. */
const avancesValidees = computed(() => etats.value?.avance?.statut === 'VALIDE')

/** L'onglet reliquat est-il accessible (sinon on affiche le verrou) ? */
const reliquatDeverrouille = computed(() => avancesValidees.value)

/** L'état général consolidé n'existe qu'au bout du bout : les DEUX tranches validées. */
const etatGeneralDisponible = computed(
  () => etats.value?.avance?.statut === 'VALIDE' && etats.value?.reliquat?.statut === 'VALIDE',
)

const libelleTranche = computed(() => (trancheActive.value === 'AVANCE' ? 'des avances' : 'des reliquats'))

const avancePourcentage = partAvance()
const reliquatPourcentage = 100 - avancePourcentage

// Chaque bouton n'apparaît qu'à l'acteur concerné et au statut correspondant.
// Un état REJETÉ se refait : le récapitulatif le ramène en BROUILLON (le backend
// l'autorise, le rejet n'ayant pas figé les OM).
const peutRecapituler = computed(() => {
  if (!canCreerMissions()) return false
  if (trancheActive.value === 'RELIQUAT' && !reliquatDeverrouille.value) return false
  return !etat.value || ['BROUILLON', 'REJETE'].includes(etat.value.statut)
})
const estRejete = computed(() => etat.value?.statut === 'REJETE')
const peutControler = computed(() => canValiderOM() && etat.value?.statut === 'BROUILLON')
const peutViser = computed(() => canViserOM() && etat.value?.statut === 'CONTROLE')
const peutApprouver = computed(() => canApprouverOM() && etat.value?.statut === 'VISA_DAF')
// Le DG ne peut arrêter l'état sans numéro : le backend l'exige, on le signale
// avant le clic plutôt que de laisser tomber une erreur.
const numeroRequisAvantDg = computed(() => peutApprouver.value && !etat.value?.numero)
const peutRejeter = computed(
  () => canRejeterOM() && !!etat.value && ['BROUILLON', 'CONTROLE', 'VISA_DAF'].includes(etat.value.statut),
)
// Le numéro suit le registre papier : modifiable jusqu'à la validation finale.
const peutNumeroter = computed(
  () => canNumeroterEtat() && !!etat.value && etat.value.statut !== 'VALIDE',
)

/** Les trois étapes du circuit, pour la frise de suivi. */
const etapes = computed(() => [
  { cle: 'CONTROLE', libelle: 'Contrôle de conformité', acteur: 'Contrôleur', signature: etat.value?.controle },
  { cle: 'VISA_DAF', libelle: 'Visa « Vu »', acteur: "Directeur de l'Administration et des Finances", signature: etat.value?.visa_daf },
  { cle: 'VALIDATION_DG', libelle: 'Validation finale', acteur: 'Directeur Général', signature: etat.value?.validation_dg },
])

const messageEtape = computed(() => {
  switch (etat.value?.statut) {
    case 'BROUILLON':
      return 'Étape 1 sur 3 — en attente du contrôle de conformité'
    case 'CONTROLE':
      return 'Étape 2 sur 3 — contrôlé, en attente du visa du DAF'
    case 'VISA_DAF':
      return 'Étape 3 sur 3 — visé par le DAF, en attente de la validation du Directeur Général'
    case 'VALIDE':
      return trancheActive.value === 'AVANCE'
        ? 'Circuit achevé — les fichiers des avances peuvent être générés ; l’état des reliquats est débloqué'
        : 'Circuit achevé — les fichiers des reliquats peuvent être générés ; l’état général est disponible'
    case 'REJETE':
      return 'État rejeté — un nouveau récapitulatif est nécessaire'
    default:
      return null
  }
})

async function charger() {
  loading.value = true
  try {
    etats.value = await etatsService.parMission(missionId)
    numero.value = etat.value?.numero ?? ''
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    loading.value = false
  }
}
onMounted(charger)

// Changement d'onglet : le champ numéro suit l'état affiché.
watch(trancheActive, () => {
  numero.value = etat.value?.numero ?? ''
})

/** Remplace l'état de la tranche active dans la paire chargée. */
function remplacerEtat(nouvel: EtatPaiement) {
  const cle = trancheActive.value === 'AVANCE' ? 'avance' : 'reliquat'
  etats.value = { avance: etats.value?.avance ?? null, reliquat: etats.value?.reliquat ?? null, [cle]: nouvel }
  numero.value = nouvel.numero ?? ''
}

/** Enveloppe commune : garde le bouton en attente et remonte l'erreur serveur. */
async function executer(operation: () => Promise<EtatPaiement>, succes: string) {
  action.value = true
  try {
    remplacerEtat(await operation())
    toast.success(succes)
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

const recapituler = () =>
  executer(
    () => etatsService.recapituler(missionId, trancheActive.value),
    `Récapitulatif ${libelleTranche.value} établi. Le brouillon est téléchargeable.`,
  )

const controler = () =>
  executer(() => etatsService.controler(etat.value!.id), 'État contrôlé. Transmis au DAF.')

const viser = () =>
  executer(() => etatsService.viser(etat.value!.id), 'Visa du DAF apposé. Transmis au Directeur Général.')

const approuver = () =>
  executer(
    () => etatsService.approuver(etat.value!.id),
    trancheActive.value === 'AVANCE'
      ? 'État des avances validé et verrouillé. Paiements des avances ouverts, état des reliquats débloqué.'
      : 'État des reliquats validé et verrouillé. Paiements des reliquats ouverts.',
  )

function enregistrerNumero() {
  const valeur = numero.value.trim()
  if (!valeur) {
    toast.warning('Saisissez le numéro de l’état.')
    return
  }
  executer(() => etatsService.definirNumero(etat.value!.id, valeur), 'Numéro enregistré.')
}

async function rejeter() {
  if (motif.value.trim().length < 10) {
    toast.warning('Le motif doit comporter au moins 10 caractères.')
    return
  }
  await executer(() => etatsService.rejeter(etat.value!.id, motif.value.trim()), 'État rejeté.')
  showRejet.value = false
  motif.value = ''
}

async function telecharger(brouillon: boolean) {
  action.value = true
  try {
    await (brouillon
      ? etatsService.telechargerBrouillon(missionId, trancheActive.value)
      : etatsService.telecharger(missionId, trancheActive.value))
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function telechargerFinal() {
  action.value = true
  try {
    await etatsService.telechargerFinal(missionId)
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}
</script>

<template>
  <AppLayout
    title="États de paiement"
    :subtitle="(etats?.avance?.mission?.objet ?? etats?.reliquat?.mission?.objet) ?? 'Mission ' + missionId"
  >
    <template #header-actions>
      <router-link
        to="/etats"
        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50"
      >
        ← États
      </router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <template v-else>
      <!-- Sélecteur de tranche : deux documents, deux circuits. -->
      <div class="flex gap-2 mb-6">
        <button
          class="px-4 py-2 rounded-xl text-sm font-display font-semibold transition-colors"
          :class="trancheActive === 'AVANCE' ? 'bg-anatt-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="trancheActive = 'AVANCE'"
        >
          Avances ({{ avancePourcentage }} %)
          <span v-if="etats?.avance" class="ml-1 text-xs opacity-80">· {{ etats.avance.statut }}</span>
        </button>
        <button
          class="px-4 py-2 rounded-xl text-sm font-display font-semibold transition-colors"
          :class="[
            trancheActive === 'RELIQUAT' ? 'bg-anatt-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
            !reliquatDeverrouille ? 'opacity-60' : '',
          ]"
          @click="trancheActive = 'RELIQUAT'"
        >
          Reliquats ({{ reliquatPourcentage }} %)
          <span v-if="etats?.reliquat" class="ml-1 text-xs opacity-80">· {{ etats.reliquat.statut }}</span>
          <span v-else-if="!reliquatDeverrouille" class="ml-1 text-xs opacity-80">· verrouillé</span>
        </button>

        <!-- Document de clôture : au bout du bout des deux circuits. -->
        <AppButton
          v-if="etatGeneralDisponible"
          variant="outline"
          class="ml-auto"
          :loading="action"
          @click="telechargerFinal"
        >
          Télécharger l’état général
        </AppButton>
      </div>

      <!-- Verrou de séquencement : le reliquat suit la validation des avances. -->
      <AppCard v-if="trancheActive === 'RELIQUAT' && !reliquatDeverrouille" class="text-center py-10 border-l-4 border-l-gold-400">
        <h3 class="font-display font-bold text-gray-800">L’état des reliquats est verrouillé</h3>
        <p class="mt-2 text-sm text-gray-500 font-body">
          Il sera établi une fois l’état des avances validé par le circuit complet
          (contrôle → visa DAF → Directeur Général).
        </p>
        <AppButton variant="outline" class="mt-5" @click="trancheActive = 'AVANCE'">
          Suivre l’état des avances
        </AppButton>
      </AppCard>

      <!-- Aucun récapitulatif encore établi pour la tranche -->
      <AppCard v-else-if="!etat" class="text-center py-10">
        <h3 class="font-display font-bold text-gray-800">Aucun état {{ libelleTranche }} pour cette mission</h3>
        <p class="mt-2 text-sm text-gray-500 font-body">
          Le récapitulatif agrège les ordres de mission et produit le brouillon de l’état {{ libelleTranche }}.
        </p>
        <AppButton v-if="peutRecapituler" class="mt-5" :loading="action" @click="recapituler">
          Établir le récapitulatif {{ libelleTranche }}
        </AppButton>
      </AppCard>

      <div v-else class="space-y-6">
        <!-- En-tête : statut et actions du circuit -->
        <AppCard>
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-display font-bold text-gray-800">
                {{ etat.numero ? 'N°' + etat.numero : 'Numéro non attribué' }}
              </h2>
              <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-anatt-100 text-anatt-700 uppercase">
                {{ trancheActive === 'AVANCE' ? 'Avances' : 'Reliquats' }}
              </span>
              <EtatStatusBadge :statut="etat.statut" />
              <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">
                {{ etat.nb_beneficiaires ?? 0 }} bénéficiaire(s)
              </span>
            </div>

            <div class="flex flex-wrap gap-3">
              <AppButton variant="outline" :loading="action" @click="telecharger(etat.statut !== 'VALIDE')">
                {{ etat.statut === 'VALIDE' ? 'Télécharger l’état' : 'Télécharger le brouillon' }}
              </AppButton>
              <AppButton
                v-if="peutRecapituler && etat"
                :variant="estRejete ? 'primary' : 'ghost'"
                :loading="action"
                @click="recapituler"
              >
                {{ estRejete ? 'Refaire le récapitulatif' : 'Rafraîchir' }}
              </AppButton>
              <AppButton v-if="peutRejeter" variant="danger" :loading="action" @click="showRejet = true">
                Rejeter
              </AppButton>
              <AppButton v-if="peutControler" :loading="action" @click="controler">Contrôler</AppButton>
              <AppButton v-if="peutViser" :loading="action" @click="viser">Apposer le visa DAF</AppButton>
              <AppButton
                v-if="peutApprouver"
                :loading="action"
                :disabled="numeroRequisAvantDg"
                @click="approuver"
              >
                Valider définitivement
              </AppButton>
            </div>
          </div>

          <p v-if="numeroRequisAvantDg" class="mt-3 text-xs font-body text-gold-600">
            Renseignez le numéro de l’état ci-dessous avant la validation finale.
          </p>
          <p v-if="messageEtape" class="mt-3 text-xs font-body text-gray-500">{{ messageEtape }}</p>
          <p v-if="etat.commentaire_rejet" class="mt-3 text-sm text-red-600 font-body">
            Motif du rejet : {{ etat.commentaire_rejet }}
          </p>
        </AppCard>

        <div class="grid lg:grid-cols-2 gap-6">
          <!-- Montants de la tranche -->
          <AppCard>
            <h3 class="font-display font-bold text-gray-800 mb-4">
              Montants {{ libelleTranche }}
            </h3>
            <dl class="space-y-2 text-sm font-body">
              <div class="flex justify-between">
                <dt class="text-gray-500">{{ trancheActive === 'AVANCE' ? `Avance (${avancePourcentage} %)` : `Reliquat (${reliquatPourcentage} %)` }}</dt>
                <dd class="font-semibold text-gray-900">{{ fmt(etat.montant_total) }} FCFA</dd>
              </div>
              <div class="flex justify-between border-t border-gray-100 pt-2">
                <dt class="font-display font-bold text-gray-700">Total de l’état</dt>
                <dd class="font-display font-bold text-anatt-600">{{ fmt(etat.montant_total) }} FCFA</dd>
              </div>
            </dl>
            <p class="mt-4 text-xs font-body text-gray-500 italic">
              Arrêté à la somme de : {{ etat.montant_total_lettres || 'Zéro (0) FRANCS CFA' }}.
            </p>
          </AppCard>

          <!-- Numérotation manuelle -->
          <AppCard>
            <h3 class="font-display font-bold text-gray-800 mb-2">Numéro de l’état {{ libelleTranche }}</h3>
            <p class="text-xs text-gray-500 font-body mb-4">
              Saisi manuellement, suivant le registre de l’agence. Requis avant la validation
              finale, puis figé. Chaque tranche porte son propre numéro.
            </p>
            <div v-if="peutNumeroter" class="flex gap-3">
              <input
                v-model="numero"
                type="text"
                placeholder="0069/ANaTT/DAF/C-SFC"
                class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-sm font-body
                       text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2
                       focus:ring-anatt-500/30 focus:border-anatt-500 transition-all duration-200"
              />
              <AppButton variant="outline" :loading="action" @click="enregistrerNumero">
                Enregistrer
              </AppButton>
            </div>
            <p v-else class="text-sm font-body text-gray-700">
              {{ etat.numero ? 'N°' + etat.numero : 'Non attribué' }}
            </p>
          </AppCard>
        </div>

        <!-- Frise du circuit -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-4">Circuit de validation</h3>
          <ol class="space-y-4">
            <li
              v-for="(etape, i) in etapes"
              :key="etape.cle"
              class="flex gap-4 items-start"
            >
              <span
                class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold font-display"
                :class="etape.signature
                  ? 'bg-anatt-500 text-white'
                  : 'bg-gray-100 text-gray-400'"
              >
                {{ i + 1 }}
              </span>
              <div class="min-w-0">
                <p class="text-sm font-display font-semibold text-gray-800">{{ etape.libelle }}</p>
                <p class="text-xs text-gray-500 font-body">{{ etape.acteur }}</p>
                <template v-if="etape.signature">
                  <p class="mt-1 text-sm font-body text-gray-900 font-semibold">
                    {{ etape.signature.nom }}
                  </p>
                  <p class="text-xs text-gray-500 font-body">
                    {{ etape.signature.date ? new Date(etape.signature.date).toLocaleString('fr-FR') : '' }}
                    <span v-if="etape.signature.code" class="text-gray-400">
                      · Vérification {{ etape.signature.code }}
                    </span>
                  </p>
                </template>
                <p v-else class="mt-1 text-xs font-body text-gold-600 italic">En attente</p>
              </div>
            </li>
          </ol>
        </AppCard>

        <!-- Paiement de la tranche -->
        <AppCard v-if="etat.paiement_ouvert">
          <h3 class="font-display font-bold text-gray-800 mb-2">Fichiers de paiement {{ libelleTranche }}</h3>
          <p class="text-sm text-gray-600 font-body">
            L’état {{ libelleTranche }} est validé : générez les fichiers de cette tranche
            (avance puis reliquat, chaque tranche après validation de son état).
          </p>
          <div class="mt-4 flex flex-wrap gap-3">
            <router-link
              to="/paiements/mtn"
              class="px-5 py-2.5 rounded-xl border-2 border-anatt-500 text-anatt-600 font-display
                     font-semibold text-sm hover:bg-anatt-500 hover:text-white transition-all duration-200"
            >
              Générer MTN
            </router-link>
            <router-link
              to="/paiements/moov"
              class="px-5 py-2.5 rounded-xl border-2 border-anatt-500 text-anatt-600 font-display
                     font-semibold text-sm hover:bg-anatt-500 hover:text-white transition-all duration-200"
            >
              Générer Moov
            </router-link>
          </div>
        </AppCard>
      </div>
    </template>

    <AppModal :show="showRejet" title="Rejeter l’état de paiement" @close="showRejet = false">
      <p class="text-sm text-gray-600 font-body mb-3">
        Le rejet efface les signatures déjà apposées et renvoie l’état en correction.
        Les ordres de mission gardent leur statut : le récapitulatif pourra être refait.
      </p>
      <textarea
        v-model="motif"
        rows="4"
        placeholder="Motif du rejet (10 caractères minimum)"
        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm font-body
               text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2
               focus:ring-red-500/30 focus:border-red-500 transition-all duration-200"
      ></textarea>
      <template #footer>
        <AppButton variant="ghost" @click="showRejet = false">Annuler</AppButton>
        <AppButton variant="danger" :loading="action" @click="rejeter">Confirmer le rejet</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
