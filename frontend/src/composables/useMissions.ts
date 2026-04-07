import { ref } from 'vue'
import { useApi } from './useApi'
import type { OrdreMission, PaginatedResponse } from '@/types'

export function useMissions() {
  const api = useApi()
  const missions = ref<OrdreMission[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchMissions(params?: Record<string, string | number>) {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get<PaginatedResponse<OrdreMission>>('/api/ordres', { params })
      missions.value = data.data
      return data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Erreur lors du chargement'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function fetchMission(id: number) {
    loading.value = true
    try {
      const { data } = await api.get(`/api/ordres/${id}`)
      return data.data as OrdreMission
    } finally {
      loading.value = false
    }
  }

  async function createMission(formData: Record<string, any>) {
    const { data } = await api.post('/api/ordres', formData)
    return data.data as OrdreMission
  }

  async function updateMission(id: number, formData: Record<string, any>) {
    const { data } = await api.put(`/api/ordres/${id}`, formData)
    return data.data as OrdreMission
  }

  async function submitMission(id: number) {
    const { data } = await api.post(`/api/ordres/${id}/soumettre`)
    return data.data as OrdreMission
  }

  async function validateN1(id: number, commentaire?: string) {
    const { data } = await api.post(`/api/ordres/${id}/valider-n1`, { commentaire })
    return data.data as OrdreMission
  }

  async function approveDG(id: number, commentaire?: string) {
    const { data } = await api.post(`/api/ordres/${id}/approuver`, { commentaire })
    return data.data as OrdreMission
  }

  async function rejectMission(id: number, commentaire: string) {
    const { data } = await api.post(`/api/ordres/${id}/rejeter`, { commentaire })
    return data.data as OrdreMission
  }

  return {
    missions,
    loading,
    error,
    fetchMissions,
    fetchMission,
    createMission,
    updateMission,
    submitMission,
    validateN1,
    approveDG,
    rejectMission,
  }
}
