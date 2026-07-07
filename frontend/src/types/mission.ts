import type { Agent, Categorie } from './agent'
import type { Versement } from './paiement'

export type ZoneCode = 'INTERIEUR' | 'EXT_ZONE_I' | 'EXT_ZONE_II' | 'EXT_ZONE_III'
export type RoleOM = 'PARTICIPANT' | 'CAV'

export type TypePriseEnCharge =
  | 'UN_REPAS' | 'DEUX_REPAS' | 'JOURNEE_COMPLETE'
  | 'LOGE_NOURRI' | 'LOGE_SEUL' | 'NOURRI_SEUL'

export type StatutOM =
  | 'BROUILLON' | 'IMPORTE' | 'EN_ATTENTE' | 'VALIDE' | 'REJETE' | 'SOLDE' | 'ANNULE'

/** Saisie : nombre de jours pour un type de prise en charge donné. */
export interface LignePerDiemInput {
  type: TypePriseEnCharge
  nb_jours: number
}

/** Ligne calculée renvoyée par l'API (état ANaTT détaillé). */
export interface LignePerDiem {
  type: TypePriseEnCharge
  taux: number
  nb_jours: number
  montant: number
}

/** Types de prise en charge autorisés selon la portée de la zone. */
export const TYPES_INTERIEUR: TypePriseEnCharge[] = ['UN_REPAS', 'DEUX_REPAS', 'JOURNEE_COMPLETE']
export const TYPES_EXTERIEUR: TypePriseEnCharge[] = ['JOURNEE_COMPLETE', 'LOGE_NOURRI', 'LOGE_SEUL', 'NOURRI_SEUL']

export function typesPourZone(zone: ZoneCode | '' | null | undefined): TypePriseEnCharge[] {
  return zone === 'INTERIEUR' ? TYPES_INTERIEUR : zone ? TYPES_EXTERIEUR : []
}

export interface Mission {
  id: number
  objet: string
  destination: string
  zone_code: ZoneCode
  date_depart: string
  date_retour: string
  imputation_budgetaire?: string | null
  prise_en_charge_tiers: boolean
  observations?: string | null
  ordres_mission?: OrdreMission[]
  created_at?: string
  updated_at?: string
}

export interface OrdreMission {
  id: number
  reference_semo: string
  mission_id: number
  mission?: Mission
  agent_id: number
  agent?: Agent
  role: RoleOM
  categorie: Categorie
  zone_code: ZoneCode
  type_prise_en_charge: TypePriseEnCharge
  nb_jours: number
  lignes_per_diem?: LignePerDiem[]
  montant_transport?: number
  montant_total: number
  avance: number
  reliquat: number
  statut: StatutOM
  conducteur_a_bord?: string | null
  reference_autorisation?: string | null
  moyen_transport?: string | null
  commentaire_rejet?: string | null
  versements?: Versement[]
  created_at?: string
  updated_at?: string
}

/** Étape 1 du wizard : contexte commun de la mission. */
export interface MissionContexte {
  objet: string
  destination: string
  zone_code: ZoneCode | ''
  date_depart: string
  date_retour: string
  imputation_budgetaire?: string
  prise_en_charge_tiers: boolean
  observations?: string
}

/** Étape 2 du wizard : un bénéficiaire (participant ou CAV). */
export interface BeneficiaireForm {
  reference_semo: string
  agent_id: number | null
  agent?: Agent | null
  role: RoleOM
  categorie?: Categorie
  type_prise_en_charge: TypePriseEnCharge | ''
  nb_jours?: number | null
  /** Répartition détaillée jours/type (état ANaTT). Vide => type unique × nb_jours. */
  lignes?: LignePerDiemInput[]
  /** Montant de transport (colonne TRANSPORT de l'état). */
  montant_transport?: number | null
  conducteur_a_bord?: string | null
  reference_autorisation?: string | null
  moyen_transport?: string | null
}

/** Charge utile envoyée à POST /api/missions. */
export interface MissionFormData {
  objet: string
  destination: string
  zone_code?: ZoneCode | ''
  date_depart: string
  date_retour: string
  imputation_budgetaire?: string
  prise_en_charge_tiers: boolean
  observations?: string
  ordres: Array<{
    reference_semo: string
    agent_id: number
    role: RoleOM
    categorie?: Categorie
    type_prise_en_charge: TypePriseEnCharge
    nb_jours?: number | null
    lignes?: LignePerDiemInput[]
    montant_transport?: number | null
    conducteur_a_bord?: string | null
    reference_autorisation?: string | null
    moyen_transport?: string | null
  }>
}

export interface MissionHistoryEntry {
  id: number
  action: string
  entite: string
  entite_id: number
  donnees?: Record<string, unknown>
  utilisateur?: { id: number; name: string; email: string; role: string } | null
  created_at: string
}
