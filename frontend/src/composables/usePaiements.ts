import { ref } from 'vue'
import { useApi } from './useApi'
import type { Paiement, PaginatedResponse } from '@/types'

export function usePaiements() {
  const api = useApi()
  const paiements = ref<Paiement[]>([])
  const loading = ref(false)

  async function fetchPaiements(params?: Record<string, string | number>) {
    loading.value = true
    try {
      const { data } = await api.get<PaginatedResponse<Paiement>>('/api/paiements', { params })
      paiements.value = data.data
      return data
    } finally {
      loading.value = false
    }
  }

  async function generateMoov(ordreIds: number[]) {
    const { data } = await api.post('/api/paiements/generer/moov', { ordre_ids: ordreIds })
    return data
  }

  async function generateMtn(ordreIds: number[]) {
    const { data } = await api.post('/api/paiements/generer/mtn', { ordre_ids: ordreIds })
    return data
  }

  async function reconcilier(file: File) {
    const formData = new FormData()
    formData.append('fichier', file)
    const { data } = await api.post('/api/paiements/reconcilier', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  }

  return { paiements, loading, fetchPaiements, generateMoov, generateMtn, reconcilier }
}
