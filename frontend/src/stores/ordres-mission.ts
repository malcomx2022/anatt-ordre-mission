import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { baremesService } from '@/services/baremes'
import { missionsService } from '@/services/missions'
import type {
  Mission, MissionContexte, BeneficiaireForm, MissionFormData,
  Bareme, ZoneCode, Categorie, TypePriseEnCharge,
} from '@/types'

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
    conducteur_a_bord: '',
    reference_autorisation: '',
    moyen_transport: '',
  }
}

/** Décompte de jours inclusif (jour de départ + jour de retour comptés). */
function joursInclusifs(depart: string, retour: string): number {
  if (!depart || !retour) return 0
  const d = new Date(depart).getTime()
  const r = new Date(retour).getTime()
  if (Number.isNaN(d) || Number.isNaN(r)) return 0
  return Math.max(1, Math.round((r - d) / 86_400_000) + 1)
}

export interface PerDiem {
  taux: number | null
  nb_jours: number
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

  /** Per diem temps réel d'un bénéficiaire (avance 70 % / reliquat 30 %). */
  function calculer(b: BeneficiaireForm): PerDiem {
    const nb = nbJoursDe(b)
    if (contexte.value.prise_en_charge_tiers) {
      return { taux: 0, nb_jours: nb, total: 0, avance: 0, reliquat: 0 }
    }
    const t = taux(b.categorie, contexte.value.zone_code, b.type_prise_en_charge)
    if (t == null) {
      return { taux: null, nb_jours: nb, total: 0, avance: 0, reliquat: 0 }
    }
    const total = t * nb
    const avance = Math.round(total * 0.7)   // 70 %
    return { taux: t, nb_jours: nb, total, avance, reliquat: total - avance }
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
    return !!b.agent_id
      && !!ref
      && !referencesEnDoublon.value.has(ref)
      && !!b.categorie
      && !!b.type_prise_en_charge
  }

  const etape2Valide = computed(() =>
    beneficiaires.value.length > 0 && beneficiaires.value.every(beneficiaireValide),
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
        type_prise_en_charge: b.type_prise_en_charge as TypePriseEnCharge,
        nb_jours: b.nb_jours || undefined,
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
    nbJoursMission, totalMission, referencesEnDoublon,
    etape1Valide, etape2Valide,
    chargerBaremes, taux, calculer, beneficiaireValide,
    ajouterBeneficiaire, retirerBeneficiaire, reset, construirePayload, enregistrer,
  }
})
