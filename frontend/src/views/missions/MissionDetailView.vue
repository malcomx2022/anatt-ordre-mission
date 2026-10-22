<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/shared/AppLayout.vue'
import AppCard from '@/components/shared/AppCard.vue'
import AppButton from '@/components/shared/AppButton.vue'
import AppModal from '@/components/shared/AppModal.vue'
import MissionStatusBadge from '@/components/missions/MissionStatusBadge.vue'
import EtatStatusBadge from '@/components/etats/EtatStatusBadge.vue'
import PaiementStatusBadge from '@/components/paiements/PaiementStatusBadge.vue'
import { ordresService } from '@/services/ordres'
import { useAuth } from '@/composables/useAuth'
import { useToast } from '@/composables/useToast'
import { getErrorMessage } from '@/composables/useApi'
import { usePartAvance } from '@/composables/usePartAvance'
import { numeroEtat } from '@/utils/etats'
import { categorieLabel } from '@/types'
import type { OrdreMission } from '@/types'

const route = useRoute()
const toast = useToast()
const { can } = useAuth()
const id = Number(route.params.id)
// Part d'avance RÉACTIVE : se met à jour dès que la valeur serveur arrive.
const { partAvance: avancePct } = usePartAvance()
const reliquatPct = computed(() => 100 - avancePct.value)

const ordre = ref<OrdreMission | null>(null)
const loading = ref(true)
const action = ref(false)
/** Message serveur en cas d'échec du chargement (404, 403…) : la page ne reste pas blanche. */
const erreur = ref<string | null>(null)

const fmt = (v: number) => v.toLocaleString('fr-FR')

// ── Amendement des jours facturés (rallonge / réduction) ──
// Une fois l'avance engagée (état des avances validé), seul le reliquat bouge et
// peut devenir négatif : c'est un trop-perçu à récupérer par retenue sur salaire.
const showAmend = ref(false)
const amendForm = ref<{ nb_jours: number | null; motif: string }>({ nb_jours: null, motif: '' })

const avanceEngagee = computed(() => ordre.value?.avance_engagee != null)
const reliquatNegatif = computed(() => (ordre.value?.reliquat ?? 0) < 0)

// Amendable tant que l'OM n'est ni rejeté, ni annulé, ni entièrement soldé. Les
// gardes fines (état en cours de signature, état des reliquats validé) restent
// portées par l'API, qui répond 422 le cas échéant.
const peutAmender = computed(
  () => can('om.retranscrire') && !!ordre.value && ['IMPORTE', 'VALIDE'].includes(ordre.value.statut),
)

function ouvrirAmend() {
  amendForm.value = { nb_jours: ordre.value?.nb_jours ?? null, motif: '' }
  showAmend.value = true
}

async function soumettreAmend() {
  if (!ordre.value) return
  action.value = true
  try {
    await ordresService.amender(id, {
      nb_jours: amendForm.value.nb_jours,
      motif: amendForm.value.motif,
    })
    showAmend.value = false
    toast.success('Ordre de mission amendé.')
    await load()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

/*
 * La validation ne se joue PAS sur l'OM individuel : elle se joue sur les états
 * de paiement de la mission (avances puis reliquats), chacun contrôlé, puis visé
 * par le DAF, puis validé par le DG — la validation de l'état des avances valide
 * d'un coup tous les OM de la mission.
 * Cette fiche est donc en lecture seule pour le circuit et renvoie vers l'état.
 */
const etat = computed(() => ordre.value?.mission?.etat_paiement ?? null)

// ── Circuit de repli PAR OM (missions SANS aucun état de paiement) ──
// Reprise de données / correction ponctuelle : IMPORTE → VALIDE en une étape, ou
// rejet motivé. Exclusif du circuit par état : le backend refuse (422) dès qu'un
// état existe (MissionService::exigerAbsenceDEtat). `etat_paiement` vaut null
// (relation chargée, sans état) — l'état des reliquats n'existe jamais sans
// celui des avances, donc null = mission sans aucun état.
const missionSansEtat = computed(() => !!ordre.value?.mission && ordre.value.mission.etat_paiement === null)
const circuitParOm = computed(() => missionSansEtat.value && ordre.value?.statut === 'IMPORTE')
const peutValiderOm = computed(() => circuitParOm.value && can('om.valider'))
const peutRejeterOm = computed(() => circuitParOm.value && can('om.rejeter'))

const showRejet = ref(false)
const motifRejet = ref('')
const MOTIF_MIN = 10

async function validerOm() {
  action.value = true
  try {
    await ordresService.valider(id)
    toast.success('Ordre de mission validé.')
    await load()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

function ouvrirRejet() {
  motifRejet.value = ''
  showRejet.value = true
}

async function soumettreRejet() {
  if (motifRejet.value.trim().length < MOTIF_MIN) return
  action.value = true
  try {
    await ordresService.rejeter(id, motifRejet.value.trim())
    showRejet.value = false
    toast.success('Ordre de mission rejeté.')
    await load()
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

// Le brouillon de l'OM (son document individuel) reste téléchargeable tant que
// la mission n'est pas définitivement validée.
const peutTelechargerBrouillon = computed(
  () => !!ordre.value && ordre.value.statut !== 'VALIDE' && ordre.value.statut !== 'SOLDE',
)

async function telechargerBrouillon() {
  action.value = true
  try {
    await ordresService.telechargerBrouillon(id)
  } catch (e) {
    toast.error(getErrorMessage(e))
  } finally {
    action.value = false
  }
}

async function load() {
  loading.value = true
  erreur.value = null
  try {
    ordre.value = await ordresService.get(id)
  } catch (e) {
    // On garde le message serveur (ex. « Ordre de mission introuvable. »).
    ordre.value = null
    erreur.value = getErrorMessage(e)
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <AppLayout title="Ordre de mission" :subtitle="ordre ? ordre.reference_semo : 'Chargement…'">
    <template #header-actions>
      <router-link to="/ordres" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">← Ordres</router-link>
      <router-link v-if="ordre" :to="`/ordres/${id}/historique`" class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-display font-medium text-gray-700 hover:bg-gray-50">Historique</router-link>
    </template>

    <p v-if="loading" class="text-center text-gray-400 font-body py-10">Chargement…</p>

    <AppCard v-else-if="erreur" class="max-w-xl mx-auto text-center">
      <div role="alert" class="space-y-3 py-4">
        <svg class="w-10 h-10 mx-auto text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <h2 class="font-display font-bold text-gray-800">Ordre de mission indisponible</h2>
        <p class="text-sm text-gray-500 font-body" data-test="erreur-chargement">{{ erreur }}</p>
        <div class="flex justify-center gap-3 pt-2">
          <AppButton variant="outline" @click="load">Réessayer</AppButton>
          <router-link to="/ordres" class="px-5 py-2.5 rounded-xl bg-anatt-500 text-white font-display font-bold text-sm hover:bg-anatt-600 transition-all duration-200">Retour aux ordres</router-link>
        </div>
      </div>
    </AppCard>

    <div v-else-if="ordre" class="space-y-6">
      <!-- En-tête -->
      <AppCard>
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-display font-bold text-gray-800">{{ ordre.reference_semo }}</h2>
            <MissionStatusBadge :statut="ordre.statut" />
            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-600">{{ ordre.role }}</span>
            <span v-if="reliquatNegatif" class="px-3 py-1 rounded-full text-[11px] font-bold bg-red-50 text-red-600">Trop-perçu</span>
          </div>
          <div class="flex items-center gap-3">
            <AppButton v-if="peutRejeterOm" variant="danger" :loading="action" @click="ouvrirRejet">
              <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <path d="m15 9-6 6" />
                  <path d="m9 9 6 6" />
                </svg>
                Rejeter
              </span>
            </AppButton>
            <AppButton v-if="peutValiderOm" :loading="action" @click="validerOm">
              <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
                Valider
              </span>
            </AppButton>
            <AppButton v-if="peutAmender" variant="outline" @click="ouvrirAmend">
              <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 20h9" />
                  <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
                </svg>
                Amender
              </span>
            </AppButton>
            <AppButton
              v-if="peutTelechargerBrouillon"
              variant="outline"
              :loading="action"
              @click="telechargerBrouillon"
            >
              <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                Télécharger le brouillon
              </span>
            </AppButton>
          </div>
        </div>
        <p v-if="ordre.statut === 'REJETE' && ordre.commentaire_rejet" class="mt-3 text-sm text-red-600 font-body">
          Motif du rejet : {{ ordre.commentaire_rejet }}
        </p>
      </AppCard>

      <!-- Renvoi vers l'état de paiement : c'est là que se joue la validation -->
      <AppCard class="border-l-4 border-anatt-500">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h3 class="font-display font-bold text-gray-800 flex items-center gap-2">
              État des avances de la mission
              <EtatStatusBadge v-if="etat" :statut="etat.statut" />
              <span v-else class="px-3 py-1 rounded-full text-[11px] font-bold bg-gold-50 text-gold-600">
                À établir
              </span>
            </h3>
            <p class="text-sm text-gray-500 font-body mt-1">
              La validation d’un ordre de mission ne se fait pas ordre par ordre : le
              <strong>contrôle</strong>, le <strong>visa du DAF</strong> puis la
              <strong>validation du Directeur Général</strong> portent sur l’état de la
              mission, ce qui valide d’un coup tous ses ordres de mission.
              <span v-if="etat?.numero"> — {{ numeroEtat(etat.numero) }}</span>
            </p>
            <p v-if="peutValiderOm || peutRejeterOm" class="text-xs text-gold-600 font-body mt-2">
              Mission sans état de paiement : la validation ordre par ordre reste possible (voie de repli,
              exclusive du circuit par état).
            </p>
          </div>
          <router-link
            :to="`/missions/${ordre.mission_id}/etat`"
            class="px-5 py-2.5 rounded-xl bg-anatt-500 text-white font-display font-bold text-sm
                   hover:bg-anatt-600 transition-all duration-200 whitespace-nowrap"
          >
            {{ etat ? 'Ouvrir l’état' : 'Établir l’état' }}
          </router-link>
        </div>
      </AppCard>

      <div class="grid lg:grid-cols-2 gap-6">
        <!-- Mission -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Mission</h3>
          <dl class="grid grid-cols-2 gap-2 text-sm font-body">
            <dt class="text-gray-400">Objet</dt><dd>{{ ordre.mission?.objet ?? '—' }}</dd>
            <dt class="text-gray-400">Destination</dt><dd>{{ ordre.mission?.destination ?? '—' }}</dd>
            <dt class="text-gray-400">Zone</dt><dd>{{ ordre.zone_code }}</dd>
            <dt class="text-gray-400">Période</dt><dd>{{ ordre.mission?.date_depart }} → {{ ordre.mission?.date_retour }}</dd>
            <dt class="text-gray-400">Imputation</dt><dd>{{ ordre.mission?.imputation_budgetaire ?? '—' }}</dd>
            <dt class="text-gray-400">Moyen transport</dt><dd>{{ ordre.moyen_transport ?? '—' }}</dd>
            <dt class="text-gray-400">Conducteur à bord</dt><dd>{{ ordre.conducteur_a_bord ?? '—' }}</dd>
          </dl>
        </AppCard>

        <!-- Bénéficiaire + indemnité -->
        <AppCard>
          <h3 class="font-display font-bold text-gray-800 mb-3">Bénéficiaire & indemnité</h3>
          <dl class="grid grid-cols-2 gap-2 text-sm font-body">
            <dt class="text-gray-400">Agent</dt><dd>{{ ordre.agent ? `${ordre.agent.nom} ${ordre.agent.prenoms}` : '—' }}</dd>
            <dt class="text-gray-400">Matricule</dt><dd>{{ ordre.agent?.matricule }}</dd>
            <dt class="text-gray-400">Catégorie</dt><dd class="font-semibold text-anatt-600">{{ categorieLabel(ordre.categorie) }}</dd>
            <dt class="text-gray-400">Type</dt><dd>{{ ordre.type_prise_en_charge }}</dd>
            <dt class="text-gray-400">Nb jours</dt><dd>{{ ordre.nb_jours }}</dd>
            <template v-if="ordre.lignes_per_diem && ordre.lignes_per_diem.length > 1">
              <dt class="text-gray-400">Répartition</dt>
              <dd class="text-[12px] text-gray-600">
                <span v-for="(l, i) in ordre.lignes_per_diem" :key="i" class="block">{{ l.type }} : {{ l.nb_jours }} j × {{ fmt(l.taux) }} = {{ fmt(l.montant) }}</span>
              </dd>
            </template>
            <template v-if="ordre.montant_transport && ordre.montant_transport > 0">
              <dt class="text-gray-400">Transport</dt><dd>{{ fmt(ordre.montant_transport) }} FCFA</dd>
            </template>
            <dt class="text-gray-400">Total</dt><dd class="font-display font-bold text-anatt-700">{{ fmt(ordre.montant_total) }} FCFA</dd>
            <dt class="text-gray-400">Avance ({{ avancePct }} %)</dt><dd>{{ fmt(ordre.avance) }}</dd>
            <dt class="text-gray-400">Reliquat ({{ reliquatPct }} %)</dt><dd>{{ fmt(ordre.reliquat) }}</dd>
          </dl>
        </AppCard>
      </div>

      <!-- Versements -->
      <AppCard>
        <h3 class="font-display font-bold text-gray-800 mb-3">Versements</h3>
        <table class="w-full text-sm font-body">
          <thead>
            <tr class="text-left text-[11px] text-gray-400 uppercase border-b border-gray-100">
              <th class="py-2">Tranche</th><th>Opérateur</th><th>Téléphone</th><th class="text-right">Montant</th><th>Statut</th><th>Date paiement</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!ordre.versements?.length"><td colspan="6" class="py-6 text-center text-gray-400">Aucun versement.</td></tr>
            <tr v-for="v in ordre.versements" :key="v.id" class="border-b border-gray-50">
              <td class="py-2 font-display">{{ v.type }}</td>
              <td>{{ v.operateur }}</td>
              <td class="font-mono text-xs">{{ v.telephone }}</td>
              <td class="text-right font-semibold">{{ fmt(v.montant) }}</td>
              <td><PaiementStatusBadge :statut="v.statut" /></td>
              <td class="text-gray-500 text-xs">{{ v.date_paiement ? new Date(v.date_paiement).toLocaleString('fr-FR') : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </AppCard>

      <!-- Historique des amendements -->
      <AppCard v-if="ordre.amendements && ordre.amendements.length">
        <h3 class="font-display font-bold text-gray-800 mb-3">Amendements</h3>
        <ul class="space-y-3">
          <li v-for="a in ordre.amendements" :key="a.id" class="border-l-4 border-gold-400 pl-4 py-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-body">
              <span class="font-display font-semibold text-gray-800">{{ a.nb_jours_avant }} j → {{ a.nb_jours_apres }} j</span>
              <span class="text-gray-500">{{ fmt(a.montant_total_avant) }} → {{ fmt(a.montant_total_apres) }} FCFA</span>
              <span
                class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                :class="a.delta_montant >= 0 ? 'bg-anatt-50 text-anatt-600' : 'bg-red-50 text-red-600'"
              >{{ a.delta_montant >= 0 ? '+' : '' }}{{ fmt(a.delta_montant) }}</span>
            </div>
            <p class="text-sm text-gray-600 font-body mt-1">« {{ a.motif }} »</p>
            <p class="text-[11px] text-gray-400 font-body mt-0.5">
              {{ a.amende_par_nom ?? '—' }}<span v-if="a.amende_le"> · {{ new Date(a.amende_le).toLocaleString('fr-FR') }}</span>
            </p>
          </li>
        </ul>
      </AppCard>
    </div>

    <!-- Modale de rejet (circuit de repli par OM) -->
    <AppModal :show="showRejet" title="Rejeter l’ordre de mission" @close="showRejet = false">
      <div class="space-y-4">
        <p class="text-sm text-gray-500 font-body">
          Le rejet est définitif pour cet ordre de mission et sera tracé au journal d’audit.
        </p>
        <div>
          <label class="block text-sm font-display font-medium text-gray-700 mb-1">Motif du rejet (≥ {{ MOTIF_MIN }} caractères)</label>
          <textarea
            v-model="motifRejet" rows="3" data-test="motif-rejet"
            placeholder="Justification du rejet…"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm font-body
                   focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
          />
          <p class="text-[11px] text-gray-400 font-body mt-1">{{ motifRejet.trim().length }} / {{ MOTIF_MIN }} caractères minimum</p>
        </div>
      </div>
      <template #footer>
        <button class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-display font-medium text-gray-700 hover:bg-gray-50" @click="showRejet = false">Annuler</button>
        <AppButton
          variant="danger"
          :loading="action"
          :disabled="motifRejet.trim().length < MOTIF_MIN"
          @click="soumettreRejet"
        >Confirmer le rejet</AppButton>
      </template>
    </AppModal>

    <!-- Modale d'amendement -->
    <AppModal :show="showAmend" title="Amender l’ordre de mission" @close="showAmend = false">
      <div class="space-y-4">
        <p class="text-sm text-gray-500 font-body">
          Ajustez le nombre de jours facturés.
          <template v-if="avanceEngagee">
            L’avance est <strong>engagée</strong> : elle ne bouge plus, seul le
            <strong>reliquat</strong> absorbe l’écart (il peut devenir négatif = trop-perçu à recouvrer).
          </template>
          <template v-else>
            L’avance n’est pas encore engagée : avance et reliquat sont recalculés ({{ avancePct }} / {{ reliquatPct }} %).
          </template>
        </p>
        <div>
          <label class="block text-sm font-display font-medium text-gray-700 mb-1">Nombre de jours</label>
          <input
            v-model.number="amendForm.nb_jours"
            type="number" min="1"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm font-body
                   focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
          >
        </div>
        <div>
          <label class="block text-sm font-display font-medium text-gray-700 mb-1">Motif (≥ 10 caractères)</label>
          <textarea
            v-model="amendForm.motif" rows="3"
            placeholder="Justification de l’amendement (rallonge ou réduction de la mission)…"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm font-body
                   focus:outline-none focus:ring-2 focus:ring-anatt-500/30 focus:border-anatt-500"
          />
        </div>
      </div>
      <template #footer>
        <button class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-display font-medium text-gray-700 hover:bg-gray-50" @click="showAmend = false">Annuler</button>
        <AppButton
          :loading="action"
          :disabled="!amendForm.nb_jours || amendForm.motif.trim().length < 10"
          @click="soumettreAmend"
        >Enregistrer l’amendement</AppButton>
      </template>
    </AppModal>
  </AppLayout>
</template>
