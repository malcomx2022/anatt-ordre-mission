import { api, toPage, unwrap, saveBlob, filenameFromDisposition, assertValidBlobResponse, type Page } from './http'
import type { LotPaiement, LotPreview, TrancheVersement, Operateur, Versement, Regularisation } from '@/types'

export interface LotSelection {
  mission_id: number
  tranche: TrancheVersement
  operateur: Operateur
}

export const paiementsService = {
  async lots(params: Record<string, unknown> = {}): Promise<Page<LotPaiement>> {
    const { data } = await api.get('/api/paiements/lots', { params })
    return toPage<LotPaiement>(data)
  },

  async previsualiser(sel: LotSelection): Promise<LotPreview> {
    const { data } = await api.get('/api/paiements/lots/previsualiser', { params: sel })
    return unwrap<LotPreview>(data)
  },

  /** Génère (ou régénère) le fichier et déclenche son téléchargement (.txt / .xlsx). */
  async generer(sel: LotSelection): Promise<void> {
    const res = await api.post('/api/paiements/lots/generer', sel, { responseType: 'blob' })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(
      res.headers['content-disposition'],
      `${sel.operateur}_${sel.tranche}.${sel.operateur === 'MTN' ? 'txt' : 'xlsx'}`,
    )
    saveBlob(res.data as Blob, nom)
  },

  async confirmer(lotId: number): Promise<LotPaiement> {
    const { data } = await api.post(`/api/paiements/lots/${lotId}/confirmer`)
    return unwrap<LotPaiement>(data)
  },

  async marquerErreur(lotId: number): Promise<LotPaiement> {
    const { data } = await api.post(`/api/paiements/lots/${lotId}/erreur`)
    return unwrap<LotPaiement>(data)
  },

  async telecharger(lot: LotPaiement): Promise<void> {
    const res = await api.get(`/api/paiements/lots/${lot.id}/download`, { responseType: 'blob' })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], lot.nom_fichier ?? 'lot')
    saveBlob(res.data as Blob, nom)
  },

  async statuts(): Promise<Array<{ operateur: string; statut: string; total: number; montant_total: number }>> {
    const { data } = await api.get('/api/paiements/statuts')
    return unwrap(data)
  },

  async historique(params: Record<string, unknown> = {}): Promise<Page<Versement>> {
    const { data } = await api.get('/api/paiements/historique', { params })
    return toPage<Versement>(data)
  },

  /** Trop-perçus à récupérer (reliquats négatifs après amendement). */
  async regularisations(params: Record<string, unknown> = {}): Promise<Regularisation[]> {
    const { data } = await api.get('/api/paiements/regularisations', { params })
    return unwrap<Regularisation[]>(data)
  },

  /** Marque une régularisation comme recouvrée (retenue appliquée par la RH). */
  async recouvrer(versementId: number): Promise<{ versement_id: number; statut: string }> {
    const { data } = await api.post(`/api/paiements/regularisations/${versementId}/recouvrer`)
    return unwrap(data)
  },
}
