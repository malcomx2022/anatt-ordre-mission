import type { Categorie } from './agent'
import type { ZoneCode, TypePriseEnCharge } from './mission'

export interface Bareme {
  id: number
  categorie: Categorie
  zone_code: ZoneCode
  type_prise_en_charge: TypePriseEnCharge
  taux_journalier: number
  created_at?: string
  updated_at?: string
}

export interface RapportLigne {
  [key: string]: string | number | null
}

export interface RapportMensuel {
  mois: string
  periode: { debut: string; fin: string }
  synthese: { nb_versements: number; montant_total: number }
  par_operateur: Array<{ operateur: string; nb_versements: number; montant_total: number }>
  par_tranche: Array<{ tranche: string; nb: number; montant: number }>
  par_statut: Array<{ statut: string; nb: number; montant: number }>
}

/** État des trop-perçus (régularisations) : à recouvrer vs recouvrés, montants absolus. */
export interface RapportTropPercus {
  a_recouvrer: { nb: number; montant: number }
  recouvre: { nb: number; montant: number }
  total: { nb: number; montant: number }
  par_operateur: Array<{ operateur: string; statut: string; nb: number; montant: number }>
}

export type RapportDimension = 'agent' | 'operateur' | 'zone' | 'imputation'
export type ExportFormat = 'xlsx' | 'pdf'

export interface ExportResultat {
  fichier: string
  url: string
  dimension: string
  format: string
  nb_lignes: number
}
