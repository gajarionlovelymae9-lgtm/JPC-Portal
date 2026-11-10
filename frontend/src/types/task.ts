/**
 * Planning & Task Management domain types.
 *
 * These mirror the Phase 3.3-F API resources
 * (`App\Http\Resources\Api\V1\TaskManagement\*`) exactly. The frontend treats
 * the API as the single source of truth — no business/workflow rules are
 * duplicated here, only the shapes used for presentation.
 */

export type TaskStatus =
  | 'draft'
  | 'assigned'
  | 'in_progress'
  | 'submitted'
  | 'under_review'
  | 'completed'
  | 'rejected'
  | 'cancelled'

export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export type AssignmentStatus =
  | 'assigned'
  | 'accepted'
  | 'in_progress'
  | 'submitted'
  | 'completed'
  | 'rejected'
  | 'cancelled'

export type ReviewDecision = 'approved' | 'rejected' | 'needs_revision'

export type TaskSort =
  | 'created_at'
  | 'updated_at'
  | 'due_date'
  | 'priority'
  | 'status'
  | 'title'

export interface AnnualPlanRef {
  id: number
  academic_year_id: number
  department_id: number
  title: string
}

export interface DepartmentPlanRef {
  id: number
  annual_plan_id: number
  department_id: number
  title: string
}

export interface DepartmentTargetRef {
  id: number
  department_plan_id: number
  title: string
}

export interface EmployeeTargetRef {
  id: number
  employee_id: number
  department_target_id: number
  title: string
}

/** Employee stub embedded in assignments (`EmployeeResource`). */
export interface TaskEmployee {
  id: number
  employee_number: string
  name: string
  department_id: number
}

export interface TaskAssignment {
  id: number
  task_id: number
  employee_id: number
  employee?: TaskEmployee
  status: AssignmentStatus
  latest_review_decision?: string | null
  assigned_at: string | null
  accepted_at: string | null
  due_date: string | null
  completed_at: string | null
  /** Backed by `progress_percentage`; the API may serialize it as a string. */
  completion_percentage: number | string | null
  remarks: string | null
  is_overdue: boolean
  /** Versioned submission history, newest first when returned by the list endpoint. */
  submissions?: TaskSubmission[]
}

/** The four ways an employee can hand in work. */
export type SubmissionMethod = 'upload' | 'link' | 'folder' | 'written'

export interface TaskAttachment {
  id: number
  file_name: string
  mime_type: string | null
  file_size: number | null
  download_url: string
  uploaded_at: string | null
}

export interface TaskSubmission {
  id: number
  task_id: number
  task_assignment_id: number
  submitted_by: number
  submitted_by_name?: string | null
  method: SubmissionMethod | string
  url: string | null
  written_response: string | null
  notes: string | null
  version: number
  submitted_at: string | null
  attachments: TaskAttachment[]
}

export interface Task {
  id: number
  task_code: string
  title: string
  description: string | null
  priority: TaskPriority | string
  status: TaskStatus | string
  start_date: string | null
  due_date: string | null
  completed_at: string | null
  estimated_hours: number | string | null
  is_overdue: boolean
  annual_plan?: AnnualPlanRef | null
  department_plan?: DepartmentPlanRef | null
  department_target?: DepartmentTargetRef | null
  employee_target?: EmployeeTargetRef | null
  assignments?: TaskAssignment[]
}

export interface TaskProgress {
  id: number
  task_id: number
  task_assignment_id: number
  employee_id: number
  previous_percentage: number | string | null
  new_percentage: number | string | null
  status: string
  comment: string | null
  created_at: string | null
}

export interface TaskReview {
  id: number
  task_id: number
  task_assignment_id: number
  reviewed_by: number
  decision: ReviewDecision | string
  remarks: string | null
  reviewed_at: string | null
}

/** Employee-scoped counts from `/tasks/me/summary`. */
export interface MyTaskSummary {
  total: number
  completed: number
  in_progress: number
  submitted: number
  under_review: number
  rejected: number
  overdue: number
}

/** Organisation-wide counts from `/tasks/summary`. */
export interface TaskSummary {
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  submitted_tasks: number
  under_review_tasks: number
  rejected_tasks: number
  cancelled_tasks: number
}

export interface MonthlyTaskProgress {
  total: number
  completed: number
  pending: number
  overdue: number
  on_time: number
  late: number
  submitted: number
  under_review: number
}

export interface EmployeeProgressRow {
  employee_id: number
  employee_name: string
  assigned_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  completion_rate: number
}

export interface DepartmentProgressRow {
  department_id: number
  department_name: string
  total_tasks: number
  completed_tasks: number
  pending_tasks: number
  overdue_tasks: number
  completion_rate: number
}

export interface MonthlyAchievementRow {
  rank: number
  employee_id: number
  employee_name: string
  completed_tasks: number
  completion_rate: number
}

/** Query parameters accepted by `GET /tasks` and `GET /tasks/me`. */
export interface TaskQueryParams {
  status?: string
  priority?: string
  department_id?: number | string
  employee_id?: number | string
  annual_plan_id?: number | string
  department_plan_id?: number | string
  department_target_id?: number | string
  employee_target_id?: number | string
  due_date_from?: string
  due_date_to?: string
  search?: string
  sort?: TaskSort | string
  direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface CreateTaskPayload {
  title: string
  description?: string | null
  priority: string
  department_id: number
  due_date: string
  start_date?: string | null
  estimated_hours?: number | null
  annual_plan_id?: number | null
  required_output?: string | null
  target_value?: number | null
  target_unit?: string | null
  /** Employees to assign in the same transaction as the task creation. */
  assigned_employee_ids?: number[]
}

export interface UpdateTaskPayload {
  title?: string
  description?: string | null
  priority?: string
  department_id?: number
  start_date?: string | null
  due_date?: string
  estimated_hours?: number | null
  annual_plan_id?: number | null
  required_output?: string | null
  target_value?: number | null
  target_unit?: string | null
}

export interface AssignTaskPayload {
  employee_id: number
  due_date: string
  remarks?: string | null
}

export interface UpdateTaskProgressPayload {
  percentage: number
  comment?: string | null
}

export interface ReviewTaskPayload {
  decision: ReviewDecision
  remarks: string
}

/**
 * Content for a Submit Task action. The service converts this into multipart
 * FormData so files travel with the selected method and optional notes.
 */
export interface SubmitTaskPayload {
  method: SubmissionMethod
  notes?: string | null
  url?: string | null
  writtenResponse?: string | null
  files?: File[]
}

/** Presentation model consumed by `TaskTimeline`. */
export interface TaskTimelineItem {
  id: number | string
  title: string
  subtitle?: string
  description?: string
  at?: string | null
  /** Visual tone: `ok` | `error` | `warning` | `info` | `muted`. */
  tone?: 'ok' | 'error' | 'warning' | 'info' | 'muted'
}
