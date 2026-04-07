export interface Agent {
  id: number
  matricule: string
  nom: string
  prenom: string
  email: string
  telephone: string
  numero_moov?: string
  numero_mtn?: string
  service: string
  categorie: 'A1' | 'A2' | 'B' | 'C' | 'D'
  actif: boolean
  created_at?: string
  updated_at?: string
}

export interface AgentFormData {
  matricule: string
  nom: string
  prenom: string
  email: string
  telephone: string
  numero_moov?: string
  numero_mtn?: string
  service: string
  categorie: 'A1' | 'A2' | 'B' | 'C' | 'D'
}
