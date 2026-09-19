import { apiRequest } from './api'
import type { ApiResponse } from '../types/api'
import type {
  AssignTaskPayload,
  ReviewTaskPayload,
  TaskAssignment,
  TaskProgress,
  TaskReview,
  UpdateTaskProgressPayload,
} from '../types/task'

const ASSIGNMENTS = '/task-assignments'

/** GET /task-assignments/{assignment} — owner or manager only. */
export function getAssignment(
  assignmentId: number | string,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`${ASSIGNMENTS}/${assignmentId}`)
}

/** POST /task-assignments/{assignment}/accept — assigned → accepted. */
export function acceptAssignment(
  assignmentId: number | string,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`${ASSIGNMENTS}/${assignmentId}/accept`, {
    method: 'POST',
  })
}

/** POST /task-assignments/{assignment}/start — assigned/accepted → in_progress. */
export function startAssignment(
  assignmentId: number | string,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`${ASSIGNMENTS}/${assignmentId}/start`, {
    method: 'POST',
  })
}

/** POST /task-assignments/{assignment}/submit — in_progress/accepted → submitted. */
export function submitAssignment(
  assignmentId: number | string,
  remarks?: string | null,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`${ASSIGNMENTS}/${assignmentId}/submit`, {
    method: 'POST',
    body: { remarks: remarks ?? null },
  })
}

/** POST /task-assignments/{assignment}/resubmit — rejected → submitted. */
export function resubmitAssignment(
  assignmentId: number | string,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`${ASSIGNMENTS}/${assignmentId}/resubmit`, {
    method: 'POST',
  })
}

/** POST /task-assignments/{assignment}/review — manager decision. */
export function reviewAssignment(
  assignmentId: number | string,
  payload: ReviewTaskPayload,
): Promise<ApiResponse<TaskReview>> {
  return apiRequest<TaskReview>(`${ASSIGNMENTS}/${assignmentId}/review`, {
    method: 'POST',
    body: payload,
  })
}

/** POST /task-assignments/{assignment}/progress */
export function updateAssignmentProgress(
  assignmentId: number | string,
  payload: UpdateTaskProgressPayload,
): Promise<ApiResponse<TaskProgress>> {
  return apiRequest<TaskProgress>(`${ASSIGNMENTS}/${assignmentId}/progress`, {
    method: 'POST',
    body: payload,
  })
}

/** GET /task-assignments/{assignment}/progress — progress history. */
export function getAssignmentProgress(
  assignmentId: number | string,
): Promise<ApiResponse<TaskProgress[]>> {
  return apiRequest<TaskProgress[]>(`${ASSIGNMENTS}/${assignmentId}/progress`)
}

/** GET /task-assignments/{assignment}/reviews */
export function getAssignmentReviews(
  assignmentId: number | string,
): Promise<ApiResponse<TaskReview[]>> {
  return apiRequest<TaskReview[]>(`${ASSIGNMENTS}/${assignmentId}/reviews`)
}

/** POST /tasks/{task}/assignments — assign an employee to a task. */
export function assignEmployee(
  taskId: number | string,
  payload: AssignTaskPayload,
): Promise<ApiResponse<TaskAssignment>> {
  return apiRequest<TaskAssignment>(`/tasks/${taskId}/assignments`, {
    method: 'POST',
    body: payload,
  })
}

/** GET /tasks/{task}/assignments */
export function listTaskAssignments(
  taskId: number | string,
): Promise<ApiResponse<TaskAssignment[]>> {
  return apiRequest<TaskAssignment[]>(`/tasks/${taskId}/assignments`)
}
