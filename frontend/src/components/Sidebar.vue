<template>
  <aside class="app-sidebar" aria-label="Workspace sidebar">
    <RouterLink to="/" class="brand-link" aria-label="KCG Workspace home"><span class="brand-mark" aria-hidden="true">K</span><span class="brand-name">KCG <span>workspace</span></span></RouterLink>
    <button v-if="!auth.isEmployee" class="btn btn-primary" @click="$emit('open-create-task')"><Plus aria-hidden="true" /> Create task <span v-if="preferences.singleKeyShortcuts" class="small" style="margin-left:auto;opacity:.8">C</span></button>
    <button v-if="!auth.isEmployee" type="button" class="sidebar-link" style="margin-top:6px;color:#c9c1ff" @click="$emit('open-write-task')"><FilePenLine aria-hidden="true" />Write a task</button>
    <p class="sidebar-caption">Workspace</p>
    <nav class="sidebar-nav" aria-label="Main navigation">
      <RouterLink v-for="link in links" :key="link.path" :to="link.path" class="sidebar-link" :class="{ active: active(link.path) }" :aria-current="active(link.path) ? 'page' : undefined">
        <component :is="link.icon" aria-hidden="true" /><span>{{ link.label }}</span>
        <span v-if="link.path === '/my-tasks' && activeCount" class="nav-count" :aria-label="activeCount + ' active tasks'">{{ activeCount }}</span>
      </RouterLink>
    </nav>
    <p class="sidebar-caption">Personalize</p>
    <button class="sidebar-link" @click="$emit('open-preferences')"><Settings2 aria-hidden="true" /> Preferences</button>
    <div class="sidebar-bottom">
      <div class="sidebar-tip"><div class="row" style="margin-bottom:8px"><Sparkles aria-hidden="true" /><h3>A little less busywork.</h3></div><p>Turn repeatable work into templates. Keep your team focused on what comes next.</p><RouterLink to="/templates" class="sidebar-link" style="padding:8px 0;color:#c9c1ff">Explore workflows <ArrowUpRight aria-hidden="true" /></RouterLink></div>
      <div class="sidebar-footer"><span>Workspace time · UTC+8</span><button @click="$emit('open-preferences')" aria-label="Open workspace help and shortcuts"><CircleHelp aria-hidden="true" /></button></div>
    </div>
  </aside>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { Plus, LayoutDashboard, CircleCheck, ListTodo, Users, Layers, Settings2, Sparkles, ArrowUpRight, CircleHelp, FilePenLine, UserCog } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { usePreferencesStore } from '@/stores/preferences'
defineEmits(['open-create-task', 'open-write-task', 'open-preferences'])
const route = useRoute(), auth = useAuthStore(), tasks = useTaskStore(), preferences = usePreferencesStore()
const baseLinks = [{ path: '/', label: 'Overview', icon: LayoutDashboard }, { path: '/my-tasks', label: 'My work', icon: CircleCheck }, { path: '/tasks', label: 'All tasks', icon: ListTodo }, { path: '/teams', label: 'Teams & workload', icon: Users }, { path: '/templates', label: 'Workflow library', icon: Layers }]
const links = computed(() => auth.isCeo ? [...baseLinks, { path: '/people', label: 'People & teams', icon: UserCog }] : baseLinks)
const activeCount = computed(() => tasks.myTasks.filter(task => task.status !== 'DONE').length)
function active(path: string) { return path === '/' ? route.path === '/' : route.path.startsWith(path) }
</script>
