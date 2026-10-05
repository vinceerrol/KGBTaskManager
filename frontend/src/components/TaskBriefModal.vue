<template>
  <BaseModal :is-open="isOpen" title="Write a task" description="Describe the work. Review the task taking shape beside it." wide dialog-class="task-brief-modal" :busy="submitting" :dialog-style="viewportStyle" @close="close">
    <div v-if="hasSavedDraft" class="notice brief-resume"><FilePenLine aria-hidden="true" /><div class="grow"><strong>You have an unfinished task draft</strong><div class="row wrap"><button type="button" class="btn btn-soft" :disabled="loading || !context" @click="restoreDraft">Resume draft</button><button type="button" class="btn btn-ghost" @click="discardDraft">Start fresh</button></div></div></div>
    <div v-if="contextError" class="error-banner" role="alert"><span class="grow">{{ contextError }}</span><button type="button" class="btn" @click="loadContext">Retry</button></div>
    <div class="brief-layout">
      <section ref="writingPane" class="brief-writing" aria-label="Write the new task request">
        <p class="eyebrow">Your request <span class="badge">English + Taglish</span></p>
        <TaskBriefEditor ref="editor" :text="brief" :mentions="mentions" :context="context" :team-id="form.team_id" :disabled="submitting || loading || hasSavedDraft" @change="changeBrief" @submit="submit" />
        <div class="brief-interpret-status" role="status" aria-live="polite"><LoaderCircle v-if="loading || interpreting" class="spinner" aria-hidden="true" /><Check v-else-if="brief.trim()" aria-hidden="true" /><FilePenLine v-else aria-hidden="true" /><span>{{ interpretationStatus }}</span></div>
        <p v-if="context?.ai_enabled" class="small muted">Your workspace uses {{ context.ai_provider === 'groq' ? 'Groq' : 'OpenAI' }} to help interpret this request.</p>
        <div v-if="visibleIssues.length" class="brief-issues" aria-label="Draft details to review"><div v-for="(issue,index) in visibleIssues" :key="issue.field + '-' + index" class="notice" :class="{ 'brief-blocking': issue.blocking }"><CircleHelp aria-hidden="true" /><p class="small grow">{{ issue.message }}</p><button v-if="issue.field !== 'brief' && issue.field !== 'mentions'" type="button" class="text-link" @click="focusField(issue.field)">Review</button></div></div>
        <p class="small muted brief-writing-note">Nothing is created until you choose Create task. Preview edits keep your paragraph unchanged.</p>
      </section>
      <section ref="previewPane" class="brief-preview" aria-labelledby="brief-preview-title">
        <div class="brief-preview-header"><div><p class="eyebrow">Live preview</p><h3 id="brief-preview-title">Create a task</h3></div><span class="badge badge-brand">Unsaved draft</span></div>
        <form id="task-brief-form" novalidate class="stack" @submit.prevent="submit" @keydown="submitShortcut">
          <div v-if="Object.keys(errors).length || mutationError" ref="errorSummary" class="error-banner" tabindex="-1" role="alert"><div class="grow"><strong>Review these details before creating.</strong><p v-if="mutationError">{{ mutationError }}</p><ul v-if="Object.keys(errors).length"><li v-for="(message,key) in errors" :key="key"><button type="button" class="brief-error-link" @click="focusField(String(key))">{{ message }}</button></li></ul></div></div>
          <fieldset :disabled="submitting || loading || hasSavedDraft" class="brief-fields stack">
            <legend class="sr-only">New task details</legend>
            <div class="field"><label for="brief-title">Task title <span class="muted small">Required</span><span v-if="edited.title" class="brief-edited">Edited</span></label><input id="brief-title" :value="form.title" maxlength="255" placeholder="Your task title appears here" :aria-invalid="!!errors.title" aria-describedby="brief-title-error" @input="edit('title', value($event))" /><p v-if="errors.title" id="brief-title-error" class="field-error">{{ errors.title }}</p></div>
            <div class="field"><label for="brief-description">Instructions <span class="muted small">Optional</span><span v-if="edited.description" class="brief-edited">Edited</span></label><textarea id="brief-description" :value="form.description" rows="3" placeholder="Instructions from your request" @input="edit('description', value($event))" /></div>
            <div class="field"><label for="brief-team">Team<span v-if="edited.team_id" class="brief-edited">Edited</span></label><select id="brief-team" :value="form.team_id" :aria-invalid="!!errors.team_id" @change="edit('team_id', value($event) ? Number(value($event)) : '')"><option value="">General / no team</option><option v-for="team in context?.teams || []" :key="team.id" :value="team.id">{{ team.name }}</option></select><p v-if="errors.team_id" class="field-error">{{ errors.team_id }}</p></div>
            <fieldset class="brief-owners stack">
              <legend>Assign to <span class="muted small">Select one or more people</span><span v-if="edited.assignee_ids" class="brief-edited">Edited</span></legend>
              <div class="row wrap"><button type="button" class="btn" :class="{ 'btn-soft': !form.assignee_ids.length && ownership !== 'unresolved' }" :aria-pressed="!form.assignee_ids.length && ownership !== 'unresolved'" @click="chooseOwnership">{{ form.team_id ? 'Entire team' : 'Leave unassigned' }}</button><button v-if="members.length" type="button" class="btn btn-ghost" @click="setOwners(members.map(member => member.id))">Select all</button></div>
              <div class="form-grid brief-member-grid"><label v-for="member in members" :key="member.id" class="member-choice"><input type="checkbox" :checked="form.assignee_ids.includes(member.id)" :value="member.id" @change="toggleOwner(member.id, checked($event))" /><span class="grow"><strong>{{ member.name }}</strong><span v-if="!form.team_id" class="muted small" style="display:block">{{ member.teams.map(t => t.name).join(', ') || 'General' }}</span></span></label></div>
              <p class="field-hint">{{ ownershipLabel }}</p><p v-if="errors.assignee_ids || errors.assignment_scope" class="field-error">{{ errors.assignee_ids || errors.assignment_scope }}</p>
            </fieldset>
            <div class="form-grid">
              <div class="field"><label for="brief-priority">Priority<span v-if="edited.priority" class="brief-edited">Edited</span></label><select id="brief-priority" :value="form.priority" @change="edit('priority', value($event))"><option value="normal">Normal</option><option value="high">High priority</option><option value="urgent">Urgent</option></select></div>
              <div class="field"><label for="brief-start">Start<span v-if="edited.start_type" class="brief-edited">Edited</span></label><select id="brief-start" :value="form.start_type" @change="editStart(value($event))"><option value="now">Start now</option><option value="scheduled">Schedule for later</option></select></div>
              <div v-if="form.start_type === 'scheduled'" class="field"><label for="brief-scheduled">Start date & time<span v-if="edited.scheduled_at" class="brief-edited">Edited</span></label><input id="brief-scheduled" :value="form.scheduled_at" type="datetime-local" :aria-invalid="!!errors.scheduled_at" @input="edit('scheduled_at', value($event))" /><p v-if="errors.scheduled_at" class="field-error">{{ errors.scheduled_at }}</p><p v-else-if="form.scheduled_at" class="field-hint">{{ formatDateTime(form.scheduled_at) }}</p></div>
              <div class="field"><label for="brief-deadline">Deadline <span class="muted small">Optional</span><span v-if="edited.deadline" class="brief-edited">Edited</span></label><input id="brief-deadline" :value="form.deadline" type="datetime-local" :aria-invalid="!!errors.deadline" @input="edit('deadline', value($event))" /><p v-if="errors.deadline" class="field-error">{{ errors.deadline }}</p><p v-else-if="form.deadline" class="field-hint">{{ formatDateTime(form.deadline) }}{{ dateOnly && !edited.deadline ? ' · End of calendar day' : '' }}{{ deadlineInherited && !edited.deadline && !edited.scheduled_at && !edited.start_type ? ' · Uses the start date' : '' }}</p></div>
            </div>
            <div class="notice"><Clock3 aria-hidden="true" /><p class="small">Dates use Asia/Manila (UTC+8). {{ form.start_type === 'now' ? 'This task will start in progress.' : 'The task will remain scheduled until its start time.' }}</p></div>
          </fieldset>
        </form>
      </section>
    </div>
    <template #footer><span class="small muted grow">{{ hasSavedDraft ? 'Resume or start a fresh draft' : saved ? 'Draft saved · Ctrl / ⌘ Enter' : 'Ctrl / ⌘ Enter to create' }}</span><button type="button" class="btn btn-ghost" :disabled="submitting" @click="close">Cancel</button><button type="submit" form="task-brief-form" class="btn btn-primary" :disabled="submitting || loading || !context || hasSavedDraft || !brief.trim() || !form.title.trim() || hasBlockingIssue"><LoaderCircle v-if="submitting" class="spinner" aria-hidden="true" /><Plus v-else aria-hidden="true" />{{ submitting ? 'Creating…' : 'Create task' }}</button></template>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, reactive, computed, watch, nextTick, onBeforeUnmount, onMounted } from 'vue'
import { FilePenLine, LoaderCircle, Check, CircleHelp, Clock3, Plus } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import TaskBriefEditor from '@/components/TaskBriefEditor.vue'
import api from '@/services/api'
import { isAxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { interpretBrief, mergeBriefFields, retainBriefSafeguards, validMentions } from '@/utils/taskBrief'
import { formatDateTime, inputToIso, parseDate, errorMessage } from '@/utils/tasks'
import { emptyBriefForm } from '@/types/taskBrief'
import type { BriefContext, BriefMention, BriefIssue, DraftField, TaskBriefForm, BriefInterpretation } from '@/types/taskBrief'
const props=defineProps<{isOpen:boolean}>(), emit=defineEmits(['close','created'])
const auth=useAuthStore(), tasks=useTaskStore(), brief=ref(''), mentions=ref<BriefMention[]>([]), form=reactive(emptyBriefForm()), edited=reactive<Partial<Record<DraftField,boolean>>>({})
const context=ref<BriefContext|null>(null), loading=ref(false), interpreting=ref(false), submitting=ref(false), contextError=ref(''), mutationError=ref(''), errors=ref<Record<string,string>>({})
const issues=ref<BriefIssue[]>([]), ownership=ref<BriefInterpretation['ownership']>('unassigned'), dateOnly=ref(false), deadlineInherited=ref(false), hasSavedDraft=ref(false), saved=ref(false), engine=ref<'local'|'ai'|'unavailable'|'limited'>('local')
const editor=ref<InstanceType<typeof TaskBriefEditor>|null>(null), errorSummary=ref<HTMLElement|null>(null), viewportStyle=ref<Record<string,string>>({})
const writingPane=ref<HTMLElement|null>(null), previewPane=ref<HTMLElement|null>(null)
let session=0, revision=0, creationKey='', reference=new Date(), openedFor:number|undefined, interpretingRequest:AbortController|null=null, interpretationTimer:ReturnType<typeof setTimeout>|undefined, running=false, rerun=false, saving=false
let lastInterpretationAt=0, cooldownUntil=0
const value=(event:Event)=>(event.target as HTMLInputElement).value, checked=(event:Event)=>(event.target as HTMLInputElement).checked
const members=computed(()=>context.value?.people.filter(person=>!form.team_id||person.teams.some(team=>team.id===form.team_id)||form.assignee_ids.includes(person.id))||[])
const visibleIssues=computed(()=>{
  const orderMessages=['The deadline is before the scheduled start. Specify a later deadline or its intended date.','The deadline must come after the start.']
  const visible=issues.value.filter(issue=>!(issue.field==='deadline'&&orderMessages.includes(issue.message))).filter(issue=>issue.field==='brief'||issue.field==='mentions' ? issue.field==='brief'||!(edited.assignee_ids||edited.team_id) : !edited[issue.field])
  if(form.start_type==='scheduled'&&form.scheduled_at&&form.deadline&&form.deadline<form.scheduled_at)visible.push({field:'deadline',message:'The deadline is before the scheduled start. Specify a later deadline or its intended date.',blocking:true})
  if(form.team_id&&!form.assignee_ids.length&&ownership.value!=='team'&&!visible.some(issue=>issue.field==='assignee_ids'))visible.push({field:'assignee_ids',message:'Select individual owners or explicitly choose Entire team in the preview.',blocking:true})
  return visible
})
const hasBlockingIssue=computed(()=>visibleIssues.value.some(issue=>issue.blocking)||Boolean(form.team_id&&!form.assignee_ids.length&&ownership.value!=='team'))
const ownershipLabel=computed(()=>form.assignee_ids.length ? form.assignee_ids.length+' selected '+(form.assignee_ids.length===1?'member will own this task.':'members will own this task.') : form.team_id ? ownership.value==='team'?'The entire team will own this task and receive assignment notifications.':'Choose individual owners or explicitly select Entire team.' : 'This task will remain unassigned until an owner is added.')
const interpretationStatus=computed(()=>loading.value?'Loading your people and teams…':interpreting.value?'Updating the live draft…':!brief.value.trim()?'Your new task will take shape as you write.':engine.value==='ai'?'Draft interpreted · review before creating':engine.value==='limited'?'AI rate limit reached. You can keep writing and review the local draft.':engine.value==='unavailable'?'AI is unavailable. Review the local draft or edit its fields.':'Live draft · review the details before creating')
function key(){return 'kcg_task_brief_draft_'+openedFor}
function save(){
  if(!saving||!openedFor||hasSavedDraft.value)return
  if(!brief.value.trim()){try{localStorage.removeItem(key())}catch{}saved.value=false;return}
  try{localStorage.setItem(key(),JSON.stringify({version:1,text:brief.value,mentions:mentions.value,form:{...form},edited:{...edited},ownership:ownership.value,reference_at:reference.toISOString(),creation_key:creationKey,saved_at:Date.now()}));saved.value=true}catch{saved.value=false}
}
function clearEdited(){for(const field of Object.keys(edited) as DraftField[])delete edited[field]}
function reset(){brief.value='';mentions.value=[];Object.assign(form,emptyBriefForm());clearEdited();ownership.value='unassigned';issues.value=[];errors.value={};mutationError.value='';dateOnly.value=false;deadlineInherited.value=false;saved.value=false;engine.value='local';submitting.value=false;creationKey=crypto.randomUUID();revision=0;lastInterpretationAt=0;cooldownUntil=0}
watch(()=>props.isOpen,async open=>{
  const current=++session;clearTimeout(interpretationTimer);interpretingRequest?.abort();interpreting.value=false;running=false;rerun=false
  if(!open){saving=false;context.value=null;brief.value='';mentions.value=[];return}
  openedFor=auth.user?.id;reset();saving=true;hasSavedDraft.value=false
  try{const raw=localStorage.getItem(key());if(raw){const existing=JSON.parse(raw);hasSavedDraft.value=existing.version===1&&Date.now()-existing.saved_at<14*86400000}}catch{}
  await loadContext();if(current!==session)return;await nextTick();writingPane.value?.scrollTo({top:0});previewPane.value?.scrollTo({top:0});writingPane.value?.closest('.modal-body')?.scrollTo({top:0});if(!hasSavedDraft.value)editor.value?.focus()
},{immediate:true})
watch([()=>({...form}),()=>({...edited}),ownership],save,{deep:true})
async function loadContext(){
  const current=session;loading.value=true;contextError.value=''
  try{const response=await api.get('/task-drafts/context');if(current!==session||auth.user?.id!==openedFor)return;context.value=response.data.data;reference=new Date(context.value!.reference_at);if(brief.value)applyLocal()}
  catch(err){if(current===session)contextError.value=errorMessage(err)}
  finally{if(current===session)loading.value=false}
}
function changeBrief(next:{text:string;mentions:BriefMention[]}){
  brief.value=next.text;mentions.value=next.mentions;revision++;errors.value={};mutationError.value='';applyLocal();save();scheduleInterpretation()
}
function applyLocal(){
  const result=interpretBrief(brief.value,mentions.value,reference);Object.assign(form,mergeBriefFields(form,result.fields,edited));if(!edited.assignee_ids&&!edited.team_id)ownership.value=result.ownership;issues.value=result.issues;dateOnly.value=result.dateOnly;deadlineInherited.value=!!result.deadlineInherited;engine.value=cooldownUntil>Date.now()?'limited':'local'
}
function scheduleInterpretation(){
  clearTimeout(interpretationTimer);if(!context.value?.ai_enabled||brief.value.trim().length<20||hasSavedDraft.value||submitting.value||issues.value.some(issue=>issue.field==='mentions'&&issue.blocking))return
  if(cooldownUntil>Date.now()){engine.value='limited';return}
  const pause=context.value.ai_provider==='groq'?1500:900
  const spacing=context.value.ai_provider==='groq'?Math.max(0,lastInterpretationAt+5000-Date.now()):0
  interpretationTimer=setTimeout(()=>void interpret(),Math.max(pause,spacing))
}
async function interpret(){
  if(running){rerun=true;return}if(!props.isOpen||!context.value?.ai_enabled||submitting.value||cooldownUntil>Date.now())return
  const current=session, version=revision, raw=brief.value, tokens=[...mentions.value];running=true;rerun=false;interpreting.value=true;lastInterpretationAt=Date.now()
  const controller=new AbortController();interpretingRequest=controller
  try{
    const response=await api.post('/task-drafts/interpret',{brief:raw,mentions:tokens,revision:version,reference_at:reference.toISOString()},{signal:controller.signal})
    if(current===session&&auth.user?.id===openedFor&&response.data.retry_after>0)cooldownUntil=Date.now()+Math.min(86400,Number(response.data.retry_after))*1000
    if(current!==session||version!==revision||response.data.revision!==version||submitting.value||auth.user?.id!==openedFor)return
    const data=response.data.data
    if(response.data.status==='interpreted'&&data){const fields:TaskBriefForm={title:data.title||'',description:data.description||'',team_id:data.team_id||'',assignee_ids:data.assignee_ids,priority:data.priority,start_type:data.start_type,scheduled_at:data.scheduled_at||'',deadline:data.deadline||''};const local=interpretBrief(raw,tokens,reference);const result=retainBriefSafeguards(local,{fields,ownership:data.ownership,issues:data.issues,dateOnly:data.date_only});Object.assign(form,mergeBriefFields(form,result.fields,edited));if(!edited.assignee_ids&&!edited.team_id)ownership.value=result.ownership;issues.value=result.issues;dateOnly.value=result.dateOnly;deadlineInherited.value=!!local.deadlineInherited&&fields.deadline===local.fields.deadline&&fields.scheduled_at===local.fields.scheduled_at;engine.value='ai';save()}
    else engine.value=cooldownUntil>Date.now()?'limited':response.data.status==='local'?'local':'unavailable'
  }catch(err){if(current===session&&!controller.signal.aborted){if(isAxiosError(err)&&err.response?.status===429){cooldownUntil=Date.now()+Math.min(86400,Math.max(1,Number(err.response.headers['retry-after'])||60))*1000;engine.value='limited'}else engine.value='unavailable'}}
  finally{if(current===session){running=false;interpreting.value=false;interpretingRequest=null;if((rerun||version!==revision)&&!submitting.value)scheduleInterpretation()}}
}
function edit(field:DraftField,next:unknown){(form as Record<DraftField,unknown>)[field]=next;edited[field]=true;delete errors.value[field];mutationError.value='';if(field==='team_id'){ownership.value=form.assignee_ids.length?'people':form.team_id?'unresolved':'unassigned'}save()}
function editStart(next:string){edit('start_type',next);edited.scheduled_at=true;if(next==='now')form.scheduled_at='';save()}
function setOwners(ids:number[]){edit('assignee_ids',ids);ownership.value=ids.length?'people':form.team_id?'unresolved':'unassigned';save()}
function toggleOwner(id:number,on:boolean){setOwners(on?[...new Set([...form.assignee_ids,id])]:form.assignee_ids.filter(owner=>owner!==id))}
function chooseOwnership(){setOwners([]);ownership.value=form.team_id?'team':'unassigned';if(form.team_id)edited.team_id=true;save()}
async function restoreDraft(){
  try{const stored=JSON.parse(localStorage.getItem(key())||'null');if(!stored||stored.version!==1||typeof stored.text!=='string'||stored.text.length>6000)throw Error()
    brief.value=stored.text;mentions.value=validMentions(stored.text,Array.isArray(stored.mentions)?stored.mentions:[]).filter(m=>m.kind==='person'?context.value?.people.some(p=>p.id===m.id&&p.name===m.label):context.value?.teams.some(t=>t.id===m.id&&t.name===m.label));Object.assign(form,emptyBriefForm(),stored.form);clearEdited();Object.assign(edited,stored.edited||{});ownership.value=stored.ownership||'unassigned';if(Number.isFinite(new Date(stored.reference_at).getTime()))reference=new Date(stored.reference_at);creationKey=stored.creation_key||crypto.randomUUID();hasSavedDraft.value=false;applyLocal();save();scheduleInterpretation()
  }catch{discardDraft()}await nextTick();editor.value?.focus()
}
function discardDraft(){try{localStorage.removeItem(key())}catch{}hasSavedDraft.value=false;reference=new Date(context.value?.reference_at||Date.now());reset();void nextTick(()=>editor.value?.focus())}
function close(){save();emit('close')}
function focusField(field:string){const id=field==='scheduled_at'?'scheduled':field==='team_id'?'team':field==='assignee_ids'||field==='assignment_scope'?'owners':field==='start_type'?'start':field;const target=id==='owners'?document.querySelector<HTMLElement>('.task-brief-modal .brief-owners input, .task-brief-modal .brief-owners button'):document.getElementById('brief-'+id);target?.focus();target?.scrollIntoView({block:'nearest'})}
function submitShortcut(event:KeyboardEvent){if(!event.isComposing&&(event.ctrlKey||event.metaKey)&&event.key==='Enter'){event.preventDefault();void submit()}}
async function submit(){
  if(submitting.value||loading.value||hasSavedDraft.value||!context.value||!props.isOpen)return
  errors.value={};mutationError.value=''
  if(!brief.value.trim())errors.value.title='Write the task request first.'
  if(!form.title.trim())errors.value.title='Enter a task title.'
  if(hasBlockingIssue.value)errors.value.assignment_scope='Resolve the highlighted details before creating.'
  if(form.team_id&&!context.value?.teams.some(t=>t.id===form.team_id))errors.value.team_id='Choose an available team.'
  if(form.assignee_ids.some(id=>!context.value?.people.some(person=>person.id===id)))errors.value.assignee_ids='Choose owners currently available in your workspace.'
  if(form.start_type==='scheduled'&&(!form.scheduled_at||!Number.isFinite(parseDate(form.scheduled_at).getTime())||parseDate(form.scheduled_at).getTime()<=Date.now()))errors.value.scheduled_at='Choose a start date and time in the future.'
  if(form.deadline&&(!Number.isFinite(parseDate(form.deadline).getTime())||parseDate(form.deadline).getTime()<=Date.now()))errors.value.deadline='Choose a deadline in the future.'
  if(form.start_type==='scheduled'&&form.scheduled_at&&form.deadline&&parseDate(form.deadline)<parseDate(form.scheduled_at))errors.value.deadline='The deadline must come after the scheduled start.'
  if(Object.keys(errors.value).length){await nextTick();errorSummary.value?.focus();return}
  clearTimeout(interpretationTimer);interpretingRequest?.abort();revision++;submitting.value=true
  const payload={title:form.title.trim(),description:form.description||null,team_id:form.team_id||null,assigned_to:form.assignee_ids[0]||null,assignee_ids:[...form.assignee_ids],priority:form.priority,start_type:form.start_type,scheduled_at:form.start_type==='scheduled'?inputToIso(form.scheduled_at):null,deadline:inputToIso(form.deadline),creation_method:'brief',source_brief:brief.value,creation_key:creationKey,assignment_scope:form.assignee_ids.length?'people':form.team_id?'team':'unassigned'}
  const current=session, created=await tasks.createTask(payload)
  if(current!==session)return
  submitting.value=false
  if(created){saving=false;try{localStorage.removeItem(key())}catch{}emit('created',created);emit('close')}
  else{mutationError.value=tasks.mutationError;errors.value={...tasks.validationErrors};await nextTick();errorSummary.value?.focus()}
}
function fitViewport(){const viewport=window.visualViewport;viewportStyle.value=viewport?{'--brief-available-height':Math.floor(viewport.height-20)+'px'}:{}}
onMounted(()=>{fitViewport();window.visualViewport?.addEventListener('resize',fitViewport)})
onBeforeUnmount(()=>{clearTimeout(interpretationTimer);interpretingRequest?.abort();window.visualViewport?.removeEventListener('resize',fitViewport)})
</script>
<style>
.modal.task-brief-modal{width:min(1120px,calc(100vw - 32px));max-height:min(92dvh,var(--brief-available-height,92dvh));}
.task-brief-modal .modal-body{padding:0;display:flex;flex-direction:column;overflow:hidden}.task-brief-modal .modal-header{padding:20px 24px}.brief-layout{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);flex:1;min-height:0;overflow:hidden}
.brief-writing{padding:24px;background:var(--surface-soft);min-width:0;overflow-y:auto;display:flex;flex-direction:column;gap:16px}.brief-writing>*{flex-shrink:0}.brief-writing>.eyebrow{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:0;flex-wrap:wrap}.brief-writing>.eyebrow .badge{letter-spacing:0;text-transform:none}
.brief-preview{padding:24px;min-width:0;overflow-y:auto}.brief-preview-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}.brief-preview-header .eyebrow{margin-bottom:4px}
.brief-fields,.brief-owners{border:0;padding:0;margin:0;min-width:0}.brief-owners legend{font-size:13px;font-weight:600;margin-bottom:8px}.brief-edited{margin-left:auto;color:var(--brand);background:var(--brand-soft);border-radius:5px;padding:2px 6px;font-size:11px;font-weight:500}
.brief-preview .field>label{display:flex;align-items:center;flex-wrap:wrap;gap:6px}.brief-member-grid{gap:8px}.brief-member-grid .member-choice{padding:10px;min-width:0;overflow-wrap:anywhere}.brief-member-grid .member-choice strong{font-size:12px}
.brief-interpret-status{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);min-height:24px}.brief-interpret-status>svg{width:16px;height:16px;color:var(--brand)}
.brief-issues{display:flex;flex-direction:column;gap:8px}.brief-issues .notice{align-items:flex-start}.brief-issues .text-link{font-size:12px;flex:none;min-height:36px}.brief-issues .brief-blocking{background:var(--warning-soft);color:var(--warning)}
.brief-writing-note{margin-top:auto;padding-top:16px;border-top:1px solid var(--line)}.brief-resume{margin:20px 24px;flex-shrink:0}.brief-resume .row{margin-top:8px}.task-brief-modal>.modal-body>.error-banner{margin:20px 24px;flex-shrink:0}
.brief-error-link{background:transparent;border:0;color:inherit;text-decoration:underline;text-align:left;min-height:32px}.brief-preview .error-banner ul{padding-left:18px;margin:8px 0 0}
@media(max-width:900px){.task-brief-modal .modal-body{display:block;overflow-y:auto}.brief-layout{display:block;overflow:visible}.brief-writing,.brief-preview{overflow:visible}.brief-writing{border-bottom:1px solid var(--line)}.brief-writing-note{margin-top:0}.brief-preview-header{margin-bottom:16px}}
@media(max-width:767px){.modal.task-brief-modal{width:calc(100vw - 16px);max-height:min(94dvh,var(--brief-available-height,94dvh))}.brief-writing,.brief-preview{padding:18px}.brief-resume{margin:16px 18px}.brief-member-grid{grid-template-columns:1fr}.task-brief-modal .modal-header,.task-brief-modal .modal-footer{padding:16px 18px}}
</style>
