import { api, toPage, unwrap, saveBlob, filenameFromDisposition, type Page } from './http'
import type {
  EtatPaiement,
  PosteSignataire,
  SignatairesParametres,
  StatutEtat,
} from '@/types'

export interface EtatFilters {
  statut?: StatutEtat
  search?: string
  per_page?: number
  page?: number
}

export const etatsService = {
  /** File de travail : les états, filtrables par statut. */
  async list(params: EtatFilters = {}): Promise<Page<EtatPaiement>> {
    const { data } = await api.get('/api/etats', { params })
    return toPage<EtatPaiement>(data)
  },

  /** État d'une mission (null si le récapitulatif n'a pas encore été produit). */
  async parMission(missionId: number): Promise<EtatPaiement | null> {
    const { data } = await api.get(`/api/missions/${missionId}/etat`)
    const etat = unwrap<EtatPaiement | null>(data)
    // Garde-fou : un objet sans `statut` n'est PAS un état (réponse vide, ou
    // backend périmé qui fabriquerait un état fantôme). On le ramène à null pour
    // afficher « Établir le récapitulatif » plutôt qu'un faux BROUILLON à 0 FCFA.
    return etat && etat.statut ? etat : null
  },

  /** Récapitulatif : crée ou rafraîchit l'état en BROUILLON. */
  async recapituler(missionId: number): Promise<EtatPaiement> {
    const { data } = await api.post(`/api/missions/${missionId}/etat/recapituler`)
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

  /** Brouillon filigrané, sans valeur d'autorisation de paiement. */
  async telechargerBrouillon(missionId: number): Promise<void> {
    const res = await api.get(`/api/missions/${missionId}/etat/brouillon`, { responseType: 'blob' })
    const nom = filenameFromDisposition(res.headers['content-disposition'], `ETAT-mission-${missionId}-BROUILLON.pdf`)
    saveBlob(res.data as Blob, nom)
  },

  /** État définitif, signatures apposées. */
  async telecharger(missionId: number): Promise<void> {
    const res = await api.get(`/api/missions/${missionId}/etat/telecharger`, { responseType: 'blob' })
    const nom = filenameFromDisposition(res.headers['content-disposition'], `ETAT-mission-${missionId}.pdf`)
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
