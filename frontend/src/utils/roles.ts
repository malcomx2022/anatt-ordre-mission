import { ROLE_LABELS } from '@/types'
import type { UserRole } from '@/types'

/**
 * Catalogue des profils proposés dans l'administration.
 *
 * Source UNIQUE : les écrans de création et de gestion des utilisateurs
 * recopiaient chacun leur propre liste de profils, et les deux avaient oublié
 * DAF et DG — les deux signataires du circuit étaient donc incréables depuis
 * l'interface alors que l'API les accepte. La liste est désormais dérivée du
 * type UserRole, et un test refuse tout profil manquant.
 *
 * Les classes Tailwind sont écrites en toutes lettres : une classe construite
 * par interpolation (`bg-${couleur}-100`) n'est pas vue par le compilateur et
 * n'est jamais générée.
 */
export interface ProfilCatalogue {
  value: UserRole
  label: string
  /** Ce que le profil fait dans le circuit, en une phrase. */
  desc: string
  /** Domaines couverts, affichés en pastilles. */
  perms: string[]
  /** Pastille de rôle : fond + texte. */
  badge: string
  /** Encadré de sélection dans le formulaire de création. */
  puce: string
}

export const PROFILS: ProfilCatalogue[] = [
  {
    value: 'SUPER_ADMIN',
    label: ROLE_LABELS.SUPER_ADMIN,
    desc: 'Accès total ; gère les super admins et les paramètres système',
    perms: ['Tout'],
    badge: 'bg-red-100 text-red-700',
    puce: 'bg-red-100 text-red-600',
  },
  {
    value: 'ADMIN',
    label: ROLE_LABELS.ADMIN,
    desc: 'Utilisateurs, référentiels, barème ; supervise sans exécuter les paiements',
    perms: ['Personnel', 'Barème', 'Utilisateurs', 'Audit'],
    badge: 'bg-purple-100 text-purple-700',
    puce: 'bg-purple-100 text-purple-600',
  },
  {
    value: 'GESTIONNAIRE_OM',
    label: ROLE_LABELS.GESTIONNAIRE_OM,
    desc: 'Retranscrit les missions SEMO, gère les OM et les agents',
    perms: ['Personnel', 'Missions/OM', 'Rapports'],
    badge: 'bg-green-100 text-green-700',
    puce: 'bg-green-100 text-green-600',
  },
  {
    value: 'AGENT_FINANCIER',
    label: ROLE_LABELS.AGENT_FINANCIER,
    desc: 'Génère les fichiers MTN/Moov et confirme les paiements déposés',
    perms: ['Paiements', 'Rapports'],
    badge: 'bg-amber-100 text-amber-700',
    puce: 'bg-amber-100 text-amber-600',
  },
  {
    value: 'CONTROLEUR',
    label: ROLE_LABELS.CONTROLEUR,
    desc: 'Étape 1 du circuit : contrôle de conformité de l’état de paiement',
    perms: ['Contrôle', 'Audit', 'Rapports'],
    badge: 'bg-blue-100 text-blue-700',
    puce: 'bg-blue-100 text-blue-600',
  },
  {
    value: 'DAF',
    label: ROLE_LABELS.DAF,
    desc: 'Étape 2 du circuit : appose le visa « Vu » sur l’état de paiement',
    perms: ['Visa DAF', 'Audit', 'Rapports'],
    badge: 'bg-indigo-100 text-indigo-700',
    puce: 'bg-indigo-100 text-indigo-600',
  },
  {
    value: 'DG',
    label: ROLE_LABELS.DG,
    desc: 'Étape 3 et dernière : arrête l’état, ce qui ouvre le paiement',
    perms: ['Validation finale', 'Audit', 'Rapports'],
    badge: 'bg-teal-100 text-teal-700',
    puce: 'bg-teal-100 text-teal-600',
  },
  {
    value: 'LECTEUR',
    label: ROLE_LABELS.LECTEUR,
    desc: 'Consultation seule (tableau de bord, OM, rapports)',
    perms: ['Dashboard', 'OM', 'Rapports'],
    badge: 'bg-gray-100 text-gray-600',
    puce: 'bg-gray-100 text-gray-600',
  },
]

/** Pastille d'un profil, avec un repli neutre pour un rôle inconnu. */
export function badgeDeRole(role: string): string {
  return PROFILS.find((p) => p.value === role)?.badge ?? 'bg-gray-100 text-gray-600'
}
