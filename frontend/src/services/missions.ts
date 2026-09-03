import { api, toPage, unwrap, type Page } from './http'
import type { Mission, MissionFormData, MissionResume } from '@/types'
import type { ComboOption } from '@/components/shared/combobox'

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

  /** Recherche de missions pour un sélecteur (AppCombobox, mode distant). */
  async search(q: string, perPage = 20): Promise<ComboOption[]> {
    const { items } = await this.list({ search: q, per_page: perPage })
    return items.map((m) => ({
      value: m.id,
      label: `${m.objet} — ${m.destination}`,
    }))
  },

  /** Retranscription SEMO : crée la mission + N OM individuels (participants + CAV). */
  async create(payload: MissionFormData): Promise<Mission> {
    const { data } = await api.post('/api/missions', payload)
    return unwrap<Mission>(data)
  },
}
