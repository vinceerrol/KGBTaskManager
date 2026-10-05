<template>
  <BaseModal :is-open="isOpen && !!task" :title="current?.title || 'Task details'" :description="'Task #' + (current?.id || '') + ' · ' + (current?.team?.name || 'General')" wide :busy="saving || uploading || deleting || savingTemplate" @close="$emit('close')">
    <div v-if="current" class="stack" style="gap:24px">
      <div class="row wrap"><span class="badge" :class="statusClass(current)">{{ statusLabel(current) }}</span><span class="badge" :class="priorityClass(current.priority)">{{ current.priority === 'normal' ? 'Normal priority' : current.priority === 'high' ? 'High priority' : 'Urgent' }}</span><span class="grow" /><button class="btn" @click="$emit('share-messenger', current)"><Share2 aria-hidden="true" />Share</button></div>
      <div v-if="editing" class="panel" style="background:var(--surface-soft)">
        <form id="edit-task-form" class="stack" novalidate @submit.prevent="saveChanges">
          <div v-if="editError" ref="editErrorSummary" class="error-banner" role="alert" tabindex="-1">{{ editError }}</div>
          <div class="field"><label for="edit-title">Task title</label><input id="edit-title" v-model="edit.title" maxlength="255" :aria-invalid="!!editErrors.title" aria-describedby="edit-title-error" /><p v-if="editErrors.title" id="edit-title-error" class="field-error">{{ editErrors.title }}</p></div>
          <div class="field"><label for="edit-description">Instructions</label><textarea id="edit-description" v-model="edit.description" rows="4" /></div>
          <div class="field"><label for="edit-team">Team</label><select id="edit-team" v-model="edit.team_id" :aria-invalid="!!editErrors.team_id" @change="changeEditTeam"><option value="">General / no team</option><option v-for="team in teams.teams" :key="team.id" :value="team.id">{{ team.name }}</option></select><p v-if="editErrors.team_id" class="field-error">{{ editErrors.team_id }}</p></div>
          <fieldset class="stack" style="border:0;padding:0;margin:0">
            <legend style="font-weight:600;font-size:13px;margin-bottom:8px">Task owners</legend>
            <button type="button" class="btn" style="align-self:flex-start" :aria-pressed="!edit.assignee_ids.length" @click="edit.assignee_ids = []">{{ edit.team_id ? 'Assign to entire team' : 'Leave unassigned' }}</button>
            <p v-if="editMembersLoading" role="status" class="small muted">Loading available members…</p>
            <p v-else-if="editMembersError" role="alert" class="field-error">{{ editMembersError }} <button type="button" class="text-link" @click="loadEditMembers">Retry</button></p>
            <div v-else class="form-grid"><label v-for="member in editMembers" :key="member.id" class="member-choice"><input v-model="edit.assignee_ids" type="checkbox" :value="member.id" /><span>{{ member.name }}</span></label></div>
            <p class="field-hint">{{ edit.assignee_ids.length ? edit.assignee_ids.length + ' selected owners' : edit.team_id ? 'This task belongs to the whole team.' : 'This task is unassigned.' }}</p>
            <p v-if="editErrors.assignee_ids || editErrors.assigned_to" class="field-error">{{ editErrors.assignee_ids || editErrors.assigned_to }}</p>
          </fieldset>
          <div class="form-grid"><div class="field"><label for="edit-priority">Priority</label><select id="edit-priority" v-model="edit.priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></div><div class="field"><label for="edit-deadline">Deadline (UTC+8)</label><input id="edit-deadline" v-model="edit.deadline" type="datetime-local" :aria-invalid="!!editErrors.deadline" aria-describedby="edit-deadline-error" /><p v-if="editErrors.deadline" id="edit-deadline-error" class="field-error">{{ editErrors.deadline }}</p></div></div>
          <p class="small muted">Save or cancel your edits before completing this task.</p>
          <div class="row wrap" style="justify-content:flex-end"><button type="button" class="btn btn-ghost" :disabled="saving" @click="editing = false">Cancel editing</button><button class="btn btn-primary" type="submit" :disabled="saving || editMembersLoading || !!editMembersError"><LoaderCircle v-if="saving" class="spinner" aria-hidden="true" />{{ saving ? 'Saving…' : 'Save changes' }}</button></div>
        </form>
      </div>
      <p v-if="tasks.mutationError && !editing" class="error-banner" role="alert">{{ tasks.mutationError }}</p>
      <p v-if="actionNotice" class="notice" role="status">{{ actionNotice }}</p>
      <p v-if="templateError" class="error-banner" role="alert">{{ templateError }}</p>
      <dl class="details-grid"><div><dt>Assigned to</dt><dd class="row wrap"><span v-for="member in assignees(current)" :key="member.id" class="row"><span class="avatar" aria-hidden="true">{{ initials(member.name) }}</span>{{ member.name }}</span><span v-if="!assignees(current).length">{{ assigneeLabel(current) }}</span></dd></div><div><dt>Deadline · UTC+8</dt><dd :class="{ 'danger-text': isOverdue(current) }">{{ formatDateTime(current.deadline) }}</dd></div><div><dt>{{ current.status === 'SCHEDULED' ? 'Scheduled start' : 'Started' }}</dt><dd>{{ formatDateTime(current.status === 'SCHEDULED' ? current.scheduled_at : current.started_at) }}</dd></div><div><dt>Created by</dt><dd>{{ current.creator?.name || 'Workspace member' }} · {{ formatDateTime(current.created_at) }}</dd></div></dl>
      <section class="stack" style="gap:10px"><h3>Instructions</h3><p class="instructions">{{ current.description || (canManageTask(current,auth.user) ? 'No instructions added. Open the editor to add context or reference links.' : 'No instructions added. Ask your team lead for context or reference links.') }}</p></section>
      <section v-if="current.status === 'DONE'" class="notice"><CircleCheck aria-hidden="true" /><div><strong>Completed {{ formatDateTime(current.completed_at) }}</strong><p style="white-space:pre-wrap">{{ current.completion_note || 'No completion note added.' }}</p></div></section>
      <section class="stack" style="gap:12px">
        <div class="section-header"><h3 class="row"><Paperclip aria-hidden="true" />Files <span class="badge">{{ current.attachments?.length || 0 }}</span></h3><button v-if="canActOnTask(current,auth.user)" class="btn" :disabled="uploading" @click="fileInput?.click()"><LoaderCircle v-if="uploading" class="spinner" aria-hidden="true" /><Upload v-else aria-hidden="true" />{{ uploading ? 'Uploading…' : 'Add file' }}</button><input ref="fileInput" type="file" class="sr-only" tabindex="-1" aria-label="Choose file to attach" @change="handleFileUpload" /></div>
        <p class="small muted">Attach reference material or finished work. Maximum file size: 20 MB. Allowed: PDF, images, video, audio, Office documents, text, CSV and ZIP.</p>
        <p v-if="fileError" class="error-banner" role="alert">{{ fileError }}</p>
        <div v-for="attachment in current.attachments" :key="attachment.id" class="panel row" style="padding:12px"><File aria-hidden="true" /><div class="grow"><strong style="overflow-wrap:anywhere">{{ attachment.file_name }}</strong><p class="small muted">{{ formatFileSize(attachment.file_size) }} · {{ formatDateTime(attachment.created_at) }}</p></div><a :href="attachment.url" class="btn" target="_blank" rel="noopener noreferrer" :aria-label="'Open ' + attachment.file_name + ' in a new tab'"><ExternalLink aria-hidden="true" />Open</a><button v-if="canRemoveFile(attachment)" class="btn btn-ghost" style="color:var(--danger)" :disabled="removingFile === attachment.id" :aria-label="'Remove ' + attachment.file_name" @click="removeFile(attachment)"><Trash2 aria-hidden="true" />{{ removingFile === attachment.id ? 'Removing…' : 'Remove' }}</button></div>
        <p v-if="!current.attachments?.length" class="instructions muted small">No files yet. Add a file when your team needs extra context.</p>
      </section>
      <section class="stack" style="gap:12px" aria-label="Comments">
        <h3 class="row"><MessageSquare aria-hidden="true" />Comments <span class="badge">{{ current.comments?.length || 0 }}</span></h3>
        <ul v-if="current.comments?.length" class="stack" style="gap:10px;list-style:none;padding:0;margin:0"><li v-for="comment in current.comments" :key="comment.id" class="panel" style="padding:12px"><p class="row small"><strong>{{ comment.user?.name || 'Workspace member' }}</strong><span class="muted">{{ formatDateTime(comment.created_at) }}</span></p><p style="white-space:pre-wrap;overflow-wrap:anywhere;margin-top:6px">{{ comment.body }}</p></li></ul>
        <p v-else class="instructions muted small">No comments yet. Ask a question or share an update with everyone on this task.</p>
        <form class="stack" style="gap:8px" @submit.prevent="postComment">
          <div class="field"><label for="task-comment" class="sr-only">Add a comment</label><textarea id="task-comment" v-model="commentBody" rows="2" maxlength="2000" placeholder="Write a comment…" @keydown.ctrl.enter.prevent="postComment" @keydown.meta.enter.prevent="postComment" /></div>
          <p v-if="commentError" class="field-error" role="alert">{{ commentError }}</p>
          <div class="row" style="justify-content:flex-end"><button class="btn" type="submit" :disabled="posting || !commentBody.trim()"><LoaderCircle v-if="posting" class="spinner" aria-hidden="true" /><Send v-else aria-hidden="true" />{{ posting ? 'Posting…' : 'Post comment' }}</button></div>
        </form>
      </section>
      <section class="stack" style="gap:16px"><h3 class="row"><History aria-hidden="true" />Activity</h3><ol class="timeline"><li v-for="activity in current.activities" :key="activity.id"><strong>{{ formatActivity(activity) }}</strong><p v-if="activity.metadata?.note || activity.metadata?.completion_note" class="muted">{{ activity.metadata.note || activity.metadata.completion_note }}</p><time class="muted small" :datetime="activity.created_at">{{ formatDateTime(activity.created_at) }}</time></li></ol><p v-if="!current.activities?.length" class="small muted">No activity recorded yet.</p></section>
      <div v-if="canManageTask(current,auth.user)" class="row wrap" style="border-top:1px solid var(--line);padding-top:16px"><button class="btn" @click="beginEdit"><Pencil aria-hidden="true" />Edit details</button><button class="btn" @click="duplicate"><Copy aria-hidden="true" />Duplicate</button><button class="btn" :disabled="savingTemplate" @click="saveTemplate"><BookmarkPlus aria-hidden="true" />{{ savingTemplate ? 'Saving…' : 'Save as template' }}</button></div>
      <div v-if="confirmDelete" class="error-banner" role="alert"><div class="grow"><strong>Delete this task permanently?</strong><p>This removes the task, its files and activity. This action cannot be undone.</p><div class="row wrap" style="margin-top:12px"><button class="btn" :disabled="deleting" @click="confirmDelete = false">Keep task</button><button class="btn btn-danger" :disabled="deleting" @click="handleDelete">{{ deleting ? 'Deleting…' : 'Yes, delete task' }}</button></div></div></div>
    </div>
    <template #footer>
      <button v-if="auth.isCeo && current" class="btn btn-ghost" :disabled="saving || deleting" style="color:var(--danger)" @click="confirmDelete = !confirmDelete"><Trash2 aria-hidden="true" />Delete task</button><span class="grow" />
      <template v-if="current && canActOnTask(current,auth.user)">
        <button v-if="current.status === 'SCHEDULED'" class="btn btn-soft" :disabled="tasks.pending[current.id] || editing || uploading" @click="tasks.startTask(current.id)"><Play aria-hidden="true" />Start task</button>
        <button v-if="current.status !== 'DONE'" class="btn btn-primary" :disabled="tasks.pending[current.id] || editing || uploading" @click="$emit('complete',current)"><Check aria-hidden="true" />Complete task</button>
        <button v-else class="btn btn-primary" :disabled="tasks.pending[current.id] || editing || uploading" @click="tasks.reopenTask(current.id)"><RotateCcw aria-hidden="true" />Reopen task</button>
      </template>
    </template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { Share2, LoaderCircle, CircleCheck, Paperclip, Upload, File, ExternalLink, History, Pencil, Copy, BookmarkPlus, Trash2, Play, Check, RotateCcw, MessageSquare, Send } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { useTeamStore } from '@/stores/teams'
import api from '@/services/api'
import { toast } from '@/stores/toast'
import { assignees, assigneeLabel, initials, formatDateTime, isOverdue, statusClass, statusLabel, priorityClass, canActOnTask, canManageTask, toLocalInput, inputToIso, parseDate, errorMessage } from '@/utils/tasks'
import type { Task, TaskActivity, TaskAttachment, TaskPriority, User } from '@/types'
const props = defineProps<{ isOpen: boolean; task: Task | null }>(), emit = defineEmits(['close','complete','share-messenger','duplicate'])
const auth = useAuthStore(), tasks = useTaskStore(), teams = useTeamStore()
const current = computed(() => tasks.currentTask?.id === props.task?.id ? tasks.currentTask : props.task)
const editing = ref(false), saving = ref(false), uploading = ref(false), confirmDelete = ref(false), deleting = ref(false), savingTemplate = ref(false)
const fileInput = ref<HTMLInputElement | null>(null), editError = ref(''), editErrors = ref<Record<string,string>>({}), editErrorSummary = ref<HTMLElement | null>(null)
const actionNotice = ref(''), templateError = ref('')
const commentBody = ref(''), posting = ref(false), commentError = ref(''), fileError = ref(''), removingFile = ref<number | null>(null)
function canRemoveFile(attachment: TaskAttachment) { return !!current.value && (attachment.uploaded_by === auth.user?.id || canManageTask(current.value, auth.user)) }
async function removeFile(attachment: TaskAttachment) {
  if (!current.value || removingFile.value) return
  if (!window.confirm('Remove "' + attachment.file_name + '" from this task?')) return
  removingFile.value = attachment.id; fileError.value = ''
  try { await api.delete('/attachments/' + attachment.id); await tasks.fetchTask(current.value.id); actionNotice.value = 'File removed.' }
  catch (err) { fileError.value = errorMessage(err) }
  finally { removingFile.value = null }
}
async function postComment() {
  const body = commentBody.value.trim()
  if (!current.value || !body || posting.value) return
  posting.value = true; commentError.value = ''
  try { await api.post('/tasks/' + current.value.id + '/comments', { body }); commentBody.value = ''; await tasks.fetchTask(current.value.id) }
  catch (err) { commentError.value = errorMessage(err) }
  finally { posting.value = false }
}
const edit = reactive({ title: '', description: '', priority: 'normal' as TaskPriority, deadline: '', team_id: '' as string | number, assignee_ids: [] as number[] })
const editMembers = ref<User[]>([]), editMembersLoading = ref(false), editMembersError = ref('')
let editMemberRequest = 0
watch(() => props.isOpen, open => { if (open) { commentBody.value = ''; commentError.value = ''; fileError.value = ''; editing.value = false; confirmDelete.value = false; editError.value = ''; editErrors.value = {}; actionNotice.value = ''; templateError.value = ''; tasks.mutationError = '' } else { ++editMemberRequest; editMembersLoading.value = false } })
async function beginEdit() { if (!current.value) return; Object.assign(edit,{ title: current.value.title, description: current.value.description || '', priority: current.value.priority, deadline: toLocalInput(current.value.deadline), team_id: current.value.team_id || '', assignee_ids: assignees(current.value).map(member => member.id) }); editing.value = true; void loadEditMembers(); await nextTick(); document.getElementById('edit-title')?.focus() }
function changeEditTeam() { edit.assignee_ids = []; void loadEditMembers() }
async function loadEditMembers() {
  const request = ++editMemberRequest
  editMembersLoading.value = true; editMembersError.value = ''
  try {
    const response = await api.get(edit.team_id ? '/teams/' + edit.team_id : '/users')
    if (request !== editMemberRequest || !current.value || !props.isOpen || !editing.value) return
    const members: User[] = edit.team_id ? response.data.data.members : response.data.data
    editMembers.value = [...members,...assignees(current.value!).filter(member => edit.assignee_ids.includes(member.id) && !members.some(existing => existing.id === member.id))]
  } catch (err) { if (request === editMemberRequest) editMembersError.value = errorMessage(err) }
  finally { if (request === editMemberRequest) editMembersLoading.value = false }
}
async function saveChanges() {
  if (!current.value || saving.value || editMembersLoading.value || editMembersError.value) return
  editError.value = ''; editErrors.value = {}
  if (!edit.title.trim()) editErrors.value.title = 'Enter a task title.'
  if (current.value.status === 'SCHEDULED' && current.value.scheduled_at && edit.deadline && parseDate(edit.deadline) < parseDate(current.value.scheduled_at)) editErrors.value.deadline = 'The deadline must come after the scheduled start.'
  if (Object.keys(editErrors.value).length) { editError.value = 'Check the highlighted fields.'; await nextTick(); editErrorSummary.value?.focus(); return }
  saving.value = true
  const result = await tasks.updateTask(current.value.id,{ title: edit.title.trim(), description: edit.description || null, priority: edit.priority, deadline: inputToIso(edit.deadline), team_id: edit.team_id ? Number(edit.team_id) : null, assignee_ids: edit.assignee_ids, assigned_to: edit.assignee_ids[0] || null })
  saving.value = false
  if (result) { editing.value = false; actionNotice.value = 'Changes saved.'; await tasks.fetchTask(result.id) }
  else { editErrors.value = tasks.validationErrors; editError.value = tasks.mutationError; await nextTick(); editErrorSummary.value?.focus() }
}
function duplicate() { if (!current.value) return; emit('duplicate',{ title: current.value.title + ' (copy)', description: current.value.description, team_id: current.value.team_id, assignee_ids: assignees(current.value).map(member => member.id), priority: current.value.priority }) }
async function saveTemplate() { if (!current.value || savingTemplate.value) return; savingTemplate.value = true; templateError.value = ''; actionNotice.value = ''; try { await api.post('/templates',{ title: current.value.title, description: current.value.description, default_instructions: current.value.description, team_id: current.value.team_id, priority: current.value.priority }); actionNotice.value = 'Template saved to your workflow library.'; toast.success(actionNotice.value) } catch (err) { templateError.value = errorMessage(err) } finally { savingTemplate.value = false } }
async function handleFileUpload(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; if (!file || !current.value) return; uploading.value = true; actionNotice.value = ''; const result = await tasks.uploadAttachment(current.value.id,file); if (result) actionNotice.value = 'File uploaded.'; uploading.value = false; input.value = '' }
async function handleDelete() { if (!current.value || deleting.value) return; deleting.value = true; const success = await tasks.deleteTask(current.value.id); deleting.value = false; if (success) emit('close') }
function formatFileSize(bytes: number) { return bytes < 1024 ? bytes + ' B' : bytes < 1048576 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / 1048576).toFixed(1) + ' MB' }
function formatActivity(activity: TaskActivity) { const actions: Record<string,string> = { created: 'created the task', started: 'started work', completed: 'completed the task', reopened: 'reopened the task', updated: activity.metadata?.assignment_changed ? 'updated the owner or team' : 'updated the details', attachment_added: 'added a file', attachment_removed: 'removed a file' }; return (activity.user?.name || 'System') + ' ' + (actions[activity.action] || activity.action) }
</script>
