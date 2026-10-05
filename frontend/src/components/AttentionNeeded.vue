<template>
  <section class="panel stack">
    <div class="section-header"><div><h2 class="row"><CircleAlert style="color:var(--warning)" aria-hidden="true" />Needs your attention</h2><p class="small muted" style="margin-top:5px">A short list of what needs a decision next.</p></div><span class="badge" :class="overdue.length ? 'badge-danger' : 'badge-success'">{{ overdue.length ? overdue.length + ' overdue' : 'On track' }}</span></div>
    <div v-if="!items.length" class="empty-state" style="padding:28px 0"><CircleCheck aria-hidden="true" /><h3>A little breathing room</h3><p>No overdue or due-today work in this view.</p></div>
    <button v-for="task in items" :key="task.id" class="command-item" style="border:1px solid var(--line)" @click="$emit('select-task', task)"><span class="team-icon" :style="{ background: isOverdue(task) ? 'var(--danger-soft)' : 'var(--info-soft)', color: isOverdue(task) ? 'var(--danger)' : 'var(--info)' }"><CircleAlert v-if="isOverdue(task)" aria-hidden="true" /><Clock3 v-else aria-hidden="true" /></span><span class="grow"><strong>{{ task.title }}</strong><span class="small muted" style="display:block;margin-top:4px">{{ assigneeLabel(task) }} · {{ task.team?.name || 'General' }}</span></span><span class="badge" :class="isOverdue(task) ? 'badge-danger' : 'badge-info'">{{ isOverdue(task) ? 'Overdue' : 'Due today' }}</span></button>
    <RouterLink v-if="items.length" :to="{ path: '/tasks', query: { status: overdue.length ? 'OVERDUE' : 'all', date_filter: overdue.length ? 'all' : 'today' } }" class="text-link">Review all {{ overdue.length ? 'overdue' : 'today’s' }} tasks <ArrowRight aria-hidden="true" /></RouterLink>
  </section>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { CircleAlert, CircleCheck, Clock3, ArrowRight } from 'lucide-vue-next'
import { useTaskStore } from '@/stores/tasks'
import { isOverdue, dateKey, assigneeLabel, focusScore } from '@/utils/tasks'
defineEmits(['select-task'])
const tasks = useTaskStore()
const overdue = computed(() => tasks.tasks.filter(isOverdue))
const items = computed(() => tasks.tasks.filter(task => task.status !== 'DONE' && (isOverdue(task) || task.deadline && dateKey(task.deadline) === dateKey())).sort((a,b) => focusScore(b) - focusScore(a)).slice(0,4))
</script>
