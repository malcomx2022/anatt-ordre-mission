export * from './agent'
export * from './mission'
export * from './paiement'
export * from './bareme'

export type UserRole =
  | 'SUPER_ADMIN' | 'ADMIN' | 'GESTIONNAIRE_OM' | 'AGENT_FINANCIER' | 'CONTROLEUR' | 'LECTEUR'

export const ROLE_LABELS: Record<UserRole, string> = {
  SUPER_ADMIN: 'Super administrateur',
  ADMIN: 'Administrateur',
  GESTIONNAIRE_OM: 'Gestionnaire OM',
  AGENT_FINANCIER: 'Agent financier',
  CONTROLEUR: 'Contrôleur',
  LECTEUR: 'Lecteur',
}

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  role_libelle?: string
  telephone?: string | null
  actif?: boolean
  must_change_password?: boolean
  derniere_connexion?: string | null
  cree_par?: number | null
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
  permissions: string[]
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
