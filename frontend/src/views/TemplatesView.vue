<template>
  <div class="page">
    <div class="page-header"><div><p class="eyebrow">Workflows / Library</p><h1>Good work. On repeat.</h1><p class="page-subtitle">Turn repeatable processes into templates and predictable rhythms.</p></div><button v-if="!auth.isEmployee" class="btn btn-primary" @click="openForm('template')"><Plus aria-hidden="true" />New template</button></div>
    <div class="panel row wrap" style="justify-content:space-between"><div class="row"><Layers style="color:var(--brand)" aria-hidden="true" /><div><strong>A head start for every handoff</strong><p class="small muted">Reuse instructions, team and priority. Review the owner and deadline before creating a task.</p></div></div><span class="badge badge-brand">{{ templates.length }} templates</span></div>
    <div class="section-header"><div class="tab-strip" style="border:0" aria-label="Workflow type"><button :aria-pressed="tab === 'templates'" @click="tab = 'templates'">Templates <span class="badge">{{ templates.length }}</span></button><button :aria-pressed="tab === 'recurring'" @click="tab = 'recurring'">Recurring workflows <span class="badge">{{ recurring.length }}</span></button></div><button class="btn btn-ghost" :disabled="loading" @click="load"><RefreshCw :class="{ spinner:loading }" aria-hidden="true" />Refresh</button></div>
    <div v-if="error" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ error }}</span><button class="btn" @click="load">Try again</button></div>
    <LoadingState v-if="loading && !templates.length && !recurring.length" label="Loading workflow library" />
    <template v-else-if="tab === 'templates'">
      <div class="field search-field" style="max-width:420px"><label for="template-search" class="sr-only">Search templates</label><Search aria-hidden="true" /><input id="template-search" v-model="query" type="search" placeholder="Find a template by name or team" /></div>
      <EmptyState v-if="!filteredTemplates.length && !error" :title="query ? 'No templates match your search' : 'Make your best process reusable'" :description="query ? 'Try a different keyword or clear the search.' : 'Save instructions once, then create consistent tasks with a few clicks.'" :icon="Layers"><button v-if="query" class="btn" @click="query = ''">Clear search</button><button v-else-if="!auth.isEmployee" class="btn btn-primary" @click="openForm('template')">Create a template</button></EmptyState>
      <div v-else class="task-grid"><article v-for="template in filteredTemplates" :key="template.id" class="panel stack"><div class="row wrap"><span class="team-icon"><FileText aria-hidden="true" /></span><span class="grow" /><span class="badge" :class="priorityClass(template.priority)">{{ template.priority }}</span></div><div><h2 style="font-size:17px">{{ template.title }}</h2><p class="small muted" style="margin-top:8px">{{ template.team?.name || 'General' }}</p></div><p v-if="template.description" class="muted">{{ template.description }}</p><details v-if="template.default_instructions"><summary style="min-height:44px;cursor:pointer;color:var(--brand);font-weight:600">Preview instructions</summary><p class="instructions" style="margin-top:8px">{{ template.default_instructions }}</p></details><button v-if="!auth.isEmployee" class="btn btn-soft" style="margin-top:auto" @click="useTemplate(template)"><Copy aria-hidden="true" />Use template <ArrowUpRight aria-hidden="true" /></button><div v-if="!auth.isEmployee" class="row wrap"><button class="btn btn-ghost" :aria-label="'Edit ' + template.title" @click="openForm('template', template)"><Pencil aria-hidden="true" />Edit</button><button class="btn btn-ghost" style="color:var(--danger)" :aria-label="'Delete ' + template.title" @click="removeTemplate(template)"><Trash2 aria-hidden="true" />Delete</button></div><p v-else class="small muted">Ask your team lead to create a task from this template.</p></article></div>
    </template>
    <template v-else>
      <div class="section-header"><div><h2>Set the rhythm</h2><p class="small muted" style="margin-top:5px">{{ recurring.filter(item => item.is_active).length }} active schedules · Asia/Manila (UTC+8)</p></div><button v-if="!auth.isEmployee" class="btn btn-primary" @click="openForm('recurring')"><Repeat2 aria-hidden="true" />New recurring workflow</button></div>
      <EmptyState v-if="!recurring.length && !error" title="Put repeatable work on a schedule" description="Daily, weekly or monthly. Create a workflow and let the scheduler handle the next task." :icon="Repeat2"><button v-if="!auth.isEmployee" class="btn btn-primary" @click="openForm('recurring')">Create a recurring workflow</button></EmptyState>
      <div v-else><article v-for="item in recurring" :key="item.id" class="panel recurring-card"><div class="stack" style="gap:10px"><div class="row wrap"><span class="badge" :class="item.is_active ? 'badge-success' : ''">{{ item.is_active ? 'Active' : 'Paused' }}</span><span class="badge badge-brand"><Repeat2 aria-hidden="true" />{{ item.frequency }} · {{ item.scheduled_time }}</span></div><h3 style="font-size:17px">{{ item.title }}</h3><p v-if="item.description" class="muted small">{{ item.description }}</p><div class="row wrap small muted"><Users aria-hidden="true" />{{ item.team?.name || 'General' }} · {{ item.assignee?.name || (item.team_id ? 'Entire team' : 'Unassigned') }}</div><p class="small muted"><Clock3 style="display:inline;width:14px;height:14px;vertical-align:middle" aria-hidden="true" /> {{ item.is_active ? item.next_run_at ? 'Next run: ' + formatDateTime(item.next_run_at) : 'Next run is initialized by the scheduler' : 'No tasks will be generated while paused' }}</p></div><button v-if="!auth.isEmployee" class="btn" :class="{ 'btn-soft':!item.is_active }" :disabled="pending[item.id]" :aria-label="(item.is_active ? 'Pause ' : 'Resume ') + item.title" @click="toggle(item)"><LoaderCircle v-if="pending[item.id]" class="spinner" aria-hidden="true" /><Pause v-else-if="item.is_active" aria-hidden="true" /><Play v-else aria-hidden="true" />{{ pending[item.id] ? 'Saving…' : item.is_active ? 'Pause workflow' : 'Resume workflow' }}</button><div v-if="!auth.isEmployee" class="row wrap"><button class="btn btn-ghost" :aria-label="'Edit ' + item.title" @click="openForm('recurring', item)"><Pencil aria-hidden="true" />Edit</button><button class="btn btn-ghost" style="color:var(--danger)" :aria-label="'Delete ' + item.title" @click="removeRecurring(item)"><Trash2 aria-hidden="true" />Delete</button></div></article></div>
      <div class="notice"><Info aria-hidden="true" /><p class="small">Recurring tasks are generated when the workspace scheduler runs. Pausing keeps existing tasks; resuming schedules the next future occurrence.</p></div>
    </template>
    <WorkflowFormModal :is-open="showForm" :mode="formMode" :item="editingItem" @close="showForm = false" @saved="onSaved" />
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Plus, Layers, RefreshCw, CircleAlert, Search, FileText, Copy, ArrowUpRight, Repeat2, Users, Clock3, LoaderCircle, Pause, Play, Info, Pencil, Trash2 } from 'lucide-vue-next'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/stores/toast'
import { errorMessage, priorityClass, formatDateTime } from '@/utils/tasks'
import EmptyState from '@/components/EmptyState.vue'
import LoadingState from '@/components/LoadingState.vue'
import WorkflowFormModal from '@/components/WorkflowFormModal.vue'
import type { TaskTemplate, RecurringTask } from '@/types'
const emit = defineEmits(['open-create-task'])
const auth = useAuthStore(), templates = ref<TaskTemplate[]>([]), recurring = ref<RecurringTask[]>([]), loading = ref(false), error = ref(''), query = ref(''), tab = ref('templates'), pending = ref<Record<number,boolean>>({})
const showForm = ref(false), formMode = ref<'template' | 'recurring'>('template'), editingItem = ref<TaskTemplate | RecurringTask | null>(null)
let request = 0
const filteredTemplates = computed(() => templates.value.filter(item => [item.title,item.description,item.team?.name].some(text => text?.toLowerCase().includes(query.value.toLowerCase()))))
async function load() {
  const current = ++request; loading.value = true; error.value = ''
  const results = await Promise.allSettled([api.get('/templates'),api.get('/recurring-tasks')])
  if (current !== request) return
  if (results[0].status === 'fulfilled') templates.value = results[0].value.data.data || results[0].value.data
  else error.value = errorMessage(results[0].reason)
  if (results[1].status === 'fulfilled') recurring.value = results[1].value.data.data || results[1].value.data
  else error.value = errorMessage(results[1].reason)
  loading.value = false
}
function useTemplate(template:TaskTemplate) { emit('open-create-task',{ title:template.title,description:template.default_instructions || template.description,team_id:template.team_id,priority:template.priority }) }
function openForm(mode:'template' | 'recurring', item: TaskTemplate | RecurringTask | null = null) { formMode.value = mode; editingItem.value = item; showForm.value = true }
function onSaved() { const edited = !!editingItem.value; toast.success(formMode.value === 'template' ? (edited ? 'Template updated.' : 'Template added to your library.') : (edited ? 'Recurring workflow updated.' : 'Recurring workflow created.')); void load() }
async function removeTemplate(template: TaskTemplate) { if (!window.confirm('Delete the template "' + template.title + '"? Tasks already created from it are not affected.')) return; try { await api.delete('/templates/' + template.id); toast.success('Template deleted.'); await load() } catch (err) { toast.error(errorMessage(err)) } }
async function removeRecurring(item: RecurringTask) { if (!window.confirm('Delete the recurring workflow "' + item.title + '"? Tasks it already created are kept; no new ones will be made.')) return; try { await api.delete('/recurring-tasks/' + item.id); toast.success('Recurring workflow deleted.'); await load() } catch (err) { toast.error(errorMessage(err)) } }
async function toggle(item:RecurringTask) { if (pending.value[item.id]) return; pending.value[item.id] = true; try { const res = await api.post('/recurring-tasks/' + item.id + '/toggle'); Object.assign(item,res.data.data || res.data); toast.success(item.is_active ? 'Workflow resumed.' : 'Workflow paused. Existing tasks are kept.') } catch (err) { toast.error(errorMessage(err)) } finally { pending.value[item.id] = false } }
onMounted(load)
</script>
