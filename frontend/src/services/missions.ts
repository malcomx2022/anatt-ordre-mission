import { api, unwrap } from './http'
import type { Mission, MissionFormData } from '@/types'

export const missionsService = {
  /** Retranscription SEMO : crée la mission + N OM individuels (participants + CAV). */
  async create(payload: MissionFormData): Promise<Mission> {
    const { data } = await api.post('/api/missions', payload)
    return unwrap<Mission>(data)
  },
}
