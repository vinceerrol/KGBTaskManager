<template>
  <div v-if="mode === 'board'" class="board" aria-label="Tasks grouped by workflow stage">
    <section v-for="column in columns" :key="column.status" class="board-column" :aria-label="column.label">
      <div class="board-column-header"><span class="badge" :class="column.class"><component :is="column.icon" aria-hidden="true" />{{ column.label }}</span><span class="badge" style="margin-left:auto">{{ grouped[column.status].length }}</span></div>
      <div class="stack"><TaskCard v-for="task in grouped[column.status].slice(0, limits[column.status])" :key="task.id" :task="task" @select="$emit('select-task',$event)" @complete="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" @start="store.startTask" @reopen="store.reopenTask" /><p v-if="!grouped[column.status].length" class="muted small" style="padding:24px 8px;text-align:center">No {{ column.label.toLowerCase() }} tasks in this view.</p><button v-if="grouped[column.status].length > limits[column.status]" type="button" class="btn" @click="limits[column.status] += PAGE">Show {{ Math.min(PAGE, grouped[column.status].length - limits[column.status]) }} more of {{ grouped[column.status].length - limits[column.status] }} remaining</button></div>
    </section>
  </div>
  <template v-else>
    <div :class="mode === 'list' ? 'task-list' : 'task-grid'">
      <TaskCard v-for="task in visible" :key="task.id" :task="task" @select="$emit('select-task',$event)" @complete="$emit('complete-task',$event)" @share-messenger="$emit('share-messenger',$event)" @start="store.startTask" @reopen="store.reopenTask" />
    </div>
    <div v-if="tasks.length > limit" class="row" style="justify-content:center;margin-top:16px"><button type="button" class="btn" @click="limit += PAGE">Show {{ Math.min(PAGE, tasks.length - limit) }} more · {{ tasks.length - limit }} remaining</button></div>
  </template>
</template>
<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { Clock3, CircleDot, CircleCheck } from 'lucide-vue-next'
import TaskCard from '@/components/TaskCard.vue'
import { useTaskStore } from '@/stores/tasks'
import type { Task, TaskStatus } from '@/types'
const props = defineProps<{ tasks: Task[]; mode?: 'grid' | 'list' | 'board' }>()
defineEmits(['select-task','complete-task','share-messenger'])
const store = useTaskStore()
// Drawing thousands of cards at once freezes the page, so show a page at a time.
const PAGE = 60
const limit = ref(PAGE), limits = reactive<Record<TaskStatus, number>>({ 'SCHEDULED': PAGE, 'IN PROGRESS': PAGE, 'DONE': PAGE })
const visible = computed(() => props.tasks.slice(0, limit.value))
const columns = [{ status:'SCHEDULED' as TaskStatus,label:'Scheduled',icon:Clock3,class:'badge-warning' },{ status:'IN PROGRESS' as TaskStatus,label:'In progress',icon:CircleDot,class:'badge-info' },{ status:'DONE' as TaskStatus,label:'Completed',icon:CircleCheck,class:'badge-success' }]
// Group once per change instead of filtering the full list three times on every render.
const grouped = computed(() => {
  const groups: Record<TaskStatus, Task[]> = { 'SCHEDULED': [], 'IN PROGRESS': [], 'DONE': [] }
  for (const task of props.tasks) groups[task.status]?.push(task)
  return groups
})
</script>
