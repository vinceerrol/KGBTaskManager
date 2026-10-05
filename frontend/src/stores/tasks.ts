import { defineStore } from 'pinia'
import { useTeamStore } from '@/stores/teams'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { Task, DashboardStats, TaskFilterOptions, TaskAttachment } from '@/types'
import { toast } from '@/stores/toast'
import { dateKey, isOverdue, parseDate, errorMessage, fieldErrors, assigneeLabel } from '@/utils/tasks'

export const defaultFilters = (): TaskFilterOptions => ({ status: 'all', team_id: '', assigned_to: '', priority: '', date_filter: 'all', search: '' })
export const useTaskStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const myTasks = ref<Task[]>([])
  const currentTask = ref<Task | null>(null)
  const stats = ref<DashboardStats | null>(null)
  const loading = ref(false), myLoading = ref(false), statsLoading = ref(false)
  const error = ref(''), myError = ref(''), statsError = ref(''), detailError = ref('')
  const mutationError = ref(''), validationErrors = ref<Record<string, string>>({})
  const pending = ref<Record<number, boolean>>({})
  const filters = ref<TaskFilterOptions>(defaultFilters())
  let tasksRequest = 0, myRequest = 0, statsRequest = 0, detailRequest = 0, session = 0
  const filteredTasks = computed(() => tasks.value.filter(task => {
    const f = filters.value
    if (f.status && f.status !== 'all' && (f.status === 'OVERDUE' ? !isOverdue(task) : task.status !== f.status)) return false
    if (f.team_id && task.team_id !== Number(f.team_id)) return false
    if (f.assigned_to && task.assigned_to !== Number(f.assigned_to) && !task.assignees?.some(user => user.id === Number(f.assigned_to))) return false
    if (f.priority && task.priority !== f.priority) return false
    const query = f.search?.trim().toLowerCase()
    if (query && ![task.title, task.description, assigneeLabel(task), task.team?.name].some(value => value?.toLowerCase().includes(query))) return false
    if (f.date_filter && f.date_filter !== 'all') {
      const today = dateKey()
      if (f.date_filter === 'today' && ![task.deadline, task.scheduled_at, task.created_at].some(value => value && dateKey(value) === today)) return false
      if (f.date_filter === 'tomorrow') {
        const tomorrow = dateKey(new Date(Date.now() + 86400000))
        if (![task.deadline, task.scheduled_at].some(value => value && dateKey(value) === tomorrow)) return false
      }
      if (f.date_filter === 'week') {
        const start = parseDate(today + 'T00:00:00')
        // Calendar weeks run Monday through Sunday in the workspace timezone.
        const localWeekday = new Date(today + 'T12:00:00+08:00').getUTCDay() || 7
        start.setUTCDate(start.getUTCDate() - localWeekday + 1)
        const end = new Date(start.getTime() + 7 * 86400000)
        if (!task.deadline || parseDate(task.deadline) < start || parseDate(task.deadline) >= end) return false
      }
    }
    return true
  }))
  async function fetchTasks() {
    const request = ++tasksRequest
    loading.value = true; error.value = ''
    try {
      const res = await api.get('/tasks')
      if (request === tasksRequest) tasks.value = res.data.data || res.data
    } catch (err) { if (request === tasksRequest) error.value = errorMessage(err) }
    finally { if (request === tasksRequest) loading.value = false }
  }
  async function fetchMyTasks() {
    const request = ++myRequest
    myLoading.value = true; myError.value = ''
    try {
      const res = await api.get('/tasks/my-tasks')
      if (request === myRequest) myTasks.value = res.data.data || res.data
    } catch (err) { if (request === myRequest) myError.value = errorMessage(err) }
    finally { if (request === myRequest) myLoading.value = false }
  }
  async function fetchDashboardStats() {
    const request = ++statsRequest
    statsLoading.value = true; statsError.value = ''
    try {
      const res = await api.get('/dashboard')
      if (request === statsRequest) stats.value = res.data
    } catch (err) { if (request === statsRequest) statsError.value = errorMessage(err) }
    finally { if (request === statsRequest) statsLoading.value = false }
  }
  async function fetchTask(id: number) {
    const request = ++detailRequest
    detailError.value = ''
    try {
      const res = await api.get('/tasks/' + id)
      const task: Task = res.data.data || res.data
      if (request !== detailRequest) return null
      currentTask.value = task
      return task
    } catch (err) {
      if (request === detailRequest) detailError.value = errorMessage(err)
      return null
    }
  }
  function updateTaskInList(updated: Task) {
    for (const list of [tasks, myTasks]) {
      const index = list.value.findIndex(task => task.id === updated.id)
      if (index !== -1) list.value[index] = { ...list.value[index], ...updated }
    }
    if (currentTask.value?.id === updated.id) currentTask.value = { ...currentTask.value, ...updated }
  }
  async function createTask(payload: Record<string, unknown>): Promise<Task | null> {
    const current = session
    mutationError.value = ''; validationErrors.value = {}
    try {
      const res = await api.post('/tasks', payload)
      if (current !== session) return null
      const task: Task = res.data.data || res.data
      if (!tasks.value.some(existing => existing.id === task.id)) tasks.value.unshift(task)
      toast.success('Task created. The assignment is ready.')
      void refreshOverview()
      return task
    } catch (err) { if (current === session) { mutationError.value = errorMessage(err); validationErrors.value = fieldErrors(err) } return null }
  }
  async function updateTask(id: number, payload: Record<string, unknown>): Promise<Task | null> {
    mutationError.value = ''; validationErrors.value = {}
    if (pending.value[id]) return null
    pending.value[id] = true
    try {
      const res = await api.put('/tasks/' + id, payload)
      const updated: Task = res.data.data || res.data
      updateTaskInList(updated)
      toast.success('Changes saved.')
      void refreshOverview()
      return updated
    } catch (err) { mutationError.value = errorMessage(err); validationErrors.value = fieldErrors(err); return null }
    finally { pending.value[id] = false }
  }
  async function transition(id: number, action: 'start' | 'complete' | 'reopen', payload = {}): Promise<boolean> {
    if (pending.value[id]) return false
    mutationError.value = ''
    pending.value[id] = true
    try {
      const res = await api.post('/tasks/' + id + '/' + action, payload)
      updateTaskInList(res.data.data || res.data)
      if (action === 'complete') toast.action('Task completed.', 'Undo', () => reopenTask(id))
      else toast.success(action === 'start' ? 'Task started.' : 'Task reopened.')
      void refreshOverview()
      if (currentTask.value?.id === id) void fetchTask(id)
      return true
    } catch (err) { mutationError.value = errorMessage(err); toast.error(mutationError.value); return false }
    finally { pending.value[id] = false }
  }
  const startTask = (id: number) => transition(id, 'start')
  const completeTask = (id: number, completion_note?: string) => transition(id, 'complete', { completion_note })
  const reopenTask = (id: number) => transition(id, 'reopen')
  async function deleteTask(id: number): Promise<boolean> {
    if (pending.value[id]) return false
    mutationError.value = ''
    pending.value[id] = true
    try {
      await api.delete('/tasks/' + id)
      tasks.value = tasks.value.filter(task => task.id !== id)
      myTasks.value = myTasks.value.filter(task => task.id !== id)
      if (currentTask.value?.id === id) currentTask.value = null
      toast.success('Task deleted.')
      void refreshOverview()
      return true
    } catch (err) { mutationError.value = errorMessage(err); toast.error(mutationError.value); return false }
    finally { pending.value[id] = false }
  }
  async function uploadAttachment(taskId: number, file: File): Promise<TaskAttachment | null> {
    mutationError.value = ''
    if (file.size > 20 * 1024 * 1024) { mutationError.value = 'Choose a file smaller than 20 MB.'; toast.error(mutationError.value); return null }
    const data = new FormData(); data.append('file', file)
    try {
      const res = await api.post('/tasks/' + taskId + '/attachments', data, { headers: { 'Content-Type': 'multipart/form-data' } })
      const attachment = res.data.data || res.data
      if (currentTask.value?.id === taskId) currentTask.value.attachments = [...(currentTask.value.attachments || []), attachment]
      toast.success('File uploaded.')
      void fetchTask(taskId)
      return attachment
    } catch (err) { mutationError.value = errorMessage(err); toast.error(mutationError.value); return null }
  }
  async function refreshOverview() {
    const teams = useTeamStore(), teamId = teams.currentTeam?.id
    await Promise.allSettled([fetchDashboardStats(), fetchMyTasks(), teams.fetchTeams(), ...(teamId ? [teams.fetchTeam(teamId)] : [])])
  }
  function reset() {
    ++session
    ++tasksRequest; ++myRequest; ++statsRequest; ++detailRequest
    tasks.value = []; myTasks.value = []; currentTask.value = null; stats.value = null
    filters.value = defaultFilters()
    loading.value = false; myLoading.value = false; statsLoading.value = false
    error.value = ''; myError.value = ''; statsError.value = ''; detailError.value = ''
    mutationError.value = ''; validationErrors.value = {}; pending.value = {}
  }
  return { tasks, myTasks, currentTask, stats, filters, filteredTasks, loading, myLoading, statsLoading, error, myError, statsError, detailError, pending, mutationError, validationErrors, fetchTasks, fetchMyTasks, fetchDashboardStats, fetchTask, createTask, updateTask, startTask, completeTask, reopenTask, deleteTask, uploadAttachment, updateTaskInList, refreshOverview, reset }
})
