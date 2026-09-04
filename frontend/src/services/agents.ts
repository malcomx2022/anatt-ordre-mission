import { api, toPage, unwrap, type Page } from './http'
import type { Agent, AgentFormData, ImportResultat } from '@/types'

export interface AgentFilters {
  search?: string
  categorie?: string
  operateur?: string
  actif?: boolean | string
  per_page?: number
  page?: number
}

export interface AgentStats {
  total: number
  actifs: number
  inactifs: number
  mtn: number
  moov: number
}

export const agentsService = {
  async list(params: AgentFilters = {}): Promise<Page<Agent>> {
    const { data } = await api.get('/api/agents', { params })
    return toPage<Agent>(data)
  },

  /** Compteurs GLOBAUX (tout le jeu filtré) pour les cartes de l'écran Personnel. */
  async stats(params: AgentFilters = {}): Promise<AgentStats> {
    const { data } = await api.get('/api/agents/stats', { params })
    return unwrap<AgentStats>(data)
  },

  async get(id: number): Promise<Agent> {
    const { data } = await api.get(`/api/agents/${id}`)
    return unwrap<Agent>(data)
  },

  /** Autocomplétion du wizard OM (nom / matricule / IFU). */
  async search(q: string): Promise<Agent[]> {
    const { data } = await api.get('/api/agents/recherche', { params: { q } })
    return unwrap<Agent[]>(data)
  },

  async create(payload: AgentFormData): Promise<Agent> {
    const { data } = await api.post('/api/agents', payload)
    return unwrap<Agent>(data)
  },

  async update(id: number, payload: Partial<AgentFormData>): Promise<Agent> {
    const { data } = await api.put(`/api/agents/${id}`, payload)
    return unwrap<Agent>(data)
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/api/agents/${id}`)
  },

  /** Import Excel/CSV. En cas d'erreurs, l'API répond 422 (le rapport est dans error.response.data.errors). */
  async import(file: File): Promise<ImportResultat> {
    const fd = new FormData()
    fd.append('fichier', file)
    const { data } = await api.post('/api/agents/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return unwrap<ImportResultat>(data)
  },
}
