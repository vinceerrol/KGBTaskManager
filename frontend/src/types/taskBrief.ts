import type { TaskPriority } from './index'

export type DraftField = 'title' | 'description' | 'team_id' | 'assignee_ids' | 'priority' | 'start_type' | 'scheduled_at' | 'deadline'
export interface TaskBriefForm {
  title: string
  description: string
  team_id: number | ''
  assignee_ids: number[]
  priority: TaskPriority
  start_type: 'now' | 'scheduled'
  scheduled_at: string
  deadline: string
}
export interface BriefMention {
  kind: 'person' | 'team'
  id: number
  label: string
  start: number
  end: number
}
export interface BriefPerson { id: number; name: string; teams: { id: number; name: string }[] }
export interface BriefTeam { id: number; name: string; member_ids: number[] }
export interface BriefContext {
  people: BriefPerson[]
  teams: BriefTeam[]
  reference_at: string
  timezone: string
  ai_enabled: boolean
  ai_provider?: 'openai' | 'groq' | null
}
export interface BriefIssue { field: DraftField | 'brief' | 'mentions'; message: string; blocking: boolean }
export interface BriefInterpretation {
  fields: TaskBriefForm
  ownership: 'people' | 'team' | 'unassigned' | 'unresolved'
  issues: BriefIssue[]
  dateOnly: boolean
  deadlineInherited?: boolean
}
export const emptyBriefForm = (): TaskBriefForm => ({ title: '', description: '', team_id: '', assignee_ids: [], priority: 'normal', start_type: 'now', scheduled_at: '', deadline: '' })
