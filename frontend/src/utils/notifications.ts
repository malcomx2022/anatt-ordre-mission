/**
 * Notifications réellement envoyées (cloche + e-mail). Miroir de
 * backend/app/Support/NotificationContenu.php — verrouillé par
 * tests/Feature/NotificationModelesEcranTest.php. Destinataires : comptes actifs
 * détenant la permission indiquée (NotificationService::notifier, SUPER_ADMIN exclu).
 * Source unique des écrans Configuration e-mail et Paramètres › Notifications.
 */
export interface NotificationCircuit {
  id: number
  name: string
  subject: string
  type: string
  destinataires: string
}

export const NOTIFICATIONS_CIRCUIT: NotificationCircuit[] = [
  { id: 1, name: 'État à signer', subject: 'État de paiement {numero} — signature attendue', type: 'etat_attend_signature', destinataires: 'om.valider, puis om.viser, puis om.approuver (étape suivante du circuit)' },
  { id: 2, name: 'État validé', subject: 'État de paiement {numero} validé — fichiers générables', type: 'etat_valide', destinataires: 'paiements.generer' },
  { id: 3, name: 'État rejeté', subject: 'État de paiement {numero} rejeté', type: 'etat_rejete', destinataires: 'om.retranscrire' },
  { id: 4, name: 'OM validé (circuit par OM)', subject: 'OM {reference_semo} validé — paiement préparable', type: 'om_valide', destinataires: 'paiements.generer' },
  { id: 5, name: 'OM rejeté (circuit par OM)', subject: 'OM {reference_semo} rejeté', type: 'om_rejete', destinataires: 'om.retranscrire' },
  { id: 6, name: 'Lot confirmé', subject: 'Lot {tranche} {operateur} confirmé', type: 'lot_confirme', destinataires: 'om.retranscrire ou utilisateurs.gerer' },
  { id: 7, name: 'Trop-perçu créé', subject: 'Trop-perçu sur OM {reference_semo} — régularisation à traiter', type: 'trop_percu_cree', destinataires: 'paiements.confirmer' },
]
