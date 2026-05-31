import { api, toPage, unwrap, type Page } from './http'
import type { AuditLog } from '@/types'

export interface AuditFilters {
  action?: string
  entite?: string
  user_id?: number
  date_debut?: string
  date_fin?: string
  per_page?: number
  page?: number
}

export const auditService = {
  async list(params: AuditFilters = {}): Promise<Page<AuditLog>> {
    const { data } = await api.get('/api/audit', { params })
    return toPage<AuditLog>(data)
  },

  async get(id: number): Promise<AuditLog> {
    const { data } = await api.get(`/api/audit/${id}`)
    return unwrap<AuditLog>(data)
  },
}
