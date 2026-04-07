import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { OrdreMission, PaginationMeta } from '@/types'

export const useMissionsStore = defineStore('missions', () => {
  const missions = ref<OrdreMission[]>([])
  const currentMission = ref<OrdreMission | null>(null)
  const meta = ref<PaginationMeta | null>(null)
  const filters = ref({
    statut: '',
    agent_id: '',
    search: '',
    page: 1,
  })

  function setMissions(data: OrdreMission[], pagination: PaginationMeta) {
    missions.value = data
    meta.value = pagination
  }

  function setCurrent(mission: OrdreMission | null) {
    currentMission.value = mission
  }

  function resetFilters() {
    filters.value = { statut: '', agent_id: '', search: '', page: 1 }
  }

  return { missions, currentMission, meta, filters, setMissions, setCurrent, resetFilters }
})
