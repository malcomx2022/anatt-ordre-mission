import type { Operateur } from './agent'
import type { Mission } from './mission'

export type { Operateur }
export type TrancheVersement = 'AVANCE' | 'RELIQUAT'
// A_RECOUVRER / RECOUVRE : régularisation d'un trop-perçu (reliquat négatif après
// amendement) — retenue sur salaire, jamais un fichier de paiement Mobile Money.
export type StatutVersement = 'EN_ATTENTE' | 'GENERE' | 'CONFIRME' | 'ANNULE' | 'A_RECOUVRER' | 'RECOUVRE'
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
  sequence?: number            // 1 = lot initial, 2.. = lot complémentaire (après confirmation)
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
  /** Part d'avance (%) des OM de la mission (null : exonérée). */
  part_avance?: number | null
  nb_lignes: number
  total: number
  beneficiaires: LotBeneficiaire[]
}

/**
 * Trop-perçu à récupérer : un reliquat devenu négatif après amendement.
 * `montant_a_recouvrer` est la valeur absolue du reliquat négatif.
 */
export interface Regularisation {
  versement_id: number
  /** OM concerné — pour ouvrir sa fiche et l'amender. */
  ordre_mission_id: number
  reference_semo: string
  beneficiaire: string
  mission: string
  operateur: Operateur
  telephone: string
  montant_a_recouvrer: number
  statut: 'A_RECOUVRER' | 'RECOUVRE'
  date_regularisation?: string | null
}
