<template>
  <div class="page">
    <div class="page-header"><div><p class="eyebrow">Your personal workspace</p><h1>One thing at a time.</h1><p class="page-subtitle">Your assignments, shared team tasks, and a clear next step.</p></div><div class="row wrap"><button class="btn" :disabled="tasks.myLoading" @click="tasks.fetchMyTasks()"><RefreshCw :class="{ spinner:tasks.myLoading }" aria-hidden="true" />Refresh</button><button class="btn" :class="{ 'btn-soft':focusMode }" :aria-pressed="focusMode" @click="focusMode = !focusMode; tab = 'active'"><Focus aria-hidden="true" />{{ focusMode ? 'Leave focus mode' : 'Focus mode' }}</button></div></div>
    <div class="panel row wrap" style="justify-content:space-between"><div class="row wrap"><span class="badge badge-brand">{{ active.length }} active</span><span class="badge badge-danger">{{ overdueCount }} overdue</span><span class="badge badge-success">{{ completed.length }} completed</span></div><p class="small muted">Your work, ordered by what needs attention.</p></div>
    <div class="tab-strip" aria-label="Choose your work view"><button v-for="item in tabs" :key="item.value" :aria-pressed="tab === item.value" @click="tab = item.value; focusMode = false">{{ item.label }}<span class="badge">{{ item.count }}</span></button></div>
    <div v-if="tasks.myError" class="error-banner" role="alert"><CircleAlert aria-hidden="true" /><span class="grow">{{ tasks.myError }}</span><button class="btn" @click="tasks.fetchMyTasks()">Try again</button></div>
    <LoadingState v-if="tasks.myLoading && !tasks.myTasks.length" label="Loading your assignments" />
    <template v-else-if="focusMode && nextTask">
      <section class="focus-card">
        <div class="grow stack"><p class="eyebrow" style="margin:0">Your next best step</p><span class="badge" :class="statusClass(nextTask)" style="align-self:flex-start">{{ focusReason }}</span><h2><button class="task-title" @click="$emit('select-task',nextTask)">{{ nextTask.title }}</button></h2><p class="muted">{{ nextTask.description || 'Open the task to review the details before you begin.' }}</p><p class="small muted">Due {{ formatDateTime(nextTask.deadline) }} · {{ nextTask.team?.name || 'General' }}</p><div class="row wrap"><button class="btn" @click="$emit('select-task',nextTask)">Open details <ArrowUpRight aria-hidden="true" /></button><button v-if="nextTask.status === 'SCHEDULED'" class="btn btn-soft" :disabled="tasks.pending[nextTask.id]" @click="tasks.startTask(nextTask.id)"><Play aria-hidden="true" />Start task</button><button class="btn btn-primary" :disabled="tasks.pending[nextTask.id]" @click="$emit('complete-task',nextTask)"><Check aria-hidden="true" />Complete task</button></div></div>
      </section><p class="small muted">This suggestion uses overdue status, priority and deadlines. Complete it to see the next task, or leave focus mode to choose another.</p>
    </template>
    <EmptyState v-else-if="!visibleTasks.length && !tasks.myError" :title="tab === 'completed' ? 'Your finished work will live here' : tab === 'today' ? 'A clear schedule for today' : tab === 'overdue' ? 'Nothing overdue. Keep it going.' : 'You’re all caught up'" :description="tab === 'completed' ? 'Complete a task to build a record of your work and handoff notes.' : tab === 'active' ? 'No pending assignments right now. Check back when your team adds new work.' : 'Choose Active to see your other assignments.'" :icon="CircleCheck"><button v-if="tab !== 'active'" class="btn" @click="tab = 'active'">View active tasks</button></EmptyState>
    <TaskCollection v-else :tasks="visibleTasks" mode="list" @select-task="$emit('select-task',$event)" @complete-task="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" />
    <p v-if="tab === 'completed'" class="small muted">Showing tasks you completed in the last {{ RECENT_DONE_DAYS }} days. Older completed work is in All tasks.</p>
    <p class="small muted row"><Clock3 aria-hidden="true" />Task dates are shown in Asia/Manila (UTC+8).</p>
  </div>
</template>
<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { RefreshCw, Focus, CircleAlert, CircleCheck, ArrowUpRight, Play, Check, Clock3 } from 'lucide-vue-next'
import { useTaskStore, RECENT_DONE_DAYS } from '@/stores/tasks'
import { isOverdue, dateKey, focusScore, formatDateTime, statusClass, parseDate } from '@/utils/tasks'
import TaskCollection from '@/components/TaskCollection.vue'
import LoadingState from '@/components/LoadingState.vue'
import EmptyState from '@/components/EmptyState.vue'
defineEmits(['select-task','complete-task','share-messenger'])
const tasks = useTaskStore(), tab = ref('active'), focusMode = ref(false)
const active = computed(() => tasks.myTasks.filter(task => task.status !== 'DONE').sort((a,b) => focusScore(b) - focusScore(a) || (a.deadline ? parseDate(a.deadline).getTime() : Infinity) - (b.deadline ? parseDate(b.deadline).getTime() : Infinity)))
const completed = computed(() => tasks.myTasks.filter(task => task.status === 'DONE').sort((a,b) => parseDate(b.completed_at || b.updated_at).getTime() - parseDate(a.completed_at || a.updated_at).getTime()))
const todayTasks = computed(() => active.value.filter(task => task.deadline && dateKey(task.deadline) === dateKey() || task.scheduled_at && dateKey(task.scheduled_at) === dateKey()))
const overdueCount = computed(() => active.value.filter(isOverdue).length)
const tabs = computed(() => [{ value:'active',label:'Active',count:active.value.length },{ value:'today',label:'Today',count:todayTasks.value.length },{ value:'overdue',label:'Overdue',count:overdueCount.value },{ value:'completed',label:'Completed',count:completed.value.length }])
const visibleTasks = computed(() => tab.value === 'completed' ? completed.value : tab.value === 'overdue' ? active.value.filter(isOverdue) : tab.value === 'today' ? todayTasks.value : active.value)
const nextTask = computed(() => active.value[0])
const focusReason = computed(() => !nextTask.value ? '' : isOverdue(nextTask.value) ? 'Overdue · follow up first' : nextTask.value.priority === 'urgent' ? 'Urgent priority' : nextTask.value.deadline && dateKey(nextTask.value.deadline) === dateKey() ? 'Due today' : 'Next in your queue')
onMounted(() => { if (!tasks.myTasks.length && !tasks.myLoading) void tasks.fetchMyTasks() })
</script>
