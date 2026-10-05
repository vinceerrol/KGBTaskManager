<template>
  <BaseModal :is-open="isOpen" :title="item ? (mode === 'template' ? 'Edit template' : 'Edit recurring workflow') : mode === 'template' ? 'Create a reusable template' : 'Create a recurring workflow'" :description="mode === 'template' ? 'Give repeatable work a reliable starting point.' : 'Set a rhythm. The workspace scheduler creates each task for you.'" :busy="saving" @close="$emit('close')">
    <form id="workflow-form" class="stack" novalidate @submit.prevent="submit">
      <div v-if="error" ref="summary" class="error-banner" role="alert" tabindex="-1"><CircleAlert aria-hidden="true" /><span>{{ error }}</span></div>
      <div class="field"><label for="workflow-title">{{ mode === 'template' ? 'Template name' : 'Task title' }}</label><input id="workflow-title" v-model="form.title" autofocus maxlength="255" placeholder="e.g. Weekly campaign review" :aria-invalid="!!errors.title" aria-describedby="workflow-title-error" /><p v-if="errors.title" id="workflow-title-error" class="field-error">{{ errors.title }}</p></div>
      <div class="field"><label for="workflow-description">Instructions</label><textarea id="workflow-description" v-model="form.description" rows="4" placeholder="Write a repeatable brief, checklist or definition of done." /></div>
      <div class="form-grid"><div class="field"><label for="workflow-team">Team</label><select id="workflow-team" v-model="form.team_id" @change="loadMembers()"><option value="">General / no team</option><option v-for="team in teams.teams" :key="team.id" :value="team.id">{{ team.name }}</option></select></div><div class="field"><label for="workflow-priority">Priority</label><select id="workflow-priority" v-model="form.priority"><option value="normal">Normal</option><option value="high">High priority</option><option value="urgent">Urgent</option></select></div></div>
      <template v-if="mode === 'recurring'">
        <div class="field"><label for="workflow-owner">Owner</label><select id="workflow-owner" v-model="form.assigned_to" :disabled="membersLoading"><option value="">{{ form.team_id ? 'Entire team' : 'Unassigned' }}</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.name }} · {{ member.active_tasks_count || 0 }} active</option></select><p v-if="membersError" class="field-error" role="alert">{{ membersError }} <button type="button" class="text-link" @click="loadMembers()">Retry</button></p></div>
        <div class="form-grid"><div class="field"><label for="workflow-frequency">Repeat</label><select id="workflow-frequency" v-model="form.frequency"><option value="daily">Every day</option><option value="weekly">Every week</option><option value="monthly">Every month</option></select></div><div class="field"><label for="workflow-time">Time (UTC+8)</label><input id="workflow-time" v-model="form.scheduled_time" type="time" :aria-invalid="!!errors.scheduled_time" aria-describedby="workflow-time-error" /><p v-if="errors.scheduled_time" id="workflow-time-error" class="field-error">{{ errors.scheduled_time }}</p></div></div>
        <div class="notice"><Repeat2 aria-hidden="true" /><p>{{ scheduleSummary }}</p></div>
        <p class="small muted">The first task starts at the next matching time. Weekly and monthly schedules use today's weekday or day of month. When the scheduler resumes after downtime, one task is generated and older missed runs are skipped.</p>
      </template>
    </form>
    <template #footer><button class="btn btn-ghost" :disabled="saving" @click="$emit('close')">Cancel</button><button class="btn btn-primary" form="workflow-form" type="submit" :disabled="saving || membersLoading"><LoaderCircle v-if="saving" class="spinner" aria-hidden="true" /><Plus v-else aria-hidden="true" />{{ saving ? 'Saving…' : item ? 'Save changes' : mode === 'template' ? 'Save template' : 'Create workflow' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, reactive, watch, computed, nextTick } from 'vue'
import { CircleAlert, Repeat2, LoaderCircle, Plus } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { useTeamStore } from '@/stores/teams'
import api from '@/services/api'
import { fieldErrors, errorMessage } from '@/utils/tasks'
import type { User, TaskPriority, TaskTemplate, RecurringTask } from '@/types'
const props = defineProps<{ isOpen:boolean; mode:'template' | 'recurring'; item?: TaskTemplate | RecurringTask | null }>(), emit = defineEmits(['close','saved'])
const teams = useTeamStore(), saving = ref(false), error = ref(''), errors = ref<Record<string,string>>({}), summary = ref<HTMLElement | null>(null)
const form = reactive({ title:'',description:'',team_id:'' as number | string,priority:'normal' as TaskPriority,assigned_to:'' as number | string,frequency:'daily',scheduled_time:'09:00' })
const members = ref<User[]>([]), membersLoading = ref(false), membersError = ref('')
let request = 0
const scheduleSummary = computed(() => {
  const weekday = new Date().toLocaleDateString('en',{ timeZone:'Asia/Manila',weekday:'long' })
  const day = new Date().toLocaleDateString('en',{ timeZone:'Asia/Manila',day:'numeric' })
  return form.frequency === 'daily' ? 'Every day at ' + form.scheduled_time + ', Asia/Manila.' : form.frequency === 'weekly' ? 'Every ' + weekday + ' at ' + form.scheduled_time + ', Asia/Manila.' : 'Day ' + day + ' of each month at ' + form.scheduled_time + '. Shorter months use their final day.'
})
watch(() => props.isOpen, open => {
  if (!open) { ++request; return }
  const item = props.item
  const instructions = item ? ('default_instructions' in item ? item.default_instructions || item.description : item.description) || '' : ''
  Object.assign(form,{ title:item?.title || '',description:instructions,team_id:item?.team_id || '',priority:item?.priority || 'normal',assigned_to:'',frequency:item && 'frequency' in item ? item.frequency : 'daily',scheduled_time:item && 'scheduled_time' in item ? item.scheduled_time : '09:00' })
  errors.value = {}; error.value = ''; void teams.fetchTeams()
  if (props.mode === 'recurring') void loadMembers(item && 'assigned_to' in item ? item.assigned_to || '' : '')
})
async function loadMembers(keep: number | string = '') {
  form.assigned_to = keep
  if (props.mode !== 'recurring') return
  const current = ++request; membersLoading.value = true; members.value = []; membersError.value = ''
  try { const res = await api.get(form.team_id ? '/teams/' + form.team_id : '/users'); if (current === request) members.value = form.team_id ? (res.data.data || res.data).members || [] : res.data.data || res.data }
  catch (err) { if (current === request) membersError.value = errorMessage(err) }
  finally { if (current === request) membersLoading.value = false }
}
async function submit() {
  if (saving.value) return
  errors.value = {}; error.value = ''
  if (!form.title.trim()) errors.value.title = 'Enter a name for this workflow.'
  if (props.mode === 'recurring' && !/^\d{2}:\d{2}$/.test(form.scheduled_time)) errors.value.scheduled_time = 'Choose a valid time.'
  if (Object.keys(errors.value).length) { error.value = 'Check the highlighted fields before saving.'; await nextTick(); summary.value?.focus(); return }
  saving.value = true
  try {
    const base = props.mode === 'template' ? '/templates' : '/recurring-tasks'
    const payload = { title:form.title.trim(),description:form.description || null,default_instructions:props.mode === 'template' ? form.description || null : undefined,team_id:form.team_id ? Number(form.team_id) : null,priority:form.priority,assigned_to:form.assigned_to ? Number(form.assigned_to) : null,frequency:form.frequency,scheduled_time:form.scheduled_time }
    if (props.item) await api.put(base + '/' + props.item.id, payload); else await api.post(base, payload)
    emit('saved'); emit('close')
  } catch (err) { errors.value = fieldErrors(err); error.value = errorMessage(err); await nextTick(); summary.value?.focus() }
  finally { saving.value = false }
}
</script>
