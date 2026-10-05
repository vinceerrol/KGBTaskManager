<template>
  <div class="page">
    <div class="page-header"><div><p class="eyebrow">Workspace / Tasks</p><h1>A place for every task.</h1><p class="page-subtitle">Keep the big picture. Find the details. Move work forward.</p></div><button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task')"><Plus aria-hidden="true" />Create task</button></div>
    <div class="panel stack">
      <div class="section-header"><div class="segmented" aria-label="Task presentation"><button v-for="view in views" :key="view.id" :aria-pressed="preferences.taskView === view.id" @click="preferences.taskView = view.id"><component :is="view.icon" aria-hidden="true" />{{ view.label }}</button></div><div class="row"><button class="btn btn-ghost" :disabled="tasks.loading" @click="tasks.fetchTasks()"><RefreshCw :class="{ spinner:tasks.loading }" aria-hidden="true" />Refresh</button><button class="btn" @click="beginSave"><BookmarkPlus aria-hidden="true" />Save view</button></div></div>
      <div class="filter-toolbar">
        <div class="field search-filter"><label for="task-search">Search</label><div class="search-field"><Search aria-hidden="true" /><input id="task-search" v-model="tasks.filters.search" placeholder="Title, team or assignee" type="search" /></div></div>
        <div class="field"><label for="filter-status">Status</label><select id="filter-status" v-model="tasks.filters.status"><option value="all">All statuses</option><option value="IN PROGRESS">In progress</option><option value="SCHEDULED">Scheduled</option><option value="OVERDUE">Overdue</option><option value="DONE">Completed</option></select></div>
        <div class="field"><label for="filter-team">Team</label><select id="filter-team" v-model="tasks.filters.team_id"><option value="">All teams</option><option v-for="team in teams.teams" :key="team.id" :value="String(team.id)">{{ team.name }}</option></select></div>
        <div class="field"><label for="filter-priority">Priority</label><select id="filter-priority" v-model="tasks.filters.priority"><option value="">All priorities</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option></select></div>
        <div class="field"><label for="filter-date">Date</label><select id="filter-date" v-model="tasks.filters.date_filter"><option value="all">Any date</option><option value="today">Today</option><option value="tomorrow">Tomorrow</option><option value="week">This week</option></select></div>
      </div>
      <div v-if="chips.length" class="row wrap" aria-label="Applied filters"><button v-for="chip in chips" :key="chip.key" class="filter-chip" :aria-label="'Remove filter ' + chip.label" @click="removeFilter(chip.key)">{{ chip.label }}<X aria-hidden="true" /></button><button class="btn btn-ghost" @click="resetFilters">Clear all</button></div>
      <form v-if="showSave" class="save-view-form" @submit.prevent="saveView"><label for="view-name" class="sr-only">Saved view name</label><input id="view-name" v-model="viewName" class="input" maxlength="40" required placeholder="Name this view, e.g. Urgent work" /><button class="btn btn-primary" type="submit">Save</button><button class="btn btn-ghost" type="button" @click="showSave = false">Cancel</button></form>
      <div v-if="savedViews.length" class="saved-views" aria-label="Saved views"><span class="small muted">Saved views</span><div v-for="view in savedViews" :key="view.id" class="row" style="gap:0"><button class="btn btn-soft" @click="applySaved(view)"><Bookmark aria-hidden="true" />{{ view.name }}</button><button class="icon-btn" :aria-label="'Remove saved view ' + view.name" @click="removeSaved(view.id)"><X aria-hidden="true" /></button></div></div>
      <p v-if="storageError" class="field-error" role="alert">{{ storageError }}</p>
    </div>
    <div class="result-toolbar"><p class="muted" role="status" aria-live="polite">{{ orderedTasks.length }} {{ orderedTasks.length === 1 ? 'task' : 'tasks' }}{{ chips.length ? orderedTasks.length === 1 ? ' matches your view' : ' match your view' : tasks.olderCompleted > 0 ? ' loaded' : ' in the workspace' }}</p><div class="row"><label for="sort-tasks" class="small muted">Sort by</label><select id="sort-tasks" v-model="sort" class="input" style="width:auto"><option value="priority">Needs attention</option><option value="deadline">Deadline</option><option value="newest">Newest first</option><option value="title">Title A–Z</option></select></div></div>
    <div v-if="tasks.error" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ tasks.error }}</span><button class="btn" @click="tasks.fetchTasks()">Try again</button></div>
    <LoadingState v-if="tasks.loading && !tasks.tasks.length" label="Loading your tasks" :count="6" />
    <EmptyState v-else-if="!orderedTasks.length && !tasks.error" :title="chips.length ? 'No tasks match this view' : 'Make space for your next idea'" :description="chips.length ? 'Try a broader search or remove a filter. Your saved views are still here.' : 'Add your first task, or start from a reusable workflow.'"><button v-if="chips.length" class="btn btn-primary" @click="resetFilters">Clear filters</button><button v-else-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task')"><Plus aria-hidden="true" />Create a task</button><RouterLink v-else to="/my-tasks" class="btn">Go to my work</RouterLink></EmptyState>
    <TaskCollection v-else :tasks="orderedTasks" :mode="preferences.taskView" @select-task="$emit('select-task',$event)" @complete-task="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" />
    <div v-if="tasks.olderCompleted > 0 || tasks.olderError" class="panel row wrap" style="justify-content:space-between">
      <p class="small muted" role="status">{{ tasks.olderCompleted }} completed {{ tasks.olderCompleted === 1 ? 'task' : 'tasks' }} older than {{ RECENT_DONE_DAYS }} days {{ tasks.olderCompleted === 1 ? 'is' : 'are' }} not loaded, so they are not in the list or search above.</p>
      <button class="btn" :disabled="tasks.loadingOlder" @click="tasks.loadOlderCompleted()"><LoaderCircle v-if="tasks.loadingOlder" class="spinner" aria-hidden="true" />{{ tasks.loadingOlder ? 'Loading…' : tasks.olderError ? 'Try again' : 'Load older completed tasks' }}</button>
      <p v-if="tasks.olderError" class="field-error" role="alert" style="flex-basis:100%">{{ tasks.olderError }}</p>
    </div>
    <p class="small muted">Board actions work with a keyboard or touch. All dates use UTC+8.</p>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Plus, LayoutGrid, List, Columns3, BookmarkPlus, Bookmark, Search, X, RefreshCw, CircleAlert, LoaderCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore, defaultFilters, RECENT_DONE_DAYS } from '@/stores/tasks'
import { useTeamStore } from '@/stores/teams'
import { usePreferencesStore } from '@/stores/preferences'
import { focusScore, parseDate } from '@/utils/tasks'
import { toast } from '@/stores/toast'
import TaskCollection from '@/components/TaskCollection.vue'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import type { TaskFilterOptions } from '@/types'
defineEmits(['open-create-task','select-task','complete-task','share-messenger'])
const auth = useAuthStore(), tasks = useTaskStore(), teams = useTeamStore(), preferences = usePreferencesStore(), route = useRoute(), router = useRouter()
const views = [{ id:'grid' as const,label:'Cards',icon:LayoutGrid },{ id:'list' as const,label:'List',icon:List },{ id:'board' as const,label:'Board',icon:Columns3 }]
const sort = ref('priority'), showSave = ref(false), viewName = ref(''), storageError = ref('')
interface SavedView { id: string; name: string; filters: TaskFilterOptions; mode: 'grid' | 'list' | 'board'; sort: string }
const savedViews = ref<SavedView[]>([])
const keys: (keyof TaskFilterOptions)[] = ['status','team_id','assigned_to','priority','date_filter','search']
let updatingFromRoute = false, queryTimer: ReturnType<typeof setTimeout> | undefined
function readQuery() {
  clearTimeout(queryTimer)
  updatingFromRoute = true
  const f = defaultFilters()
  for (const key of keys) { const value = route.query[key]; if (typeof value === 'string') (f as Record<string,unknown>)[key] = value }
  if (!['all','SCHEDULED','IN PROGRESS','OVERDUE','DONE'].includes(f.status || '')) f.status = 'all'
  if (!['all','today','tomorrow','week'].includes(f.date_filter || '')) f.date_filter = 'all'
  if (f.priority && !['normal','high','urgent'].includes(f.priority)) f.priority = ''
  tasks.filters = f; updatingFromRoute = false
}
readQuery()
watch(() => route.query, readQuery)
watch(() => tasks.filters, () => {
  if (updatingFromRoute) return
  clearTimeout(queryTimer)
  queryTimer = setTimeout(() => {
    const query: Record<string,string> = {}
    for (const key of keys) { const value = tasks.filters[key]; if (value !== '' && value !== 'all' && value !== undefined) query[key] = String(value) }
    if (JSON.stringify(query) !== JSON.stringify(route.query)) void router.replace({ query })
  }, 250)
}, { deep:true })
const chips = computed(() => {
  const f = tasks.filters, result: { key:keyof TaskFilterOptions;label:string }[] = []
  if (f.search) result.push({ key:'search',label:'Search: ' + f.search })
  if (f.status && f.status !== 'all') result.push({ key:'status',label:f.status === 'DONE' ? 'Completed' : f.status.toLowerCase() })
  if (f.team_id) result.push({ key:'team_id',label:teams.teams.find(team => team.id === Number(f.team_id))?.name || 'Selected team' })
  if (f.priority) result.push({ key:'priority',label:f.priority + ' priority' })
  if (f.assigned_to) result.push({ key:'assigned_to',label:'Assigned member #' + f.assigned_to })
  if (f.date_filter && f.date_filter !== 'all') result.push({ key:'date_filter',label:f.date_filter === 'week' ? 'This week' : f.date_filter })
  return result
})
const orderedTasks = computed(() => [...tasks.filteredTasks].sort((a,b) => sort.value === 'title' ? a.title.localeCompare(b.title) : sort.value === 'newest' ? parseDate(b.created_at).getTime() - parseDate(a.created_at).getTime() : sort.value === 'deadline' ? (a.deadline ? parseDate(a.deadline).getTime() : Infinity) - (b.deadline ? parseDate(b.deadline).getTime() : Infinity) : focusScore(b) - focusScore(a) || parseDate(b.created_at).getTime() - parseDate(a.created_at).getTime()))
function removeFilter(key:keyof TaskFilterOptions) { (tasks.filters as Record<string,unknown>)[key] = defaultFilters()[key] }
function resetFilters() { tasks.filters = defaultFilters() }
function storageKey() { return 'kcg_saved_views_' + auth.user?.id }
async function beginSave() { showSave.value = !showSave.value; if (showSave.value) { await nextTick(); document.getElementById('view-name')?.focus() } }
function persistViews() { try { localStorage.setItem(storageKey(),JSON.stringify(savedViews.value)); storageError.value = ''; return true } catch { storageError.value = 'Browser storage is unavailable. This view is available until the page closes.'; return false } }
function saveView() { if (!viewName.value.trim()) return; savedViews.value.push({ id:crypto.randomUUID(),name:viewName.value.trim(),filters:{ ...tasks.filters },mode:preferences.taskView,sort:sort.value }); persistViews(); viewName.value = ''; showSave.value = false; toast.success('View saved. Find it above the task list.') }
function applySaved(view:SavedView) { tasks.filters = { ...defaultFilters(),...view.filters }; preferences.taskView = view.mode; sort.value = view.sort }
function removeSaved(id:string) { const removed = savedViews.value.find(view => view.id === id); savedViews.value = savedViews.value.filter(view => view.id !== id); persistViews(); if (removed) toast.action('Saved view removed.','Undo',() => { savedViews.value.push(removed); persistViews() }) }
onMounted(() => { try { const saved = JSON.parse(localStorage.getItem(storageKey()) || '[]'); savedViews.value = Array.isArray(saved) ? saved.filter(view => view && typeof view.name === 'string' && view.filters) : [] } catch {} if (!tasks.tasks.length && !tasks.loading) void tasks.fetchTasks(); if (!teams.teams.length) void teams.fetchTeams() })
onBeforeUnmount(() => clearTimeout(queryTimer))
</script>
