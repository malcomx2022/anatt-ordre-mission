import type { Operateur } from './agent'
import type { Mission } from './mission'

export type { Operateur }
export type TrancheVersement = 'AVANCE' | 'RELIQUAT'
export type StatutVersement = 'EN_ATTENTE' | 'GENERE' | 'CONFIRME' | 'ANNULE'
export type StatutLot = 'GENERE' | 'CONFIRME' | 'ERREUR'

export interface Versement {
  id: number
  ordre_mission_id: number
  lot_id?: number | null
  type: TrancheVersement
  montant: number
  operateur: Operateur
  telephone: string
  statut: StatutVersement
  date_paiement?: string | null
  // Contexte présent quand l'API charge l'OM (ex. historique des versements).
  reference_semo?: string
  beneficiaire?: string | null
  mission?: { objet?: string | null; destination?: string | null }
}

export interface LotPaiement {
  id: number
  mission_id: number
  mission?: Mission
  tranche: TrancheVersement
  operateur: Operateur
  statut: StatutLot
  nom_fichier?: string | null
  nb_lignes: number
  montant_total: number
  genere_le?: string | null
  genere_par?: number | null
  confirme_le?: string | null
  confirme_par?: number | null
  created_at?: string
  updated_at?: string
}

/** Bénéficiaire éligible affiché dans l'aperçu d'un lot avant génération. */
export interface LotBeneficiaire {
  versement_id: number
  reference_semo: string
  beneficiaire: string
  telephone: string
  montant: number
}

export interface LotPreview {
  mission_id: number
  mission: string
  tranche: TrancheVersement
  operateur: Operateur
  nb_lignes: number
  total: number
  beneficiaires: LotBeneficiaire[]
}
