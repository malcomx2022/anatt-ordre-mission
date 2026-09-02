import { api, unwrap } from './http'
import type { Permission } from '@/utils/permissions'
import type { UserRole } from '@/types'

/** Réponse de GET /api/admin/users/:id/permissions (RBAC niveau 2). */
export interface UserPermissions {
  user_id: number
  role: UserRole
  super_admin: boolean
  /** Permissions données par le profil (avant overrides). */
  base: Permission[]
  /** Accordées en plus du profil. */
  granted: Permission[]
  /** Retirées de ce que le profil donnerait. */
  revoked: Permission[]
  /** Résultat effectif = base ∪ accordées − retirées. */
  effectives: Permission[]
  /** Clés jamais accordables (réservées au super administrateur). */
  non_assignables: Permission[]
}

export const usersService = {
  /** Overrides de permissions d'un utilisateur (base, accords, retraits, effectif). */
  async permissions(userId: number): Promise<UserPermissions> {
    const { data } = await api.get(`/api/admin/users/${userId}/permissions`)
    return unwrap<UserPermissions>(data)
  },

  /** Remplace les overrides d'un utilisateur ; renvoie l'effectif résultant. */
  async updatePermissions(
    userId: number,
    granted: Permission[],
    revoked: Permission[],
  ): Promise<Permission[]> {
    const { data } = await api.put(`/api/admin/users/${userId}/permissions`, { granted, revoked })
    return unwrap<{ effectives: Permission[] }>(data).effectives
  },
}
