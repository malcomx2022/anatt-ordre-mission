export type Categorie = 'INDICE_SUP_800' | 'INDICE_400_799' | 'INDICE_INF_400'
export type Operateur = 'MTN' | 'MOOV'

/** Ordre d'affichage : de la catégorie la plus élevée à la plus basse. */
export const CATEGORIES: Categorie[] = ['INDICE_SUP_800', 'INDICE_400_799', 'INDICE_INF_400']

/**
 * Miroir de App\Support\CategorieIndice::LIBELLES (backend).
 * Le code porte le seuil, le libellé le rend lisible : jamais afficher le code brut.
 */
export const CATEGORIE_LABELS: Record<Categorie, string> = {
  INDICE_SUP_800: 'Indice ≥ 800',
  INDICE_400_799: 'Indice 400-799',
  INDICE_INF_400: 'Indice < 400',
}

/** Libellé d'une catégorie, tolérant aux valeurs héritées (journal d'audit). */
export function categorieLabel(code?: Categorie | string | null): string {
  if (!code) return '—'
  return CATEGORIE_LABELS[code as Categorie] ?? code
}

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
