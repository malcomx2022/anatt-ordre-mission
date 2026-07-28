/**
 * État de paiement d'une mission — l'objet que le circuit valide.
 *
 * Un état = 1 mission, N bénéficiaires, colonnes AVANCE et RELIQUAT.
 * Sa validation finale par le Directeur Général ouvre la génération des fichiers
 * de paiement, tranche par tranche.
 */

export type StatutEtat = 'BROUILLON' | 'CONTROLE' | 'VISA_DAF' | 'VALIDE' | 'REJETE'

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
  mission?: EtatMissionResume | null
  /** Numérotation manuelle, suivant le registre de l'agence. */
  numero: string | null
  statut: StatutEtat
  nb_beneficiaires: number
  montant_total: number
  montant_avance: number
  montant_reliquat: number
  /** Mention d'arrêt : total général (avance + reliquat) en lettres. */
  montant_total_lettres: string
  controle?: EtapeSignature | null
  visa_daf?: EtapeSignature | null
  validation_dg?: EtapeSignature | null
  commentaire_rejet?: string | null
  prochaine_etape: ProchaineEtape
  /** true dès que le DG a validé : les fichiers de paiement sont générables. */
  paiement_ouvert: boolean
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
