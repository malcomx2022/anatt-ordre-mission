import type { Permission } from '@/utils/permissions'

/**
 * Définition du menu latéral.
 *
 * Règle : chaque entrée porte la MÊME permission que le `meta.permission` de sa
 * route (cf. router/index.ts). Un lien affiché doit toujours mener à un écran
 * réellement accessible — sans quoi l'utilisateur atterrit sur /403 ou sur un
 * écran vide accompagné d'un 403 de l'API. La cohérence est verrouillée par
 * `__tests__/navigation.spec.ts`.
 *
 * Extrait du composant pour rester testable : `<script setup>` n'exporte rien.
 */
export interface NavItem {
  label: string
  icon: string
  to: string
  badge?: number
  permission?: Permission
  /** Sous-écrans, dépliés quand la section parente est active. */
  children?: NavItem[]
}

export interface NavGroup {
  title: string
  items: NavItem[]
}

export const NAVIGATION: NavGroup[] = [
  {
    title: 'Principal',
    items: [
      { label: 'Tableau de bord', icon: 'grid', to: '/', permission: 'dashboard.consulter' },
    ],
  },
  {
    title: 'Gestion',
    items: [
      {
        label: 'Personnel', icon: 'users', to: '/agents', permission: 'personnel.consulter',
        children: [
          { label: 'Nouvel agent', icon: '', to: '/agents/nouveau', permission: 'personnel.gerer' },
          { label: 'Import CSV', icon: '', to: '/agents/import', permission: 'personnel.gerer' },
        ],
      },
      { label: 'Missions', icon: 'briefcase', to: '/missions', permission: 'om.consulter' },
      {
        label: 'Ordres de Mission', icon: 'file-text', to: '/ordres', permission: 'om.consulter',
        children: [
          { label: 'Retranscrire', icon: '', to: '/ordres/nouveau', permission: 'om.retranscrire' },
        ],
      },
      { label: 'Etats de paiement', icon: 'check-square', to: '/etats', permission: 'om.consulter' },
    ],
  },
  {
    title: 'Finances',
    items: [
      {
        label: 'Paiements', icon: 'credit-card', to: '/paiements', permission: 'paiements.consulter',
        children: [
          { label: 'Generer MTN', icon: '', to: '/paiements/mtn', permission: 'paiements.generer' },
          { label: 'Generer Moov', icon: '', to: '/paiements/moov', permission: 'paiements.generer' },
          { label: 'Statuts', icon: '', to: '/paiements/statuts', permission: 'paiements.consulter' },
          { label: 'Regularisations', icon: '', to: '/paiements/regularisations', permission: 'paiements.consulter' },
          { label: 'Historique', icon: '', to: '/paiements/historique', permission: 'paiements.consulter' },
          { label: 'Reconciliation', icon: '', to: '/paiements/reconciliation', permission: 'paiements.consulter' },
        ],
      },
      {
        label: 'Baremes', icon: 'calculator', to: '/taux', permission: 'baremes.consulter',
        children: [
          { label: 'Configurer', icon: '', to: '/taux/configurer', permission: 'baremes.modifier' },
        ],
      },
    ],
  },
  {
    title: 'Analyses',
    items: [
      {
        label: 'Rapports', icon: 'bar-chart-2', to: '/rapports', permission: 'rapports.consulter',
        children: [
          { label: 'Mensuel', icon: '', to: '/rapports/mensuel', permission: 'rapports.consulter' },
          { label: 'Par agent', icon: '', to: '/rapports/agents', permission: 'rapports.consulter' },
          { label: 'Trop-percus', icon: '', to: '/rapports/trop-percus', permission: 'rapports.consulter' },
          { label: 'Export', icon: '', to: '/rapports/export', permission: 'rapports.consulter' },
        ],
      },
      { label: 'Audit', icon: 'clock', to: '/audit', permission: 'audit.consulter' },
    ],
  },
  {
    title: 'Systeme',
    items: [
      {
        label: 'Utilisateurs', icon: 'shield', to: '/admin/utilisateurs', permission: 'utilisateurs.gerer',
        children: [
          { label: 'Nouvel utilisateur', icon: '', to: '/admin/utilisateurs/nouveau', permission: 'utilisateurs.gerer' },
          { label: 'Matrice des roles', icon: '', to: '/admin/roles', permission: 'utilisateurs.gerer' },
        ],
      },
      { label: 'Signatures', icon: 'edit-3', to: '/admin/signatures', permission: 'parametres.gerer' },
      {
        label: 'Parametres', icon: 'settings', to: '/admin/parametres', permission: 'parametres.gerer',
        children: [
          { label: 'Messagerie', icon: '', to: '/admin/email', permission: 'parametres.gerer' },
          { label: 'Sauvegardes', icon: '', to: '/admin/sauvegardes', permission: 'parametres.gerer' },
        ],
      },
    ],
  },
]

/** Toutes les entrées, parents et enfants confondus. */
export function toutesLesEntrees(groupes: NavGroup[] = NAVIGATION): NavItem[] {
  return groupes.flatMap((g) => g.items.flatMap((i) => [i, ...(i.children ?? [])]))
}
