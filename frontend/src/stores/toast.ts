import { ref } from 'vue'
export type ToastType = 'success' | 'error' | 'info' | 'warning'
export interface ToastItem { id: string; type: ToastType; message: string; title?: string; actionLabel?: string; action?: () => unknown; duration: number }
const toasts = ref<ToastItem[]>([])
const timers = new Map<string, ReturnType<typeof setTimeout>>()
const remaining = new Map<string, number>()
const started = new Map<string, number>()
function schedule(item: ToastItem) {
  const duration = remaining.get(item.id) ?? item.duration
  if (duration <= 0) return
  started.set(item.id, Date.now())
  timers.set(item.id, setTimeout(() => remove(item.id), duration))
}
function remove(id: string) {
  clearTimeout(timers.get(id)); timers.delete(id); remaining.delete(id); started.delete(id)
  toasts.value = toasts.value.filter(item => item.id !== id)
}
function add(type: ToastType, message: string, title?: string, duration = 6000, actionLabel?: string, action?: () => unknown) {
  const item = { id: crypto.randomUUID(), type, message, title, duration, actionLabel, action }
  toasts.value.push(item); schedule(item)
}
function pause(id: string) {
  const item = toasts.value.find(item => item.id === id)
  if (!item || !timers.has(id)) return
  clearTimeout(timers.get(id)); timers.delete(id)
  remaining.set(id, Math.max(1, (remaining.get(id) ?? item.duration) - (Date.now() - (started.get(id) ?? Date.now()))))
}
function resume(id: string) {
  const item = toasts.value.find(item => item.id === id)
  if (item && !timers.has(id)) schedule(item)
}
export const toast = {
  success: (message: string, title?: string, duration = 6000) => add('success', message, title, duration),
  error: (message: string, title?: string, duration = 0) => add('error', message, title, duration),
  info: (message: string, title?: string, duration = 6000) => add('info', message, title, duration),
  warning: (message: string, title?: string, duration = 8000) => add('warning', message, title, duration),
  action: (message: string, label: string, action: () => unknown) => add('success', message, undefined, 10000, label, action),
  remove, pause, resume, clear: () => [...toasts.value].forEach(item => remove(item.id))
}
export function useToast() { return { toasts, toast, removeToast: remove } }
