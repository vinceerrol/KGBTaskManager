<template>
  <a v-if="!isLoginPage" href="#main-content" class="skip-link">Skip to main content</a>
  <RouterView v-if="isLoginPage" />
  <div v-else class="app-shell">
    <Sidebar @open-create-task="openCreateTaskModal()" @open-write-task="openWriteTask" @open-preferences="isPreferencesOpen = true" />
    <div class="app-body">
      <Header @toggle-sidebar="isNavigationOpen = true" @open-search="isSearchOpen = true" @open-preferences="isPreferencesOpen = true" @open-task="openTaskById" />
      <main id="main-content" ref="mainContent" class="workspace-main" tabindex="-1">
        <RouterView @open-create-task="openCreateTaskModal" @open-write-task="openWriteTask" @select-task="openTaskDetail" @complete-task="openCompleteModal" @share-messenger="openMessengerModal" />
      </main>
    </div>
    <MobileNav @open-create-task="openCreateTaskModal()" @open-more="isNavigationOpen = true" />
  </div>
  <BaseModal :is-open="isNavigationOpen" title="Your workspace" description="Jump to a view or adjust your workspace." @close="isNavigationOpen = false">
    <nav class="stack" aria-label="All workspace pages">
      <button v-if="!auth.isEmployee" type="button" class="btn btn-soft" @click="isNavigationOpen = false; openWriteTask()">Write a task</button>
      <RouterLink v-for="link in navigation" :key="link.path" :to="link.path" class="btn" @click="isNavigationOpen = false">{{ link.label }}</RouterLink>
      <button class="btn btn-soft" @click="isNavigationOpen = false; isPreferencesOpen = true">Workspace preferences</button>
    </nav>
  </BaseModal>
  <CreateTaskModal :is-open="isCreateModalOpen" :prefill="createPrefill" @close="isCreateModalOpen = false" @created="onTaskCreated" />
  <TaskBriefModal :is-open="isBriefModalOpen" @close="isBriefModalOpen = false" @created="onTaskCreated" />
  <TaskDetailModal :is-open="isDetailModalOpen" :task="selectedTask" @close="closeDetail" @complete="openCompleteModal" @share-messenger="openMessengerModal" @duplicate="openCreateTaskModal" />
  <CompleteTaskModal :is-open="isCompleteModalOpen" :task="taskToComplete" @close="isCompleteModalOpen = false" @completed="onTaskCompleted" />
  <MessengerShareModal :is-open="isMessengerModalOpen" :task="taskToShare" @close="isMessengerModalOpen = false" />
  <CommandPalette :is-open="isSearchOpen" @close="isSearchOpen = false" @open-create-task="openCreateTaskModal()" @open-write-task="openWriteTask" @select-task="openTaskDetail" @open-preferences="isPreferencesOpen = true" />
  <PreferencesModal :is-open="isPreferencesOpen" @close="isPreferencesOpen = false" />
  <ToastContainer />
</template>
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Sidebar from '@/components/Sidebar.vue'
import Header from '@/components/Header.vue'
import MobileNav from '@/components/MobileNav.vue'
import BaseModal from '@/components/BaseModal.vue'
import CreateTaskModal from '@/components/CreateTaskModal.vue'
import TaskBriefModal from '@/components/TaskBriefModal.vue'
import TaskDetailModal from '@/components/TaskDetailModal.vue'
import CompleteTaskModal from '@/components/CompleteTaskModal.vue'
import MessengerShareModal from '@/components/MessengerShareModal.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import CommandPalette from '@/components/CommandPalette.vue'
import PreferencesModal from '@/components/PreferencesModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useTaskStore } from '@/stores/tasks'
import { useTeamStore } from '@/stores/teams'
import { useNotificationStore } from '@/stores/notifications'
import { usePreferencesStore } from '@/stores/preferences'
import { toast } from '@/stores/toast'
import type { Task, CreateTaskPrefill } from '@/types'
const route = useRoute(), router = useRouter(), auth = useAuthStore(), tasks = useTaskStore(), teams = useTeamStore(), notifications = useNotificationStore()
const preferences = usePreferencesStore()
const isLoginPage = computed(() => route.path === '/login')
const isNavigationOpen = ref(false), isCreateModalOpen = ref(false), isDetailModalOpen = ref(false), isCompleteModalOpen = ref(false), isMessengerModalOpen = ref(false), isSearchOpen = ref(false), isPreferencesOpen = ref(false)
const isBriefModalOpen = ref(false)
const selectedTask = ref<Task | null>(null), taskToComplete = ref<Task | null>(null), taskToShare = ref<Task | null>(null), createPrefill = ref<CreateTaskPrefill>({})
const mainContent = ref<HTMLElement | null>(null)
const baseNavigation = [{ path: '/', label: 'Overview' }, { path: '/my-tasks', label: 'My work' }, { path: '/tasks', label: 'All tasks' }, { path: '/teams', label: 'Teams & workload' }, { path: '/templates', label: 'Workflow library' }]
const navigation = computed(() => auth.isCeo ? [...baseNavigation, { path: '/people', label: 'People & teams' }] : baseNavigation)
let detailReturn = '/tasks', detailWasPushed = false, deepLinkRequest = 0
watch(() => auth.user?.id, async id => {
  toast.clear()
  detailWasPushed = false; detailReturn = '/tasks'; ++deepLinkRequest
  tasks.reset(); teams.reset(); notifications.reset()
  isDetailModalOpen.value = false; isCreateModalOpen.value = false; isCompleteModalOpen.value = false; isMessengerModalOpen.value = false; isSearchOpen.value = false; isPreferencesOpen.value = false
  isBriefModalOpen.value = false
  if (id) {
    await Promise.allSettled([tasks.fetchTasks(), tasks.fetchMyTasks(), tasks.fetchDashboardStats(), teams.fetchTeams(), notifications.fetchNotifications()])
    if (auth.user?.id !== id) return
    await checkDeepLink()
  }
}, { immediate: true })
watch(() => route.params.id, checkDeepLink)
watch(() => route.path, async () => {
  isNavigationOpen.value = false
  document.title = String(route.meta.title || 'Workspace') + ' · KCG'
  if (!route.params.id && !isLoginPage.value) { await nextTick(); mainContent.value?.focus({ preventScroll: true }) }
}, { immediate: true })
async function checkDeepLink() {
  const request = ++deepLinkRequest
  if (!auth.isAuthenticated) return
  if (!route.params.id) { isDetailModalOpen.value = false; selectedTask.value = null; return }
  const id = Number(route.params.id)
  if (!Number.isInteger(id) || id <= 0) { toast.error('This task link is invalid.'); return }
  const task = await tasks.fetchTask(id)
  if (request !== deepLinkRequest || String(route.params.id) !== String(id)) return
  if (task) { selectedTask.value = task; isDetailModalOpen.value = true }
  else { isDetailModalOpen.value = false; toast.error(tasks.detailError || 'This task is no longer available.') }
}
function openCreateTaskModal(prefill: CreateTaskPrefill = {}) {
  if (auth.isEmployee || !auth.isAuthenticated) return
  createPrefill.value = prefill && !(prefill instanceof Event) ? prefill : {}
  isCreateModalOpen.value = true
}
function openWriteTask() {
  if (auth.isEmployee || !auth.isAuthenticated) return
  isBriefModalOpen.value = true
}
function openTaskDetail(task: Task) {
  openTaskById(task.id)
}
function openTaskById(id: number) {
  if (!route.params.id) { detailReturn = route.fullPath; detailWasPushed = true }
  void router.push({ path: '/tasks/' + id, query: route.query })
}
function closeDetail() {
  isDetailModalOpen.value = false; selectedTask.value = null
  if (route.params.id) {
    if (detailWasPushed) { detailWasPushed = false; router.back() }
    else void router.replace(detailReturn)
  }
}
function openCompleteModal(task: Task) { taskToComplete.value = task; isCompleteModalOpen.value = true }
function openMessengerModal(task: Task) { taskToShare.value = task; isMessengerModalOpen.value = true }
function onTaskCreated() { if (isDetailModalOpen.value) closeDetail(); void tasks.fetchTasks(); void teams.fetchTeams() }
function onTaskCompleted() { if (isDetailModalOpen.value) closeDetail(); void tasks.fetchTasks(); void teams.fetchTeams() }
function handleGlobalKeydown(event: KeyboardEvent) {
  if (isLoginPage.value || !auth.isAuthenticated || document.querySelector('dialog[open]')) return
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); isSearchOpen.value = true; return }
  const target = event.target as HTMLElement
  if (target.closest('input, textarea, select, [contenteditable="true"]')) return
  if (!preferences.singleKeyShortcuts || event.altKey || event.metaKey || event.ctrlKey) return
  if (event.key === '/') { event.preventDefault(); isSearchOpen.value = true }
  else if (event.key.toLowerCase() === 'c' && !auth.isEmployee) { event.preventDefault(); openCreateTaskModal() }
}
// Keep the bell fresh without a page reload: refresh quietly every minute while the tab is visible.
let notificationTimer: number | undefined
function refreshNotifications() { if (auth.isAuthenticated && !document.hidden) void notifications.fetchNotifications(true) }
onMounted(() => {
  if (auth.token) void auth.fetchUser()
  window.addEventListener('keydown', handleGlobalKeydown)
  document.addEventListener('visibilitychange', refreshNotifications)
  notificationTimer = window.setInterval(refreshNotifications, 60_000)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
  document.removeEventListener('visibilitychange', refreshNotifications)
  window.clearInterval(notificationTimer)
})
</script>
