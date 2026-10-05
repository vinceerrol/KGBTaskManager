export type UserRole = 'ceo' | 'team_lead' | 'employee'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  is_active?: boolean
  teams?: Team[]
  active_tasks_count?: number
  due_today_count?: number
  overdue_count?: number
  created_at?: string
}

export interface Team {
  id: number
  name: string
  description?: string
  members?: User[]
  members_count?: number
  active_tasks_count?: number
  due_today_count?: number
  overdue_count?: number
  created_at?: string
  tasks?: Task[]
}

export type TaskStatus = 'SCHEDULED' | 'IN PROGRESS' | 'DONE'
export type TaskPriority = 'normal' | 'high' | 'urgent'

export interface TaskAttachment {
  id: number
  task_id: number
  uploaded_by: number
  uploader?: User
  file_name: string
  /** Signed, expiring download link; legacy files keep their original public URL. */
  url: string
  mime_type: string
  file_size: number
  created_at: string
}

export interface TaskActivity {
  id: number
  task_id: number
  user_id: number
  user?: User
  action: string
  metadata?: Record<string, any>
  created_at: string
}

export interface TaskComment {
  id: number
  task_id: number
  user_id: number
  user?: User
  body: string
  created_at: string
}

export interface Task {
  id: number
  title: string
  description?: string | null
  created_by: number
  creator?: User
  assigned_to?: number | null
  assignee?: User | null
  assignees?: User[]
  team_id?: number | null
  team?: Team | null
  status: TaskStatus
  priority: TaskPriority
  scheduled_at?: string | null
  deadline?: string | null
  started_at?: string | null
  completed_at?: string | null
  completion_note?: string | null
  is_overdue?: boolean
  attachments?: TaskAttachment[]
  activities?: TaskActivity[]
  comments?: TaskComment[]
  created_at: string
  updated_at: string
}

export interface TaskTemplate {
  id: number
  title: string
  description?: string | null
  team_id?: number | null
  team?: Team | null
  priority: TaskPriority
  default_instructions?: string | null
  created_at: string
}

export interface RecurringTask {
  id: number
  title: string
  description?: string | null
  team_id?: number | null
  team?: Team | null
  assigned_to?: number | null
  assignee?: User | null
  priority: TaskPriority
  frequency: 'daily' | 'weekly' | 'monthly'
  scheduled_time: string // e.g. "09:00"
  is_active: boolean
  last_run_at?: string | null
  next_run_at?: string | null
  created_at: string
}

export interface AppNotification {
  id: number
  user_id: number
  title: string
  message: string
  task_id?: number | null
  read_at?: string | null
  created_at: string
}

export interface DashboardStats {
  tasks_today: number
  in_progress: number
  scheduled: number
  overdue: number
  completed: number
  attention_needed: {
    overdue_tasks: Task[]
    starting_soon: Task[]
    due_today: Task[]
  }
}

export interface TaskFilterOptions {
  status?: string
  team_id?: number | string
  assigned_to?: number | string
  priority?: string
  date_filter?: 'all' | 'today' | 'tomorrow' | 'week'
  search?: string
}

export interface CreateTaskPrefill {
  title?: string
  description?: string | null
  team_id?: number | string | null
  assigned_to?: number | null
  assignee_ids?: number[]
  priority?: TaskPriority
}
