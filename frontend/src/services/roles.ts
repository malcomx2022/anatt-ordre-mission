import { api, unwrap } from './http'
import type { Permission } from '@/utils/permissions'
import type { UserRole } from '@/types'

/** Un profil de la matrice, tel que renvoyé par l'API. */
export interface RoleMatrice {
  cle: UserRole
  libelle: string
  verrouille: boolean
  personnalise: boolean
}

/** Réponse de GET /api/admin/roles : matrice effective + catalogue des clés. */
export interface MatriceRoles {
  profils: RoleMatrice[]
  permissions: Permission[]
  matrice: Record<string, Permission[]>
  non_assignables: Permission[]
}

export const rolesService = {
  /** Matrice effective (défaut config ∪ personnalisations). */
  async matrice(): Promise<MatriceRoles> {
    const { data } = await api.get('/api/admin/roles')
    return unwrap<MatriceRoles>(data)
  },

  /** Remplace intégralement les permissions d'un profil. */
  async remplacer(role: UserRole, permissions: Permission[]): Promise<Permission[]> {
    const { data } = await api.put(`/api/admin/roles/${role}/permissions`, { permissions })
    return unwrap<{ role: string; permissions: Permission[] }>(data).permissions
  },

  /** Rétablit le défaut de configuration pour un profil. */
  async reinitialiser(role: UserRole): Promise<Permission[]> {
    const { data } = await api.delete(`/api/admin/roles/${role}/permissions`)
    return unwrap<{ role: string; permissions: Permission[] }>(data).permissions
  },
}
