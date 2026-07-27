import type { UserRole } from '@/types'

/**
 * Source UNIQUE du RBAC côté frontend (miroir de App\Support\Permissions).
 * Utilisée par useAuth, le guard du routeur et la directive v-permission.
 */
export type Permission =
  | 'dashboard.consulter'
  | 'personnel.consulter' | 'personnel.gerer'
  | 'om.consulter' | 'om.retranscrire' | 'om.valider' | 'om.viser' | 'om.approuver' | 'om.rejeter'
  | 'paiements.consulter' | 'paiements.generer' | 'paiements.confirmer'
  | 'baremes.consulter' | 'baremes.modifier'
  | 'rapports.consulter'
  | 'audit.consulter'
  | 'utilisateurs.gerer'
  | 'superadmins.gerer'
  | 'parametres.gerer'

export const TOUTES_PERMISSIONS: Permission[] = [
  'dashboard.consulter',
  'personnel.consulter', 'personnel.gerer',
  'om.consulter', 'om.retranscrire', 'om.valider', 'om.viser', 'om.approuver', 'om.rejeter',
  'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
  'baremes.consulter', 'baremes.modifier',
  'rapports.consulter',
  'audit.consulter',
  'utilisateurs.gerer',
  'superadmins.gerer',
  'parametres.gerer',
]

export const ROLE_PERMISSIONS: Record<UserRole, Permission[]> = {
  SUPER_ADMIN: TOUTES_PERMISSIONS,
  ADMIN: [
    'dashboard.consulter',
    'personnel.consulter', 'personnel.gerer',
    'om.consulter',
    'paiements.consulter',
    'baremes.consulter', 'baremes.modifier',
    'rapports.consulter',
    'audit.consulter',
    'utilisateurs.gerer',
  ],
  GESTIONNAIRE_OM: [
    'dashboard.consulter',
    'personnel.consulter', 'personnel.gerer',
    'om.consulter', 'om.retranscrire',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
  ],
  AGENT_FINANCIER: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter',
    'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
    'baremes.consulter',
    'rapports.consulter',
  ],
  // 1re signature du circuit double : « Vu » du DAF.
  DAF: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.viser', 'om.rejeter',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
  ],
  // 2e et derniere signature : le DG arrete l'etat, l'OM devient payable.
  DG: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.approuver', 'om.rejeter',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
  ],
  CONTROLEUR: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.valider', 'om.rejeter',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
  ],
  LECTEUR: [
    'dashboard.consulter',
    'om.consulter',
    'rapports.consulter',
  ],
}

export function permissionsForRole(role?: UserRole | string | null): Permission[] {
  if (!role) return []
  return ROLE_PERMISSIONS[role as UserRole] ?? []
}
