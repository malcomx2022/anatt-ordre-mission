export * from './agent'
export * from './mission'
export * from './paiement'

export type UserRole = 'AGENT' | 'CHEF_SERVICE' | 'DRH' | 'DIRECTEUR' | 'ADMIN'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  agent_id?: number
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

export interface AuditLog {
  id: number
  action: string
  utilisateur: string
  user_id: number
  cible: string
  details?: string
  ip_address?: string
  created_at: string
}
