import { api, toPage, unwrap, type Page } from './http'
import type { Mission, MissionFormData, MissionResume } from '@/types'

export interface MissionFilters {
  /** Statut de l'état, ou 'sans_etat' pour les récapitulatifs à établir. */
  etat?: string
  search?: string
  per_page?: number
  page?: number
}

export const missionsService = {
  /** Liste des missions avec agrégats et avancement de leur état. */
  async list(params: MissionFilters = {}): Promise<Page<MissionResume>> {
    const { data } = await api.get('/api/missions', { params })
    return toPage<MissionResume>(data)
  },

  /** Retranscription SEMO : crée la mission + N OM individuels (participants + CAV). */
  async create(payload: MissionFormData): Promise<Mission> {
    const { data } = await api.post('/api/missions', payload)
    return unwrap<Mission>(data)
  },
}
