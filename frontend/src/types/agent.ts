export type Categorie = 'ORDRE_1' | 'ORDRE_2' | 'ORDRE_3'
export type Operateur = 'MTN' | 'MOOV'

export interface Agent {
  id: number
  nom: string
  prenoms: string
  nom_complet?: string
  matricule: string
  ifu?: string | null
  grade?: string | null
  indice?: number | null
  salaire_brut?: number | null
  categorie: Categorie
  telephone: string
  operateur: Operateur
  actif: boolean
  created_at?: string
  updated_at?: string
}

export interface AgentFormData {
  nom: string
  prenoms: string
  matricule: string
  ifu?: string | null
  grade?: string | null
  indice?: number | null
  salaire_brut?: number | null
  categorie?: Categorie
  telephone: string
  operateur: Operateur
  actif?: boolean
}

/** Rapport d'import Excel/CSV : une entrée par ligne en erreur. */
export interface ImportErreur {
  ligne: number
  erreurs: string[]
  donnees: Record<string, unknown>
}

export interface ImportResultat {
  importes: number
  ignores: number
  rollback: boolean
  erreurs: ImportErreur[]
}
