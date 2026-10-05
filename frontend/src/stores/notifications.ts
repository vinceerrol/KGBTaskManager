import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import type { AppNotification } from '@/types'
import { errorMessage } from '@/utils/tasks'
export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<AppNotification[]>([]), loading = ref(false), error = ref(''), marking = ref(false)
  // The list is capped by the server, so unread items beyond it are counted separately.
  const serverUnread = ref<number | null>(null)
  let requestId = 0
  const unreadCount = computed(() => serverUnread.value ?? notifications.value.filter(item => !item.read_at).length)
  // quiet=true is used by background refreshes: no spinner and errors stay silent.
  async function fetchNotifications(quiet = false) {
    const request = ++requestId
    if (!quiet) { loading.value = true; error.value = '' }
    try {
      const res = await api.get('/notifications')
      if (request !== requestId) return
      notifications.value = res.data.data || res.data
      serverUnread.value = typeof res.data.unread_count === 'number' ? res.data.unread_count : null
    }
    catch (err) { if (request === requestId && !quiet) error.value = errorMessage(err) }
    finally { if (request === requestId && !quiet) loading.value = false }
  }
  async function markAsRead(id: number) {
    try {
      await api.post('/notifications/' + id + '/read')
      const item = notifications.value.find(item => item.id === id)
      if (item && !item.read_at) { item.read_at = new Date().toISOString(); if (serverUnread.value !== null) serverUnread.value = Math.max(0, serverUnread.value - 1) }
    }
    catch (err) { error.value = errorMessage(err) }
  }
  async function markAllAsRead() {
    if (marking.value) return
    marking.value = true
    try { await api.post('/notifications/read-all'); notifications.value.forEach(item => item.read_at = new Date().toISOString()); serverUnread.value = 0 }
    catch (err) { error.value = errorMessage(err) }
    finally { marking.value = false }
  }
  function reset() { ++requestId; notifications.value = []; serverUnread.value = null; error.value = ''; loading.value = false }
  return { notifications, unreadCount, loading, marking, error, fetchNotifications, markAsRead, markAllAsRead, reset }
})
