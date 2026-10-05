import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'
import type { Team } from '@/types'
import { errorMessage } from '@/utils/tasks'
export const useTeamStore = defineStore('teams', () => {
  const teams = ref<Team[]>([]), currentTeam = ref<Team | null>(null)
  const loading = ref(false), detailLoading = ref(false), error = ref(''), detailError = ref('')
  let listRequest = 0, detailRequest = 0
  async function fetchTeams() {
    const request = ++listRequest
    loading.value = true; error.value = ''
    try { const res = await api.get('/teams'); if (request === listRequest) teams.value = res.data.data || res.data }
    catch (err) { if (request === listRequest) error.value = errorMessage(err) }
    finally { if (request === listRequest) loading.value = false }
  }
  async function fetchTeam(id: number) {
    const request = ++detailRequest
    detailLoading.value = true; detailError.value = ''; currentTeam.value = null
    try { const res = await api.get('/teams/' + id); if (request !== detailRequest) return null; currentTeam.value = res.data.data || res.data; return currentTeam.value }
    catch (err) { if (request === detailRequest) detailError.value = errorMessage(err); return null }
    finally { if (request === detailRequest) detailLoading.value = false }
  }
  function reset() { ++listRequest; ++detailRequest; teams.value = []; currentTeam.value = null; loading.value = false; detailLoading.value = false; error.value = ''; detailError.value = '' }
  return { teams, currentTeam, loading, detailLoading, error, detailError, fetchTeams, fetchTeam, reset }
})
