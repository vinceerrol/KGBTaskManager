<template>
  <BaseModal :is-open="isOpen" title="Search & jump to" description="Find tasks by title, team or assignee. Use ↑ ↓ and Enter." @close="$emit('close')">
    <div class="stack">
      <div class="field search-field"><label for="command-search" class="sr-only">Search tasks and commands</label><Search aria-hidden="true" /><input id="command-search" v-model="query" autofocus placeholder="Search your workspace…" role="combobox" aria-autocomplete="list" aria-controls="command-results" :aria-expanded="true" :aria-activedescendant="results.length ? 'command-' + activeIndex : undefined" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="run(results[activeIndex])" /></div>
      <p class="eyebrow" aria-live="polite">{{ query ? results.length + ' results' : 'Quick actions' }}</p>
      <div id="command-results" class="command-list" role="listbox" aria-label="Search results">
        <div v-if="tasks.loading && !tasks.tasks.length" class="muted" role="status">Loading tasks…</div>
        <button v-for="(result, index) in results" :id="'command-' + index" :key="result.id" type="button" class="command-item" :class="{ 'is-selected': index === activeIndex }" role="option" :aria-selected="index === activeIndex" @click="run(result)">
          <component :is="result.icon" aria-hidden="true" /><span class="grow"><strong>{{ result.label }}</strong><span v-if="result.caption" class="muted small" style="display:block">{{ result.caption }}</span></span><ArrowUpRight aria-hidden="true" />
        </button>
      </div>
      <div v-if="!results.length" class="empty-state"><SearchX aria-hidden="true" /><h3>No results for “{{ query }}”</h3><p>Try a task title, a team name or a different keyword.</p></div>
      <div v-if="tasks.error" class="error-banner" role="alert">{{ tasks.error }}<button class="btn" @click="tasks.fetchTasks()">Try again</button></div>
    </div>
  </BaseModal>
</template>
<script setup lang="ts">
import { ref, computed, watch, nextTick, type Component } from 'vue'
import { useRouter } from 'vue-router'
import { Search, SearchX, Plus, LayoutDashboard, CircleCheck, ListTodo, Users, Layers, Settings2, ArrowUpRight, FilePenLine } from 'lucide-vue-next'
import BaseModal from '@/components/BaseModal.vue'
import { useTaskStore } from '@/stores/tasks'
import { useAuthStore } from '@/stores/auth'
import { assigneeLabel } from '@/utils/tasks'
import type { Task } from '@/types'
const props = defineProps<{ isOpen: boolean }>()
const emit = defineEmits(['close', 'open-create-task', 'open-write-task', 'select-task', 'open-preferences'])
const router = useRouter(), tasks = useTaskStore(), auth = useAuthStore(), query = ref(''), activeIndex = ref(0)
interface Result { id: string; label: string; caption?: string; icon: Component; path?: string; action?: string; task?: Task }
const commands = computed<Result[]>(() => [
  ...(!auth.isEmployee ? [{ id: 'create', label: 'Create a task', caption: 'Assign work in a few seconds', icon: Plus, action: 'create' }] : []),
  ...(!auth.isEmployee ? [{ id: 'write', label: 'Write a task', caption: 'English or Taglish · exact @mentions · live preview', icon: FilePenLine, action: 'write' }] : []),
  { id: 'overview', label: 'Overview', icon: LayoutDashboard, path: '/' },
  { id: 'mine', label: 'My work', caption: 'Focus on your next task', icon: CircleCheck, path: '/my-tasks' },
  { id: 'tasks', label: 'All tasks', icon: ListTodo, path: '/tasks' },
  { id: 'teams', label: 'Teams & workload', icon: Users, path: '/teams' },
  { id: 'library', label: 'Workflow library', icon: Layers, path: '/templates' },
  { id: 'preferences', label: 'Preferences & shortcuts', icon: Settings2, action: 'preferences' }
])
const results = computed<Result[]>(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return commands.value
  const matches = tasks.tasks.filter(task => [task.title, task.description, task.team?.name, assigneeLabel(task)].some(text => text?.toLowerCase().includes(q)))
  return [...commands.value.filter(item => item.label.toLowerCase().includes(q)), ...matches.slice(0, 15).map(task => ({ id: 'task-' + task.id, label: task.title, caption: (task.team?.name || 'General') + ' · ' + assigneeLabel(task), icon: ListTodo, task }))]
})
watch(() => props.isOpen, open => { if (open) { query.value = ''; activeIndex.value = 0; if (!tasks.tasks.length) void tasks.fetchTasks() } })
watch(query, () => activeIndex.value = 0)
async function move(direction: number) { if (!results.value.length) return; activeIndex.value = (activeIndex.value + direction + results.value.length) % results.value.length; await nextTick(); document.getElementById('command-' + activeIndex.value)?.scrollIntoView({ block: 'nearest' }) }
function run(result?: Result) { if (!result) return; emit('close'); if (result.task) emit('select-task', result.task); else if (result.action === 'create') emit('open-create-task'); else if (result.action === 'write') emit('open-write-task'); else if (result.action === 'preferences') emit('open-preferences'); else if (result.path) void router.push(result.path) }
</script>
