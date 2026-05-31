import { api, unwrap } from './http'
import type { Bareme } from '@/types'

export interface BaremeFilters {
  categorie?: string
  zone_code?: string
  type_prise_en_charge?: string
}

export interface BaremeFormData {
  categorie: string
  zone_code: string
  type_prise_en_charge: string
  taux_journalier: number
}

export const baremesService = {
  async list(params: BaremeFilters = {}): Promise<Bareme[]> {
    const { data } = await api.get('/api/baremes', { params })
    return unwrap<Bareme[]>(data)
  },

  async get(id: number): Promise<Bareme> {
    const { data } = await api.get(`/api/baremes/${id}`)
    return unwrap<Bareme>(data)
  },

  async create(payload: BaremeFormData): Promise<Bareme> {
    const { data } = await api.post('/api/baremes', payload)
    return unwrap<Bareme>(data)
  },

  async update(id: number, payload: { taux_journalier: number }): Promise<Bareme> {
    const { data } = await api.put(`/api/baremes/${id}`, payload)
    return unwrap<Bareme>(data)
  },

  async remove(id: number): Promise<void> {
    await api.delete(`/api/baremes/${id}`)
  },
}
