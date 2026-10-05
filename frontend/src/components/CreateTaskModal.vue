<template>
  <BaseModal :is-open="isOpen" title="Create a task" description="A clear title and owner are a great start. Add details as you need them." :busy="submitting" @close="$emit('close')">
    <form id="create-task-form" class="stack" novalidate @submit.prevent="submitForm" @keydown="submitShortcut">
      <div v-if="hasDraft" class="notice"><FilePenLine aria-hidden="true" /><div class="grow"><strong>You have an unfinished draft</strong><p class="small">Pick up where you left off.</p><div class="row wrap" style="margin-top:8px"><button type="button" class="btn btn-soft" @click="restoreDraft">Resume draft</button><button type="button" class="btn btn-ghost" @click="discardDraft">Discard draft</button></div></div></div>
      <div v-if="Object.keys(errors).length || tasks.mutationError" ref="errorSummary" class="error-banner" tabindex="-1" role="alert">
        <div class="grow"><strong>We couldn't create this task.</strong><p v-if="tasks.mutationError && !Object.keys(errors).length">{{ tasks.mutationError }}</p><ul v-if="Object.keys(errors).length" style="margin:8px 0 0;padding-left:18px"><li v-for="(message,key) in errors" :key="key"><button type="button" style="border:0;background:transparent;color:inherit;text-align:left;text-decoration:underline;min-height:32px" @click="focusField(String(key))">{{ message }}</button></li></ul></div>
      </div>
      <div class="field"><label for="create-title">Task title <span class="muted small">Required</span></label><input id="create-title" v-model="form.title" autofocus maxlength="255" placeholder="What needs to get done?" :aria-invalid="!!errors.title" aria-describedby="create-title-hint create-title-error" /><p id="create-title-hint" class="field-hint">Start with a verb, such as “Review the campaign brief”.</p><p v-if="errors.title" id="create-title-error" class="field-error">{{ errors.title }}</p></div>
      <div class="field"><label for="create-description">Instructions <span class="muted small">Optional</span></label><textarea id="create-description" v-model="form.description" rows="3" placeholder="Describe the outcome, include reference links or a checklist." /></div>
      <div class="field"><label for="create-team">Team</label><select id="create-team" v-model="form.team_id" :aria-invalid="!!errors.team_id" @change="changeTeam"><option value="">General / no team</option><option v-for="team in teams.teams" :key="team.id" :value="team.id">{{ team.name }}</option></select><p v-if="errors.team_id" class="field-error">{{ errors.team_id }}</p><p v-if="teams.error" class="field-error">{{ teams.error }} <button type="button" class="text-link" @click="teams.fetchTeams()">Retry</button></p></div>
      <fieldset style="border:0;padding:0;margin:0" class="stack">
        <legend style="font-weight:600;font-size:13px;margin-bottom:8px">Assign to <span class="muted small">Select one or more people</span></legend>
        <div class="row wrap"><button type="button" class="btn" :class="{ 'btn-soft': !form.assignee_ids.length }" :aria-pressed="!form.assignee_ids.length" @click="form.assignee_ids = []">{{ form.team_id ? 'Entire team' : 'Leave unassigned' }}</button><button v-if="members.length" type="button" class="btn btn-ghost" @click="form.assignee_ids = members.map(member => member.id)">Select all</button></div>
        <p v-if="membersLoading" class="muted small" role="status">Loading available members…</p>
        <div v-else-if="membersError" class="error-banner" role="alert"><span class="grow">{{ membersError }}</span><button type="button" class="btn" @click="loadMembers()">Retry</button></div>
        <div v-else class="form-grid">
          <label v-for="member in members" :key="member.id" class="member-choice"><input v-model="form.assignee_ids" type="checkbox" :value="member.id" /><span class="grow"><strong>{{ member.name }}</strong><span class="muted small" style="display:block">{{ member.active_tasks_count || 0 }} active tasks</span></span><span v-if="member.overdue_count" class="badge badge-danger">{{ member.overdue_count }} overdue</span></label>
        </div>
        <p class="field-hint" aria-live="polite">{{ form.assignee_ids.length ? form.assignee_ids.length + (form.assignee_ids.length === 1 ? ' selected member will own this task.' : ' selected members will own this task.') : form.team_id ? 'With no individual selected, this task belongs to the whole team.' : 'This task will remain unassigned until an owner is added.' }}</p>
      </fieldset>
      <div class="form-grid">
        <div class="field"><label for="create-priority">Priority</label><select id="create-priority" v-model="form.priority"><option value="normal">Normal</option><option value="high">High priority</option><option value="urgent">Urgent</option></select></div>
        <div class="field"><label for="create-start">Start</label><select id="create-start" v-model="form.start_type"><option value="now">Start now</option><option value="scheduled">Schedule for later</option></select></div>
        <div v-if="form.start_type === 'scheduled'" class="field"><label for="create-scheduled">Start date & time</label><input id="create-scheduled" v-model="form.scheduled_at" type="datetime-local" :aria-invalid="!!errors.scheduled_at" aria-describedby="create-scheduled-error" /><p v-if="errors.scheduled_at" id="create-scheduled-error" class="field-error">{{ errors.scheduled_at }}</p></div>
        <div class="field"><label for="create-deadline">Deadline <span class="muted small">Optional</span></label><input id="create-deadline" v-model="form.deadline" type="datetime-local" :aria-invalid="!!errors.deadline" aria-describedby="create-deadline-error" /><p v-if="errors.deadline" id="create-deadline-error" class="field-error">{{ errors.deadline }}</p></div>
      </div>
      <div class="notice"><Clock3 aria-hidden="true" /><p class="small">Dates use Asia/Manila (UTC+8). {{ form.start_type === 'now' ? 'This task will start in progress.' : 'The task will remain scheduled until its start time.' }}</p></div>
    </form>
    <template #footer><span class="small muted grow">Draft saved as you type · <kbd>Ctrl / ⌘ Enter</kbd></span><button type="button" class="btn btn-ghost" :disabled="submitting" @click="$emit('close')">Cancel</button><button type="submit" form="create-task-form" class="btn btn-primary" :disabled="submitting || membersLoading"><LoaderCircle v-if="submitting" class="spinner" aria-hidden="true" /><Plus v-else aria-hidden="true" />{{ submitting ? 'Creating…' : 'Create task' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, reactive, watch, nextTick } from 'vue'
import { FilePenLine, Clock3, Plus, LoaderCircle } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { useTeamStore } from '@/stores/teams'
import api from '@/services/api'
import { inputToIso, parseDate, errorMessage } from '@/utils/tasks'
import type { CreateTaskPrefill, TaskPriority, User } from '@/types'
const props = defineProps<{ isOpen: boolean; prefill?: CreateTaskPrefill }>()
const emit = defineEmits(['close', 'created'])
const auth = useAuthStore(), tasks = useTaskStore(), teams = useTeamStore()
const blank = () => ({ title: '', description: '', team_id: '' as number | string, assignee_ids: [] as number[], priority: 'normal' as TaskPriority, start_type: 'now' as 'now' | 'scheduled', scheduled_at: '', deadline: '' })
const form = reactive(blank())
const submitting = ref(false), hasDraft = ref(false), errors = ref<Record<string,string>>({}), errorSummary = ref<HTMLElement | null>(null)
const members = ref<User[]>([]), membersLoading = ref(false), membersError = ref('')
let memberRequest = 0, saveDraft = false
function draftKey() { return 'kcg_task_draft_' + auth.user?.id }
watch(() => props.isOpen, async open => {
  if (!open) { saveDraft = false; ++memberRequest; return }
  errors.value = {}; tasks.mutationError = ''; tasks.validationErrors = {}; Object.assign(form, blank())
  const prefill = props.prefill || {}
  const hasPrefill = Object.keys(prefill).length > 0
  if (hasPrefill) Object.assign(form, { title: prefill.title || '', description: prefill.description || '', team_id: prefill.team_id || '', priority: prefill.priority || 'normal', assignee_ids: prefill.assignee_ids || (prefill.assigned_to ? [prefill.assigned_to] : []) })
  try { hasDraft.value = !hasPrefill && !!localStorage.getItem(draftKey()) } catch { hasDraft.value = false }
  saveDraft = !hasDraft.value
  void teams.fetchTeams()
  await loadMembers()
  await nextTick(); document.getElementById('create-title')?.focus()
})
watch(form, () => {
  if (!props.isOpen || !saveDraft) return
  try { if (form.title || form.description || form.team_id || form.assignee_ids.length) localStorage.setItem(draftKey(), JSON.stringify(form)) } catch { /* Form stays usable without storage. */ }
}, { deep: true })
async function loadMembers() {
  const request = ++memberRequest
  members.value = []; membersLoading.value = true; membersError.value = ''
  try {
    const res = await api.get(form.team_id ? '/teams/' + form.team_id : '/users')
    if (request !== memberRequest) return
    members.value = form.team_id ? (res.data.data || res.data).members || [] : res.data.data || res.data
    form.assignee_ids = form.assignee_ids.filter(id => members.value.some(member => member.id === id))
  } catch (err) { if (request === memberRequest) membersError.value = errorMessage(err) }
  finally { if (request === memberRequest) membersLoading.value = false }
}
function changeTeam() { form.assignee_ids = []; void loadMembers() }
function restoreDraft() {
  try { const draft = JSON.parse(localStorage.getItem(draftKey()) || 'null'); if (draft) Object.assign(form, blank(), draft) } catch { /* Ignore malformed drafts. */ }
  hasDraft.value = false; saveDraft = true; void loadMembers(); document.getElementById('create-title')?.focus()
}
function discardDraft() { try { localStorage.removeItem(draftKey()) } catch {} hasDraft.value = false; saveDraft = true }
function focusField(key: string) { document.getElementById('create-' + ({ title: 'title', team_id: 'team', priority: 'priority', scheduled_at: 'scheduled', deadline: 'deadline' }[key] || 'title'))?.focus() }
function submitShortcut(event: KeyboardEvent) { if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') { event.preventDefault(); void submitForm() } }
async function submitForm() {
  if (submitting.value || membersLoading.value) return
  errors.value = {}; tasks.mutationError = ''
  if (!form.title.trim()) errors.value.title = 'Enter a task title.'
  if (form.start_type === 'scheduled' && (!form.scheduled_at || parseDate(form.scheduled_at).getTime() <= Date.now())) errors.value.scheduled_at = 'Choose a start date and time in the future.'
  if (form.deadline && parseDate(form.deadline).getTime() <= Date.now()) errors.value.deadline = 'Choose a deadline in the future.'
  if (form.start_type === 'scheduled' && form.scheduled_at && form.deadline && parseDate(form.deadline) < parseDate(form.scheduled_at)) errors.value.deadline = 'The deadline must come after the scheduled start.'
  if (Object.keys(errors.value).length) { await nextTick(); errorSummary.value?.focus(); return }
  submitting.value = true
  const created = await tasks.createTask({ title: form.title.trim(), description: form.description || null, team_id: form.team_id ? Number(form.team_id) : null, assigned_to: form.assignee_ids[0] || null, assignee_ids: form.assignee_ids, priority: form.priority, start_type: form.start_type, scheduled_at: form.start_type === 'scheduled' ? inputToIso(form.scheduled_at) : null, deadline: inputToIso(form.deadline) })
  submitting.value = false
  if (created) { saveDraft = false; try { localStorage.removeItem(draftKey()) } catch {} emit('created', created); emit('close') }
  else { errors.value = { ...tasks.validationErrors }; await nextTick(); errorSummary.value?.focus() }
}
</script>
