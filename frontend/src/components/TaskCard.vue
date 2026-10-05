<template>
  <article class="task-card" :class="{ 'is-overdue': isOverdue(task), 'is-done': task.status === 'DONE' }" :aria-labelledby="'task-title-' + task.id">
    <div class="stack" style="gap:9px">
      <div class="row wrap"><span class="badge" :class="priorityClass(task.priority)"><Flag aria-hidden="true" />{{ task.priority === 'normal' ? 'Normal' : task.priority === 'high' ? 'High priority' : 'Urgent' }}</span><span class="badge" :class="statusClass(task)">{{ statusLabel(task) }}</span></div>
      <h3><button :id="'task-title-' + task.id" class="task-title" @click="$emit('select', task)">{{ task.title }}</button></h3>
      <p v-if="task.description" class="task-description">{{ task.description }}</p>
    </div>
    <div class="task-metadata">
      <span class="row"><Folder aria-hidden="true" /><span class="truncate">{{ task.team?.name || 'General' }}</span></span>
      <span v-if="task.deadline" class="row" :class="{ 'danger-text': isOverdue(task) }"><CalendarDays aria-hidden="true" /><span>Due {{ formatDateTime(task.deadline) }}</span></span>
      <span v-else class="row"><CalendarDays aria-hidden="true" /><span>No deadline set</span></span>
      <span v-if="task.status === 'SCHEDULED'" class="row"><Clock3 aria-hidden="true" />Starts {{ formatDateTime(task.scheduled_at) }}</span>
    </div>
    <div class="task-footer">
      <div class="row grow" :title="assigneeLabel(task)">
        <div class="avatar-stack" aria-hidden="true"><span v-for="member in assignees(task).slice(0,2)" :key="member.id" class="avatar">{{ initials(member.name) }}</span><span v-if="!assignees(task).length" class="avatar"><Users aria-hidden="true" /></span></div>
        <span class="truncate small">{{ assignees(task).length > 1 ? assignees(task).length + ' members' : assigneeLabel(task) }}</span>
      </div>
      <div class="task-actions">
        <button class="icon-btn" :aria-label="'Share ' + task.title" @click="$emit('share-messenger', task)"><Share2 aria-hidden="true" /></button>
        <template v-if="canActOnTask(task, auth.user)">
          <button v-if="task.status === 'SCHEDULED'" class="btn btn-soft" :disabled="tasks.pending[task.id]" :aria-label="'Start ' + task.title" @click="$emit('start', task.id)"><LoaderCircle v-if="tasks.pending[task.id]" class="spinner" aria-hidden="true" /><Play v-else aria-hidden="true" />Start</button>
          <button v-if="task.status !== 'DONE'" class="btn btn-success" :disabled="tasks.pending[task.id]" :aria-label="'Complete ' + task.title" @click="$emit('complete', task)"><Check aria-hidden="true" />Done</button>
          <button v-else class="btn btn-ghost" :disabled="tasks.pending[task.id]" :aria-label="'Reopen ' + task.title" @click="$emit('reopen', task.id)"><RotateCcw aria-hidden="true" />Reopen</button>
        </template>
      </div>
    </div>
  </article>
</template>
<script setup lang="ts">
import { Flag, Folder, CalendarDays, Clock3, Users, Share2, Play, Check, RotateCcw, LoaderCircle } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { isOverdue, formatDateTime, assignees, assigneeLabel, initials, priorityClass, statusClass, statusLabel, canActOnTask } from '@/utils/tasks'
import type { Task } from '@/types'
defineProps<{ task: Task }>(); defineEmits(['select', 'share-messenger', 'start', 'complete', 'reopen'])
const auth = useAuthStore(), tasks = useTaskStore()
</script>
