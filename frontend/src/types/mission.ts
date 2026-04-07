import type { Agent } from './agent'
import type { TauxPerDiem } from './paiement'

export type StatutMission = 'BROUILLON' | 'SOUMIS' | 'VALIDE_N1' | 'APPROUVE_DG' | 'PAYE' | 'REJETE'

export interface OrdreMission {
  id: number
  numero: string
  agent_id: number
  agent: Agent
  objet: string
  destination: string
  date_depart: string
  date_retour: string
  nb_jours: number
  taux_id: number
  taux: TauxPerDiem
  montant_per_diem: number
  statut: StatutMission
  commentaire_rejet?: string
  valide_par_chef?: number
  valide_par_dg?: number
  created_at: string
  updated_at: string
}

export interface OrdreMissionFormData {
  agent_id: number
  objet: string
  destination: string
  date_depart: string
  date_retour: string
  taux_id: number
}

export interface MissionHistoryEntry {
  id: number
  action: string
  utilisateur: string
  date: string
  commentaire?: string
}
