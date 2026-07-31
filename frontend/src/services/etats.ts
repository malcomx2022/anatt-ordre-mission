import { api, toPage, unwrap, saveBlob, filenameFromDisposition, assertValidBlobResponse, type Page } from './http'
import type {
  EtatPaiement,
  EtatsMission,
  PosteSignataire,
  SignatairesParametres,
  StatutEtat,
  TrancheEtat,
} from '@/types'

export interface EtatFilters {
  statut?: StatutEtat
  tranche?: TrancheEtat
  search?: string
  per_page?: number
  page?: number
}

export const etatsService = {
  /** File de travail : les états, filtrables par statut et par tranche. */
  async list(params: EtatFilters = {}): Promise<Page<EtatPaiement>> {
    const { data } = await api.get('/api/etats', { params })
    return toPage<EtatPaiement>(data)
  },

  /**
   * États d'une mission, par tranche (avances / reliquats).
   * null tant qu'aucun récapitulatif n'a été produit.
   */
  async parMission(missionId: number): Promise<EtatsMission | null> {
    const { data } = await api.get(`/api/missions/${missionId}/etat`)
    const etats = unwrap<EtatsMission | null>(data)
    // Garde-fou : une tranche sans `statut` n'est PAS un état (backend périmé,
    // réponse vide) — on la ramène à null plutôt qu'un faux BROUILLON à 0 FCFA.
    if (!etats) return null
    return {
      avance: etats.avance?.statut ? etats.avance : null,
      reliquat: etats.reliquat?.statut ? etats.reliquat : null,
    }
  },

  /** Récapitulatif : crée ou rafraîchit l'état d'une tranche en BROUILLON. */
  async recapituler(missionId: number, tranche: TrancheEtat = 'AVANCE'): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/missions/${missionId}/etat/recapituler`, { tranche })
    return unwrap<EtatPaiement>(data)
  },

  /** Numérotation manuelle, exigée avant la validation finale. */
  async definirNumero(etatId: number, numero: string): Promise<EtatPaiement> {
    const { data } = await api.put(`/api/etats/${etatId}/numero`, { numero })
    return unwrap<EtatPaiement>(data)
  },

  /** Étape 1 — contrôle de conformité. */
  async controler(etatId: number): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/etats/${etatId}/controler`)
    return unwrap<EtatPaiement>(data)
  },

  /** Étape 2 — visa du DAF. */
  async viser(etatId: number): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/etats/${etatId}/viser`)
    return unwrap<EtatPaiement>(data)
  },

  /** Étape 3 — validation finale du DG : ouvre les fichiers de paiement. */
  async approuver(etatId: number): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/etats/${etatId}/approuver`)
    return unwrap<EtatPaiement>(data)
  },

  async rejeter(etatId: number, motif: string): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/etats/${etatId}/rejeter`, { motif })
    return unwrap<EtatPaiement>(data)
  },

  /** Brouillon filigrané d'une tranche, sans valeur d'autorisation de paiement. */
  async telechargerBrouillon(missionId: number, tranche: TrancheEtat = 'AVANCE'): Promise<void> {
    const res = await api.get(`/api/missions/${missionId}/etat/brouillon`, {
      params: { tranche },
      responseType: 'blob',
    })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], `ETAT-mission-${missionId}-${tranche}-BROUILLON.pdf`)
    saveBlob(res.data as Blob, nom)
  },

  /** État définitif d'une tranche, signatures apposées. */
  async telecharger(missionId: number, tranche: TrancheEtat = 'AVANCE'): Promise<void> {
    const res = await api.get(`/api/missions/${missionId}/etat/telecharger`, {
      params: { tranche },
      responseType: 'blob',
    })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], `ETAT-mission-${missionId}-${tranche}.pdf`)
    saveBlob(res.data as Blob, nom)
  },

  /**
   * État général consolidé (avances + reliquats) : le document de clôture,
   * servi par le backend une fois les DEUX tranches validées (422 sinon).
   */
  async telechargerFinal(missionId: number): Promise<void> {
    const res = await api.get(`/api/missions/${missionId}/etat/final`, { responseType: 'blob' })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], `ETAT-GENERAL-mission-${missionId}.pdf`)
    saveBlob(res.data as Blob, nom)
  },
}

/** Signatures électroniques configurées dans les Paramètres. */
export const signaturesService = {
  async list(): Promise<SignatairesParametres> {
    const { data } = await api.get('/api/admin/signatures')
    return unwrap<SignatairesParametres>(data)
  },

  async televerser(poste: PosteSignataire, fichier: File): Promise<SignatairesParametres> {
    const form = new FormData()
    form.append('signature', fichier)
    const { data } = await api.post(`/api/admin/signatures/${poste}`, form)
    return unwrap<SignatairesParametres>(data)
  },

  async supprimer(poste: PosteSignataire): Promise<SignatairesParametres> {
    const { data } = await api.delete(`/api/admin/signatures/${poste}`)
    return unwrap<SignatairesParametres>(data)
  },
}
