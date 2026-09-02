import { api, toPage, unwrap, saveBlob, filenameFromDisposition, type Page } from './http'
import type { OrdreMission, MissionHistoryEntry, AmendementFormData } from '@/types'

export interface OrdreFilters {
  statut?: string
  zone?: string
  agent_id?: number
  operateur?: string
  date_debut?: string
  date_fin?: string
  search?: string
  actifs?: boolean
  per_page?: number
  page?: number
}

export const ordresService = {
  async list(params: OrdreFilters = {}): Promise<Page<OrdreMission>> {
    const { data } = await api.get('/api/ordres', { params })
    return toPage<OrdreMission>(data)
  },

  async get(id: number): Promise<OrdreMission> {
    const { data } = await api.get(`/api/ordres/${id}`)
    return unwrap<OrdreMission>(data)
  },

  /**
   * Validation OM par OM en une étape : IMPORTE → VALIDE (missions sans état).
   * Le circuit à trois signatures se joue sur l'état (etatsService).
   */
  async valider(id: number): Promise<OrdreMission> {
    const { data } = await api.post(`/api/ordres/${id}/valider`)
    return unwrap<OrdreMission>(data)
  },

  async rejeter(id: number, motif: string): Promise<OrdreMission> {
    const { data } = await api.post(`/api/ordres/${id}/rejeter`, { motif })
    return unwrap<OrdreMission>(data)
  },

  /**
   * Amende un OM (rallonge / réduction des jours facturés). Après engagement de
   * l'avance, seul le reliquat bouge (il peut devenir négatif = trop-perçu).
   */
  async amender(id: number, payload: AmendementFormData): Promise<OrdreMission> {
    const { data } = await api.post(`/api/ordres/${id}/amender`, payload)
    return unwrap<OrdreMission>(data)
  },

  async historique(id: number): Promise<MissionHistoryEntry[]> {
    const { data } = await api.get(`/api/ordres/${id}/historique`)
    return unwrap<MissionHistoryEntry[]>(data)
  },

  /** Télécharge le PDF officiel de l'OM (généré côté serveur). */
  async telechargerPdf(id: number): Promise<void> {
    const res = await api.get(`/api/ordres/${id}/pdf/download`, { responseType: 'blob' })
    const nom = filenameFromDisposition(res.headers['content-disposition'], `OM-${id}.pdf`)
    saveBlob(res.data as Blob, nom)
  },

  /**
   * Télécharge le BROUILLON de l'OM : épreuve filigranée, consultable avant
   * toute validation et sans valeur d'autorisation de paiement.
   */
  async telechargerBrouillon(id: number): Promise<void> {
    const res = await api.get(`/api/ordres/${id}/pdf/brouillon`, { responseType: 'blob' })
    const nom = filenameFromDisposition(res.headers['content-disposition'], `OM-${id}-BROUILLON.pdf`)
    saveBlob(res.data as Blob, nom)
  },
}
