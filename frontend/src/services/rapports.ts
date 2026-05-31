import { api, unwrap, saveBlob, filenameFromDisposition } from './http'
import type { RapportMensuel, RapportDimension, ExportFormat, ExportResultat } from '@/types'

export interface PeriodeFiltres {
  date_debut?: string
  date_fin?: string
}

export const rapportsService = {
  async mensuel(mois: string): Promise<RapportMensuel> {
    const { data } = await api.get('/api/rapports/mensuel', { params: { mois } })
    return unwrap<RapportMensuel>(data)
  },

  async parAgent(params: PeriodeFiltres = {}): Promise<Record<string, unknown>[]> {
    const { data } = await api.get('/api/rapports/agents', { params })
    return unwrap(data)
  },

  async parOperateur(params: PeriodeFiltres = {}): Promise<Record<string, unknown>[]> {
    const { data } = await api.get('/api/rapports/operateurs', { params })
    return unwrap(data)
  },

  async parZone(params: PeriodeFiltres = {}): Promise<Record<string, unknown>[]> {
    const { data } = await api.get('/api/rapports/zones', { params })
    return unwrap(data)
  },

  async parImputation(params: PeriodeFiltres = {}): Promise<Record<string, unknown>[]> {
    const { data } = await api.get('/api/rapports/imputations', { params })
    return unwrap(data)
  },

  async export(params: { dimension: RapportDimension; format: ExportFormat } & PeriodeFiltres): Promise<ExportResultat> {
    const { data } = await api.get('/api/rapports/export', { params })
    return unwrap<ExportResultat>(data)
  },

  /** Télécharge un fichier d'export déjà généré (chemin renvoyé par export()). */
  async telechargerExport(fichier: string): Promise<void> {
    const res = await api.get('/api/rapports/export/download', {
      params: { fichier },
      responseType: 'blob',
    })
    const nom = filenameFromDisposition(res.headers['content-disposition'], fichier.split('/').pop() ?? 'export')
    saveBlob(res.data as Blob, nom)
  },
}
