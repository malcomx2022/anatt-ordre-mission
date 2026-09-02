import { api, unwrap } from './http'
import type { Permission } from '@/utils/permissions'

/** Un profil de la matrice, tel que renvoyé par l'API. */
export interface RoleMatrice {
  cle: string
  libelle: string
  /** true pour un profil système (non supprimable/renommable). */
  systeme: boolean
  verrouille: boolean
  personnalise: boolean
  nb_utilisateurs: number
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
  async remplacer(role: string, permissions: Permission[]): Promise<Permission[]> {
    const { data } = await api.put(`/api/admin/roles/${role}/permissions`, { permissions })
    return unwrap<{ role: string; permissions: Permission[] }>(data).permissions
  },

  /** Rétablit le défaut de configuration pour un profil. */
  async reinitialiser(role: string): Promise<Permission[]> {
    const { data } = await api.delete(`/api/admin/roles/${role}/permissions`)
    return unwrap<{ role: string; permissions: Permission[] }>(data).permissions
  },

  /** Crée un rôle personnalisé (permissions initiales optionnelles). */
  async creer(code: string, libelle: string, permissions: Permission[] = []): Promise<void> {
    await api.post('/api/admin/roles', { code, libelle, permissions })
  },

  /** Renomme un rôle personnalisé (le code reste immuable). */
  async renommer(code: string, libelle: string): Promise<void> {
    await api.put(`/api/admin/roles/${code}`, { libelle })
  },

  /** Supprime un rôle personnalisé (aucun utilisateur ne doit y être rattaché). */
  async supprimer(code: string): Promise<void> {
    await api.delete(`/api/admin/roles/${code}`)
  },
}
