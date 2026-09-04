import type { UserRole } from '@/types'

/**
 * Source UNIQUE du RBAC côté frontend (miroir de App\Support\Permissions).
 * Utilisée par useAuth, le guard du routeur et la directive v-permission.
 */
export type Permission =
  | 'dashboard.consulter'
  | 'personnel.consulter' | 'personnel.gerer'
  | 'om.consulter' | 'om.retranscrire' | 'om.valider' | 'om.numeroter'
  | 'om.viser' | 'om.approuver' | 'om.rejeter'
  | 'paiements.consulter' | 'paiements.generer' | 'paiements.confirmer'
  | 'baremes.consulter' | 'baremes.modifier'
  | 'rapports.consulter'
  | 'audit.consulter'
  | 'utilisateurs.gerer'
  | 'superadmins.gerer'
  | 'parametres.gerer'
  | 'roles.gerer'

export const TOUTES_PERMISSIONS: Permission[] = [
  'dashboard.consulter',
  'personnel.consulter', 'personnel.gerer',
  'om.consulter', 'om.retranscrire', 'om.valider', 'om.numeroter',
  'om.viser', 'om.approuver', 'om.rejeter',
  'paiements.consulter', 'paiements.generer', 'paiements.confirmer',
  'baremes.consulter', 'baremes.modifier',
  'rapports.consulter',
  'audit.consulter',
  'utilisateurs.gerer',
  'superadmins.gerer',
  'parametres.gerer',
  'roles.gerer',
]

/**
 * Libellés lisibles des permissions (source unique, exhaustive : une entrée par
 * clé de `TOUTES_PERMISSIONS`). Utilisée par la fiche profil et l'écran 403.
 */
export const PERMISSION_LABELS: Record<Permission, string> = {
  'dashboard.consulter': 'Tableau de bord',
  'personnel.consulter': 'Consulter le personnel',
  'personnel.gerer': 'Gérer le personnel',
  'om.consulter': 'Consulter les OM',
  'om.retranscrire': 'Retranscrire les missions',
  'om.valider': 'Valider / rejeter les OM',
  'om.numeroter': "Numéroter l'état de paiement",
  'om.viser': "Viser l'état de paiement (DAF)",
  'om.approuver': "Approuver l'état de paiement (DG)",
  'om.rejeter': 'Rejeter un OM',
  'paiements.consulter': 'Consulter les paiements',
  'paiements.generer': 'Générer les fichiers de paiement',
  'paiements.confirmer': 'Confirmer les paiements',
  'baremes.consulter': 'Consulter le barème',
  'baremes.modifier': 'Modifier le barème',
  'rapports.consulter': 'Consulter les rapports',
  'audit.consulter': "Consulter le journal d'audit",
  'utilisateurs.gerer': 'Gérer les utilisateurs',
  'superadmins.gerer': 'Gérer les super administrateurs',
  'parametres.gerer': 'Paramètres système',
  'roles.gerer': 'Gérer la matrice des rôles',
}

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
    'om.consulter', 'om.numeroter', 'om.viser', 'om.rejeter',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
  ],
  // 2e et derniere signature : le DG arrete l'etat, l'OM devient payable.
  DG: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.numeroter', 'om.approuver', 'om.rejeter',
    'paiements.consulter',
    'baremes.consulter',
    'rapports.consulter',
    'audit.consulter',
  ],
  CONTROLEUR: [
    'dashboard.consulter',
    'personnel.consulter',
    'om.consulter', 'om.valider', 'om.numeroter', 'om.rejeter',
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
