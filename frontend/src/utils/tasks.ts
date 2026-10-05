import type { Task, TaskPriority, User } from '@/types'
import { useNow, useIntervalFn } from '@vueuse/core'

const { now: workspaceNow, pause } = useNow({ controls: true, scheduler: callback => useIntervalFn(callback, 60_000) })
if (import.meta.hot) import.meta.hot.dispose(pause)

export const WORKSPACE_TIMEZONE = 'Asia/Manila'
export function parseDate(value: string) {
  return new Date(/(Z|[+-]\d{2}:?\d{2})$/i.test(value) ? value : `${value.replace(' ', 'T')}+08:00`)
}
export function dateKey(value: string | Date = workspaceNow.value) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: WORKSPACE_TIMEZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).format(typeof value === 'string' ? parseDate(value) : value)
}
export function formatDateTime(value?: string | null) {
  if (!value) return 'No date set'
  const date = parseDate(value)
  if (Number.isNaN(date.getTime())) return 'Date unavailable'
  const time = date.toLocaleTimeString('en', { timeZone: WORKSPACE_TIMEZONE, hour: 'numeric', minute: '2-digit' })
  const day = dateKey(date) === dateKey() ? 'Today' : date.toLocaleDateString('en', { timeZone: WORKSPACE_TIMEZONE, month: 'short', day: 'numeric', ...(date.getFullYear() !== new Date().getFullYear() ? { year: 'numeric' } : {}) })
  return `${day}, ${time}`
}
export function toLocalInput(value?: string | null) {
  if (!value) return ''
  const date = parseDate(value)
  const time = date.toLocaleTimeString('en-GB', { timeZone: WORKSPACE_TIMEZONE, hour: '2-digit', minute: '2-digit', hour12: false })
  return `${dateKey(date)}T${time}`
}
export function inputToIso(value: string) { return value ? `${value}:00+08:00` : null }
export function isOverdue(task: Task) { return task.status !== 'DONE' && !!task.deadline && parseDate(task.deadline).getTime() < workspaceNow.value.getTime() }
export function assignees(task: Task): User[] { return task.assignees?.length ? task.assignees : task.assignee ? [task.assignee] : [] }
export function assigneeLabel(task: Task) { return assignees(task).map(user => user.name).join(', ') || (task.team_id ? 'Entire team' : 'Unassigned') }
export function initials(name: string) { return name.split(' ').slice(0, 2).map(word => word.charAt(0)).join('').toUpperCase() }
export function priorityClass(priority: TaskPriority) { return priority === 'urgent' ? 'badge-danger' : priority === 'high' ? 'badge-warning' : '' }
export function statusClass(task: Task) { return isOverdue(task) ? 'badge-danger' : task.status === 'DONE' ? 'badge-success' : task.status === 'SCHEDULED' ? 'badge-warning' : 'badge-info' }
export function statusLabel(task: Task) { return isOverdue(task) ? 'Overdue' : task.status === 'DONE' ? 'Completed' : task.status === 'SCHEDULED' ? 'Scheduled' : 'In progress' }
export function canActOnTask(task: Task, user: User | null) {
  if (!user) return false
  return canManageTask(task,user) || task.assigned_to === user.id || assignees(task).some(member => member.id === user.id) || (!task.assigned_to && !assignees(task).length && !!user.teams?.some(team => team.id === task.team_id))
}
export function canManageTask(task: Task, user: User | null) { return !!user && (user.role === 'ceo' || user.role === 'team_lead' && (task.created_by === user.id || !!user.teams?.some(team => team.id === task.team_id))) }
export function focusScore(task: Task) {
  return (isOverdue(task) ? 100 : 0) + (task.priority === 'urgent' ? 30 : task.priority === 'high' ? 20 : 0) + (task.deadline && dateKey(task.deadline) === dateKey() ? 15 : 0) + (task.status === 'IN PROGRESS' ? 10 : 0)
}
export function errorMessage(error: unknown) {
  const response = (error as { response?: { status?: number; data?: { message?: string } } })?.response
  if (response?.status === 404) return 'This item is unavailable or outside your workspace access.'
  if (response?.status && response.status >= 500) return 'The workspace could not finish this request. Try again in a moment.'
  return response?.data?.message || 'Could not connect to the workspace. Check your connection and try again.'
}
export function fieldErrors(error: unknown): Record<string, string> {
  const errors = (error as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors || {}
  return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0] || 'Check this field.']))
}
