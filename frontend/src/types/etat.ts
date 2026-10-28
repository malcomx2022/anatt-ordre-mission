/**
 * État de paiement d'une mission — l'objet que le circuit valide.
 *
 * Un état = 1 mission × 1 TRANCHE (avances ou reliquats), N bénéficiaires.
 * Chaque état suit le circuit complet (contrôle → DAF → DG) et sa validation
 * finale ouvre la génération des fichiers de paiement DE SA TRANCHE. L'état
 * des reliquats n'est établi qu'une fois l'état des avances validé.
 */

export type StatutEtat = 'BROUILLON' | 'CONTROLE' | 'VISA_DAF' | 'VALIDE' | 'REJETE'

/** Tranche portée par un état : avances (partAvance %) ou reliquats (100 - partAvance %). */
export type TrancheEtat = 'AVANCE' | 'RELIQUAT'

/** Étape à franchir, telle que renvoyée par le serveur. */
export type ProchaineEtape = 'CONTROLE' | 'VISA_DAF' | 'VALIDATION_DG' | null

/** Étape franchie : instantané du signataire au moment de la signature. */
export interface EtapeSignature {
  nom: string
  fonction?: string | null
  date?: string | null
  /** Code dérivé du scellé d'intégrité, imprimé sur le document. */
  code?: string | null
}

export interface EtatMissionResume {
  id: number
  objet: string
  destination: string
  date_depart?: string | null
  date_retour?: string | null
}

export interface EtatPaiement {
  id: number
  mission_id: number
  tranche: TrancheEtat
  mission?: EtatMissionResume | null
  /** Numérotation manuelle, suivant le registre de l'agence (un numéro par état). */
  numero: string | null
  statut: StatutEtat
  nb_beneficiaires: number
  /** Total DE LA TRANCHE (Σ avances pour l'état des avances, Σ reliquats sinon). */
  montant_total: number
  montant_avance: number
  montant_reliquat: number
  /** Mention d'arrêt : total de la tranche en lettres. */
  montant_total_lettres: string
  /** Part d'avance (%) des OM de la mission, figée à la retranscription (null : exonérée). */
  part_avance?: number | null
  controle?: EtapeSignature | null
  visa_daf?: EtapeSignature | null
  validation_dg?: EtapeSignature | null
  commentaire_rejet?: string | null
  prochaine_etape: ProchaineEtape
  /** true dès que le DG a validé : les fichiers de la tranche sont générables. */
  paiement_ouvert: boolean
}

/** Les deux documents d'une mission, tels que renvoyés par GET /missions/{id}/etat. */
export interface EtatsMission {
  avance: EtatPaiement | null
  reliquat: EtatPaiement | null
}

/** Signataire configuré dans les Paramètres (titulaire en poste). */
export interface SignataireParametre {
  nom: string
  prenoms: string
  nom_complet: string
  fonction: string
  signature_enregistree: boolean
}

export type PosteSignataire = 'controleur' | 'daf' | 'dg'

export type SignatairesParametres = Record<PosteSignataire, SignataireParametre>
