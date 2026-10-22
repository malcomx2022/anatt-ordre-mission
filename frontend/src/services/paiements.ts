import { api, toPage, unwrap, saveBlob, filenameFromDisposition, assertValidBlobResponse, type Page } from './http'
import type { LotPaiement, LotPreview, TrancheVersement, Operateur, Versement, Regularisation } from '@/types'

export interface LotSelection {
  mission_id: number
  tranche: TrancheVersement
  operateur: Operateur
}

/** Réponse 202 d'une génération basculée en file Redis (gros lot). */
export interface GenerationAsync {
  async: true
  tracking_id: string
  nb_lignes: number
  message?: string
}

/**
 * État d'une génération asynchrone (cf. GenererLotPaiementJob) :
 * INTROUVABLE tant que le worker n'a pas pris la tâche (ou cache expiré),
 * EN_COURS pendant le traitement, TERMINE (lot_id + fichier) ou ERREUR (message).
 */
export interface StatutJobLot {
  statut: 'EN_COURS' | 'TERMINE' | 'ERREUR' | 'INTROUVABLE'
  lot_id?: number
  fichier?: string
  message?: string
}

/** Lit un corps JSON reçu en Blob (requête faite en `responseType: 'blob'`). */
async function lireJsonBlob<T>(data: unknown): Promise<T> {
  const texte = data instanceof Blob ? await data.text() : typeof data === 'string' ? data : JSON.stringify(data)
  return JSON.parse(texte) as T
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

  /**
   * Génère (ou régénère) le fichier et déclenche son téléchargement (.txt / .xlsx).
   *
   * Petit lot : le fichier arrive directement → téléchargé, renvoie `null`.
   * Gros lot (> seuil `paiement.async_seuil`) : l'API répond 202 avec un JSON
   * `{ async, tracking_id, nb_lignes, message }` — renvoyé tel quel pour que
   * l'écran suive la génération (`statutJob`) puis télécharge (`telechargerLot`).
   */
  async generer(sel: LotSelection): Promise<GenerationAsync | null> {
    const res = await api.post('/api/paiements/lots/generer', sel, { responseType: 'blob' })
    if (res.status === 202) {
      return lireJsonBlob<GenerationAsync>(res.data)
    }
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(
      res.headers['content-disposition'],
      `${sel.operateur}_${sel.tranche}.${sel.operateur === 'MTN' ? 'txt' : 'xlsx'}`,
    )
    saveBlob(res.data as Blob, nom)
    return null
  },

  /** Suivi d'une génération asynchrone (cache serveur `lot_job:{trackingId}`). */
  async statutJob(trackingId: string): Promise<StatutJobLot> {
    const { data } = await api.get(`/api/paiements/lots/job/${encodeURIComponent(trackingId)}`)
    return unwrap<StatutJobLot>(data)
  },

  /** Télécharge le fichier d'un lot par son identifiant (fin de génération asynchrone). */
  async telechargerLot(lotId: number, nomParDefaut = 'lot'): Promise<void> {
    const res = await api.get(`/api/paiements/lots/${lotId}/download`, { responseType: 'blob' })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], nomParDefaut)
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
    await paiementsService.telechargerLot(lot.id, lot.nom_fichier ?? 'lot')
  },

  async statuts(): Promise<Array<{ operateur: string; statut: string; total: number; montant_total: number }>> {
    const { data } = await api.get('/api/paiements/statuts')
    return unwrap(data)
  },

  async historique(params: Record<string, unknown> = {}): Promise<{ page: Page<Versement>; totalMontant: number }> {
    const { data } = await api.get('/api/paiements/historique', { params })
    return { page: toPage<Versement>(data), totalMontant: Number(data.meta?.total_montant ?? 0) }
  },

  /**
   * Trop-perçus à récupérer (reliquats négatifs après amendement), paginés.
   * `totalARecouvrer` est le total GLOBAL du périmètre filtré (pas seulement la page).
   */
  async regularisations(params: Record<string, unknown> = {}): Promise<{ page: Page<Regularisation>; totalARecouvrer: number }> {
    const { data } = await api.get('/api/paiements/regularisations', { params })
    return {
      page: toPage<Regularisation>(data),
      totalARecouvrer: (data as { total_a_recouvrer?: number }).total_a_recouvrer ?? 0,
    }
  },

  /** Marque une régularisation comme recouvrée (retenue appliquée par la RH). */
  async recouvrer(versementId: number): Promise<{ versement_id: number; statut: string }> {
    const { data } = await api.post(`/api/paiements/regularisations/${versementId}/recouvrer`)
    return unwrap(data)
  },

  /** Télécharge le document de retenue sur salaire (PDF) à remettre à la RH. */
  async documentRetenue(versementId: number): Promise<void> {
    const res = await api.get(`/api/paiements/regularisations/${versementId}/document`, { responseType: 'blob' })
    await assertValidBlobResponse(res)
    const nom = filenameFromDisposition(res.headers['content-disposition'], `retenue-${versementId}.pdf`)
    saveBlob(res.data as Blob, nom)
  },
}
