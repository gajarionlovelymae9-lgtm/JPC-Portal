import type { PaginationMeta } from './api'

/**
 * In-app notification inbox types.
 *
 * These mirror the Phase 3.3-I API resources
 * (`App\Http\Resources\Api\V1\Notifications\NotificationResource` and
 * `ActivityLogResource`) exactly. The frontend only shapes data for
 * presentation; sending/marking logic lives in the Laravel backend.
 */

export type NotificationCategory = 'deadline' | 'review' | 'task' | 'system'

export type NotificationPriority = 'normal' | 'important' | 'urgent'

export interface AppNotification {
  id: number
  type: string
  title: string
  message: string | null
  /** Free-form payload (task_id, task_code, …) emitted by the backend. */
  data: Record<string, unknown>
  priority: NotificationPriority | string
  read_at: string | null
  created_at: string | null
  /** Deep link to the related task detail page, when applicable. */
  action_url: string | null
  category: NotificationCategory | string
}

/** Append-only row from the activity_logs table (immutable). */
export interface ActivityLogItem {
  id: number
  activity_type: string
  action: string
  description: string | null
  user_id: number | null
  user_name: string | null
  /** Basename of the subject, e.g. `Task`. */
  subject_type: string | null
  subject_id: number | null
  metadata: Record<string, unknown>
  task_code: string | null
  ip_address: string | null
  user_agent: string | null
  created_at: string | null
}

export interface NotificationFilter {
  filter?: 'unread' | 'read'
  category?: NotificationCategory | string
  type?: string
  page?: number
  per_page?: number
}

export interface ActivityLogFilter {
  action?: string
  subject?: string
  subject_id?: number | string
  search?: string
  from?: string
  to?: string
  user_id?: number | string
  page?: number
  per_page?: number
}

export interface UnreadCount {
  count: number
}

/** One upcoming-deadline row from GET /me/tasks/upcoming. */
export interface UpcomingTask {
  assignment_id: number
  task_id: number
  task_code: string
  title: string
  priority: string
  due_date: string
  progress_percentage: number
  status: string
}

export type NotificationList = {
  data: AppNotification[]
  meta: PaginationMeta
}

export type ActivityLogList = {
  data: ActivityLogItem[]
  meta: PaginationMeta
}