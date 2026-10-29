import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { baremesService } from '@/services/baremes'
import { missionsService } from '@/services/missions'
import { usePartAvance } from '@/composables/usePartAvance'
import type {
  Mission, MissionContexte, BeneficiaireForm, MissionFormData,
  Bareme, ZoneCode, Categorie, TypePriseEnCharge,
} from '@/types'

/** Part de l'avance (réactive) : les calculs se rafraîchissent dès la valeur serveur reçue. */
const { partAvance: partAvanceRef } = usePartAvance()

function contexteVierge(): MissionContexte {
  return {
    objet: '',
    destination: '',
    zone_code: 'INTERIEUR',
    date_depart: '',
    date_retour: '',
    imputation_budgetaire: '',
    prise_en_charge_tiers: false,
    observations: '',
  }
}

function beneficiaireVierge(): BeneficiaireForm {
  return {
    reference_semo: '',
    agent_id: null,
    agent: null,
    role: 'PARTICIPANT',
    categorie: undefined,
    type_prise_en_charge: '',
    nb_jours: null,
    lignes: [],
    montant_transport: null,
    conducteur_a_bord: '',
    reference_autorisation: '',
    moyen_transport: '',
  }
}

/** Décompte de jours inclusif (jour de départ + jour de retour comptés) ; 0 si le retour précède le départ. */
function joursInclusifs(depart: string, retour: string): number {
  if (!depart || !retour) return 0
  const d = new Date(depart).getTime()
  const r = new Date(retour).getTime()
  if (Number.isNaN(d) || Number.isNaN(r) || r < d) return 0
  return Math.round((r - d) / 86_400_000) + 1
}

export interface PerDiem {
  ok: boolean          // false => barème introuvable pour un des types
  taux: number | null  // taux unique (mode simple) ; null en mode multi-lignes
  nb_jours: number
  per_diem: number
  transport: number
  total: number
  avance: number
  reliquat: number
}

export const useOrdresMissionStore = defineStore('ordres-mission', () => {
  const contexte = ref<MissionContexte>(contexteVierge())
  const beneficiaires = ref<BeneficiaireForm[]>([])
  const baremes = ref<Bareme[]>([])
  const submitting = ref(false)

  const nbJoursMission = computed(() => joursInclusifs(contexte.value.date_depart, contexte.value.date_retour))

  async function chargerBaremes(): Promise<void> {
    if (!baremes.value.length) {
      baremes.value = await baremesService.list()
    }
  }

  function taux(categorie?: Categorie, zone?: ZoneCode | '', type?: TypePriseEnCharge | ''): number | null {
    if (!categorie || !zone || !type) return null
    const b = baremes.value.find(
      (x) => x.categorie === categorie && x.zone_code === zone && x.type_prise_en_charge === type,
    )
    return b ? b.taux_journalier : null
  }

  function nbJoursDe(b: BeneficiaireForm): number {
    return b.nb_jours && b.nb_jours > 0 ? b.nb_jours : nbJoursMission.value
  }

  /**
   * Per diem temps réel d'un bénéficiaire (répartition avance/reliquat paramétrable).
   * Prend en compte la répartition détaillée par type (état ANaTT) si présente,
   * sinon type unique × nb_jours, plus l'éventuel montant de transport.
   */
  function calculer(b: BeneficiaireForm): PerDiem {
    const lignes = (b.lignes ?? []).filter((l) => l.type && l.nb_jours > 0)
    const transport = Math.max(0, Number(b.montant_transport) || 0)

    if (contexte.value.prise_en_charge_tiers) {
      const nb = lignes.length ? lignes.reduce((s, l) => s + l.nb_jours, 0) : nbJoursDe(b)
      return { ok: true, taux: 0, nb_jours: nb, per_diem: 0, transport: 0, total: 0, avance: 0, reliquat: 0 }
    }

    let perDiem = 0
    let nbJours = 0
    let tauxAffiche: number | null = null

    if (lignes.length) {
      for (const l of lignes) {
        const t = taux(b.categorie, contexte.value.zone_code, l.type)
        if (t == null) return { ok: false, taux: null, nb_jours: 0, per_diem: 0, transport, total: transport, avance: 0, reliquat: 0 }
        perDiem += t * l.nb_jours
        nbJours += l.nb_jours
      }
    } else {
      const nb = nbJoursDe(b)
      const t = taux(b.categorie, contexte.value.zone_code, b.type_prise_en_charge)
      if (t == null) return { ok: false, taux: null, nb_jours: nb, per_diem: 0, transport, total: transport, avance: 0, reliquat: 0 }
      tauxAffiche = t
      perDiem = t * nb
      nbJours = nb
    }

    const total = perDiem + transport
    const avance = Math.round(total * partAvanceRef.value / 100)   // paramétrable (réactif)
    return { ok: true, taux: tauxAffiche, nb_jours: nbJours, per_diem: perDiem, transport, total, avance, reliquat: total - avance }
  }

  const totalMission = computed(() => beneficiaires.value.reduce((s, b) => s + calculer(b).total, 0))

  /** Références SEMO présentes en double dans le wizard. */
  const referencesEnDoublon = computed<Set<string>>(() => {
    const vues = new Map<string, number>()
    for (const b of beneficiaires.value) {
      const ref = b.reference_semo.trim()
      if (ref) vues.set(ref, (vues.get(ref) ?? 0) + 1)
    }
    return new Set([...vues.entries()].filter(([, n]) => n > 1).map(([ref]) => ref))
  })

  /** Agents présents deux fois : le serveur refuse (ordres.*.agent_id distinct). */
  const agentsEnDoublon = computed<Set<number>>(() => {
    const vus = new Map<number, number>()
    for (const b of beneficiaires.value) {
      if (b.agent_id) vus.set(b.agent_id, (vus.get(b.agent_id) ?? 0) + 1)
    }
    return new Set([...vus.entries()].filter(([, n]) => n > 1).map(([id]) => id))
  })

  // Dates inversées : refusées par le serveur (date_retour after_or_equal:date_depart) ; signalées
  // dès l'étape 1 plutôt qu'à l'enregistrement, avec le même motif.
  const datesInversees = computed(() =>
    !!contexte.value.date_depart && !!contexte.value.date_retour
    && contexte.value.date_retour < contexte.value.date_depart,
  )

  const etape1Valide = computed(() =>
    !!contexte.value.objet.trim()
    && !!contexte.value.destination.trim()
    && !!contexte.value.zone_code
    && !!contexte.value.date_depart
    && !!contexte.value.date_retour
    && nbJoursMission.value > 0,
  )

  function beneficiaireValide(b: BeneficiaireForm): boolean {
    const ref = b.reference_semo.trim()
    // Répartition valide : un type unique OU au moins une ligne détaillée > 0 jour.
    const aLignes = (b.lignes ?? []).some((l) => l.type && l.nb_jours > 0)
    return !!b.agent_id
      && !agentsEnDoublon.value.has(b.agent_id)
      && !!ref
      && !referencesEnDoublon.value.has(ref)
      && !!b.categorie
      && (!!b.type_prise_en_charge || aLignes)
  }

  /**
   * Ce qui manque à un bénéficiaire pour être valide, en clair.
   * Sert à dire à l'utilisateur QUOI corriger, et pas seulement que ça bloque.
   */
  function champsManquants(b: BeneficiaireForm): string[] {
    const manques: string[] = []
    const ref = b.reference_semo.trim()
    const aLignes = (b.lignes ?? []).some((l) => l.type && l.nb_jours > 0)

    if (!b.agent_id) manques.push('agent')
    else if (agentsEnDoublon.value.has(b.agent_id)) manques.push('agent déjà présent dans la mission')
    if (!ref) manques.push('référence SEMO')
    else if (referencesEnDoublon.value.has(ref)) manques.push('référence SEMO en doublon')
    if (!b.categorie) manques.push('catégorie')
    if (!b.type_prise_en_charge && !aLignes) manques.push('type de prise en charge')

    return manques
  }

  const etape2Valide = computed(() =>
    beneficiaires.value.length > 0 && beneficiaires.value.every(beneficiaireValide),
  )

  /** Bénéficiaires incomplets, repérés par leur rang d'affichage. */
  const beneficiairesIncomplets = computed(() =>
    beneficiaires.value
      .map((b, i) => ({ rang: i + 1, manques: champsManquants(b) }))
      .filter((x) => x.manques.length > 0),
  )

  function ajouterBeneficiaire(prefill: Partial<BeneficiaireForm> = {}): void {
    beneficiaires.value.push({ ...beneficiaireVierge(), ...prefill })
  }

  function retirerBeneficiaire(index: number): void {
    beneficiaires.value.splice(index, 1)
  }

  function reset(): void {
    contexte.value = contexteVierge()
    beneficiaires.value = []
  }

  function construirePayload(): MissionFormData {
    return {
      objet: contexte.value.objet,
      destination: contexte.value.destination,
      zone_code: contexte.value.zone_code,
      date_depart: contexte.value.date_depart,
      date_retour: contexte.value.date_retour,
      imputation_budgetaire: contexte.value.imputation_budgetaire || undefined,
      prise_en_charge_tiers: contexte.value.prise_en_charge_tiers,
      observations: contexte.value.observations || undefined,
      ordres: beneficiaires.value.map((b) => ({
        reference_semo: b.reference_semo.trim(),
        agent_id: b.agent_id as number,
        role: b.role,
        categorie: b.categorie,
        // Omis en répartition détaillée (les lignes portent les types) : n'envoyer
        // le type de prise en charge que s'il est renseigné (mode simple).
        type_prise_en_charge: (b.type_prise_en_charge || undefined) as TypePriseEnCharge | undefined,
        nb_jours: b.nb_jours || undefined,
        // Répartition détaillée (jours > 0 uniquement) ; omise si vide.
        lignes: (b.lignes ?? []).filter((l) => l.type && l.nb_jours > 0).length
          ? (b.lignes ?? []).filter((l) => l.type && l.nb_jours > 0)
          : undefined,
        montant_transport: b.montant_transport ? Number(b.montant_transport) : undefined,
        conducteur_a_bord: b.role === 'PARTICIPANT' ? (b.conducteur_a_bord || undefined) : undefined,
        reference_autorisation: b.reference_autorisation || undefined,
        moyen_transport: b.moyen_transport || undefined,
      })),
    }
  }

  async function enregistrer(): Promise<Mission> {
    submitting.value = true
    try {
      return await missionsService.create(construirePayload())
    } finally {
      submitting.value = false
    }
  }

  return {
    contexte, beneficiaires, baremes, submitting,
    nbJoursMission, totalMission, referencesEnDoublon, agentsEnDoublon,
    datesInversees, etape1Valide, etape2Valide, beneficiairesIncomplets, champsManquants,
    chargerBaremes, taux, calculer, beneficiaireValide,
    ajouterBeneficiaire, retirerBeneficiaire, reset, construirePayload, enregistrer,
  }
})
