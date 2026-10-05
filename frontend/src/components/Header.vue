<template>
  <header class="app-header">
    <div class="row">
      <button class="icon-btn mobile-toggle" aria-label="Open navigation" @click="$emit('toggle-sidebar')"><Menu aria-hidden="true" /></button>
      <div class="breadcrumb" aria-label="Current location"><span>Workspace</span><ChevronRight aria-hidden="true" /><strong>{{ route.meta.title || 'Overview' }}</strong></div>
    </div>
    <div class="header-actions">
      <button class="search-trigger" aria-label="Search tasks and commands" aria-keyshortcuts="Control+K Meta+K /" @click="$emit('open-search')"><Search aria-hidden="true" /><span>Search anything</span><kbd>⌘ / Ctrl K</kbd></button>
      <button class="icon-btn" aria-label="Workspace preferences" @click="$emit('open-preferences')"><Settings2 aria-hidden="true" /></button>
      <div ref="notificationsRoot" style="position:relative">
        <button ref="bellButton" class="icon-btn" :aria-label="'Notifications, ' + notifications.unreadCount + ' unread'" :aria-expanded="showNotifications" aria-controls="notification-panel" style="position:relative" @click="toggleNotifications">
          <Bell aria-hidden="true" /><span v-if="notifications.unreadCount" class="notification-dot" aria-hidden="true" />
        </button>
        <section v-if="showNotifications" id="notification-panel" class="header-popover" aria-label="Notifications">
          <div class="popover-head"><h3>Inbox <span class="badge badge-brand">{{ notifications.unreadCount }}</span></h3><button v-if="notifications.unreadCount" class="btn btn-ghost" :disabled="notifications.marking" @click="notifications.markAllAsRead()">Mark all read</button></div>
          <div v-if="notifications.loading" class="empty-state" role="status">Loading your inbox…</div>
          <div v-else-if="notifications.error" class="stack" style="padding:16px"><p role="alert">{{ notifications.error }}</p><button class="btn" @click="notifications.fetchNotifications()">Try again</button></div>
          <div v-else-if="!notifications.notifications.length" class="empty-state"><BellOff aria-hidden="true" /><h3>You're up to date</h3><p>New assignments and updates will appear here.</p></div>
          <div v-else class="notification-list">
            <button v-for="item in notifications.notifications" :key="item.id" class="notification-item" :class="{ unread: !item.read_at }" @click="openNotification(item)">
              <span class="row"><strong>{{ item.title }}</strong><span v-if="!item.read_at" class="badge badge-brand">New</span></span><span class="muted small">{{ item.message }}</span><span class="muted small">{{ formatDateTime(item.created_at) }}</span>
            </button>
          </div>
        </section>
      </div>
      <span style="width:1px;height:24px;background:var(--line);margin:0 6px" aria-hidden="true" />
      <div ref="profileRoot" style="position:relative">
        <button ref="profileButton" class="btn btn-ghost" :aria-expanded="showProfile" aria-controls="profile-panel" :aria-label="'Account for ' + (auth.user?.name || 'User')" style="padding:0 4px" @click="showProfile = !showProfile; showNotifications = false">
          <span class="avatar" aria-hidden="true">{{ initials(auth.user?.name || 'User') }}</span><span class="header-profile-name">{{ auth.user?.name.split(' ')[0] }}</span><ChevronDown aria-hidden="true" />
        </button>
        <section v-if="showProfile" id="profile-panel" class="header-popover" aria-label="Your account">
          <div class="popover-head"><div><h3>{{ auth.user?.name }}</h3><p class="small muted">{{ roleLabel }} · {{ auth.user?.email }}</p></div></div>
          <div class="stack" style="padding:12px">
            <button class="btn" @click="showProfile = false; $emit('open-preferences')"><Settings2 aria-hidden="true" />Preferences & shortcuts</button>
            <button class="btn" @click="showProfile = false; passwordOpen = true"><KeyRound aria-hidden="true" />Change password</button>
            <template v-if="isDevelopment"><p class="eyebrow" style="margin:8px 0 0">Development demo accounts</p><button v-for="role in demoRoles" :key="role.value" class="btn" :disabled="switching || auth.user?.role === role.value" @click="switchRole(role.value)">{{ role.label }}</button></template>
            <button class="btn btn-danger" @click="auth.logout()"><LogOut aria-hidden="true" />Sign out</button>
          </div>
        </section>
      </div>
    </div>
    <ChangePasswordModal :is-open="passwordOpen" @close="passwordOpen = false" />
  </header>
</template>
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Menu, ChevronRight, Search, Settings2, Bell, BellOff, ChevronDown, LogOut, KeyRound } from 'lucide-vue-next'
import ChangePasswordModal from '@/components/ChangePasswordModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { initials, formatDateTime } from '@/utils/tasks'
import type { AppNotification, UserRole } from '@/types'
const emit = defineEmits(['toggle-sidebar', 'open-search', 'open-preferences', 'open-task'])
const route = useRoute(), router = useRouter(), auth = useAuthStore(), notifications = useNotificationStore()
const showNotifications = ref(false), showProfile = ref(false), switching = ref(false), passwordOpen = ref(false)
const notificationsRoot = ref<HTMLElement | null>(null), profileRoot = ref<HTMLElement | null>(null), bellButton = ref<HTMLButtonElement | null>(null), profileButton = ref<HTMLButtonElement | null>(null)
const isDevelopment = import.meta.env.DEV
const roleLabel = computed(() => auth.isCeo ? 'CEO' : auth.isTeamLead ? 'Team lead' : 'Team member')
const demoRoles: { value: UserRole; label: string }[] = [{ value: 'ceo', label: 'Sophia · CEO' }, { value: 'team_lead', label: 'Anna · Team lead' }, { value: 'employee', label: 'Carlo · Team member' }]
function toggleNotifications() { showNotifications.value = !showNotifications.value; showProfile.value = false; if (showNotifications.value) void notifications.fetchNotifications() }
function openNotification(item: AppNotification) { showNotifications.value = false; void notifications.markAsRead(item.id); if (item.task_id) emit('open-task',item.task_id) }
async function switchRole(role: UserRole) { switching.value = true; const success = await auth.demoLogin(role); switching.value = false; if (success) { showProfile.value = false; await router.push(role === 'employee' ? '/my-tasks' : '/') } }
function outside(event: Event) {
  const target = event.target as Node
  if (!notificationsRoot.value?.contains(target)) showNotifications.value = false
  if (!profileRoot.value?.contains(target)) showProfile.value = false
}
function escape(event: KeyboardEvent) {
  if (event.key !== 'Escape' || document.querySelector('dialog[open]')) return
  if (showNotifications.value) { showNotifications.value = false; bellButton.value?.focus() }
  if (showProfile.value) { showProfile.value = false; profileButton.value?.focus() }
}
function focusOut(event: FocusEvent) { outside(event) }
watch(() => route.fullPath, () => { showNotifications.value = false; showProfile.value = false })
onMounted(() => { document.addEventListener('pointerdown', outside); document.addEventListener('keydown', escape); document.addEventListener('focusin', focusOut) })
onBeforeUnmount(() => { document.removeEventListener('pointerdown', outside); document.removeEventListener('keydown', escape); document.removeEventListener('focusin', focusOut) })
</script>
