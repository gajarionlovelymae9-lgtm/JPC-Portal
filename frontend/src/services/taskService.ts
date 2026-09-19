import { apiRequest, toQueryString } from './api'
import type { ApiResponse } from '../types/api'
import type { UpcomingTask } from '../types/notification'
import type {
  CreateTaskPayload,
  MonthlyTaskProgress,
  MyTaskSummary,
  Task,
  TaskQueryParams,
  TaskSummary,
  UpdateTaskPayload,
} from '../types/task'

const TASKS = '/tasks'

/** GET /tasks — paginated, filterable, sortable task list. */
export function listTasks(params: TaskQueryParams = {}): Promise<ApiResponse<Task[]>> {
  return apiRequest<Task[]>(`${TASKS}${toQueryString({ ...params })}`)
}

/** GET /tasks/{task} */
export function getTask(taskId: number | string): Promise<ApiResponse<Task>> {
  return apiRequest<Task>(`${TASKS}/${taskId}`)
}

/** POST /tasks */
export function createTask(payload: CreateTaskPayload): Promise<ApiResponse<Task>> {
  return apiRequest<Task>(TASKS, { method: 'POST', body: payload })
}

/** PUT /tasks/{task} */
export function updateTask(
  taskId: number | string,
  payload: UpdateTaskPayload,
): Promise<ApiResponse<Task>> {
  return apiRequest<Task>(`${TASKS}/${taskId}`, { method: 'PUT', body: payload })
}

/** DELETE /tasks/{task} — soft delete, 204 with an empty body. */
export function deleteTask(taskId: number | string): Promise<ApiResponse<null>> {
  return apiRequest<null>(`${TASKS}/${taskId}`, { method: 'DELETE' })
}

/** POST /tasks/{task}/cancel */
export function cancelTask(taskId: number | string): Promise<ApiResponse<Task>> {
  return apiRequest<Task>(`${TASKS}/${taskId}/cancel`, { method: 'POST' })
}

/** GET /tasks/summary — organisation-wide counts (manager/admin only). */
export function getTaskSummary(): Promise<ApiResponse<TaskSummary>> {
  return apiRequest<TaskSummary>(`${TASKS}/summary`)
}

/** GET /tasks/monthly-progress?year=&month= */
export function getMonthlyProgress(
  year: number,
  month: number,
): Promise<ApiResponse<MonthlyTaskProgress>> {
  return apiRequest<MonthlyTaskProgress>(
    `${TASKS}/monthly-progress${toQueryString({ year, month })}`,
  )
}

/** GET /tasks/me — tasks assigned to the authenticated employee. */
export function getMyTasks(params: TaskQueryParams = {}): Promise<ApiResponse<Task[]>> {
  return apiRequest<Task[]>(`${TASKS}/me${toQueryString({ ...params })}`)
}

/** GET /my/tasks/summary — employee-scoped counts (alias of /tasks/me/summary). */
export function getMyTaskSummary(): Promise<ApiResponse<MyTaskSummary>> {
  return apiRequest<MyTaskSummary>(`${TASKS}/me/summary`)
}

/**
 * GET /me/tasks/upcoming — actionable assignments with due dates from today
 * onwards, ordered by nearest due date. Drives the employee dashboard's
 * upcoming-deadlines panel.
 */
export function getMyUpcomingTasks(): Promise<ApiResponse<UpcomingTask[]>> {
  return apiRequest<UpcomingTask[]>('/me/tasks/upcoming')
}

/** GET /tasks/employees/{employee}/tasks — manager or self only. */
export function getEmployeeTasks(
  employeeId: number | string,
  params: TaskQueryParams = {},
): Promise<ApiResponse<Task[]>> {
  return apiRequest<Task[]>(
    `${TASKS}/employees/${employeeId}/tasks${toQueryString({ ...params })}`,
  )
}
