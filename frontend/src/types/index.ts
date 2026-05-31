export * from './agent'
export * from './mission'
export * from './paiement'
export * from './bareme'

export type UserRole = 'admin' | 'gestionnaire_om' | 'agent_financier' | 'lecteur'

export const ROLE_LABELS: Record<UserRole, string> = {
  admin: 'Administrateur',
  gestionnaire_om: 'Gestionnaire OM',
  agent_financier: 'Agent financier',
  lecteur: 'Lecteur',
}

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  email_verified_at?: string
  created_at?: string
  updated_at?: string
}

export interface ApiErrorResponse {
  message: string
  errors?: Record<string, string[]>
}

export interface LoginResponse {
  token: string
  user: User
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
  entite: string
  entite_id: number | null
  donnees?: Record<string, unknown> | null
  ip_address?: string | null
  utilisateur?: { id: number; name: string; email: string; role: UserRole } | null
  created_at: string
}
