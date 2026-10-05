<template>
  <div v-if="mode === 'board'" class="board" aria-label="Tasks grouped by workflow stage">
    <section v-for="column in columns" :key="column.status" class="board-column" :aria-label="column.label">
      <div class="board-column-header"><span class="badge" :class="column.class"><component :is="column.icon" aria-hidden="true" />{{ column.label }}</span><span class="badge" style="margin-left:auto">{{ inColumn(column.status).length }}</span></div>
      <div class="stack"><TaskCard v-for="task in inColumn(column.status)" :key="task.id" :task="task" @select="$emit('select-task',$event)" @complete="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" @start="store.startTask" @reopen="store.reopenTask" /><p v-if="!inColumn(column.status).length" class="muted small" style="padding:24px 8px;text-align:center">No {{ column.label.toLowerCase() }} tasks in this view.</p></div>
    </section>
  </div>
  <div v-else :class="mode === 'list' ? 'task-list' : 'task-grid'">
    <TaskCard v-for="task in tasks" :key="task.id" :task="task" @select="$emit('select-task',$event)" @complete="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" @start="store.startTask" @reopen="store.reopenTask" />
  </div>
</template>
<script setup lang="ts">
import { Clock3, CircleDot, CircleCheck } from 'lucide-vue-next'
import TaskCard from '@/components/TaskCard.vue'
import { useTaskStore } from '@/stores/tasks'
import type { Task, TaskStatus } from '@/types'
const props = defineProps<{ tasks: Task[]; mode?: 'grid' | 'list' | 'board' }>()
defineEmits(['select-task','complete-task','share-messenger'])
const store = useTaskStore()
const columns = [{ status:'SCHEDULED' as TaskStatus,label:'Scheduled',icon:Clock3,class:'badge-warning' },{ status:'IN PROGRESS' as TaskStatus,label:'In progress',icon:CircleDot,class:'badge-info' },{ status:'DONE' as TaskStatus,label:'Completed',icon:CircleCheck,class:'badge-success' }]
function inColumn(status: TaskStatus) { return props.tasks.filter(task => task.status === status) }
</script>
