export type OperateurPaiement = 'MOOV' | 'MTN'
export type StatutPaiement = 'EN_ATTENTE' | 'INITIE' | 'SUCCES' | 'ECHEC'

export interface Paiement {
  id: number
  ordre_mission_id: number
  agent_id: number
  operateur: OperateurPaiement
  numero_destinataire: string
  montant: number
  statut: StatutPaiement
  reference_transaction?: string
  fichier_virement?: string
  date_virement?: string
  created_at: string
}

export interface TauxPerDiem {
  id: number
  categorie: string
  destination_type: 'LOCALE' | 'REGIONALE' | 'INTERNATIONALE'
  montant_journalier: number
  annee: number
  actif: boolean
}

export interface LotPaiement {
  id: number
  reference: string
  operateur: OperateurPaiement
  nb_paiements: number
  montant_total: number
  statut: string
  created_at: string
}
