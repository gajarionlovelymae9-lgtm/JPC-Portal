import { apiRequest, toQueryString } from './api'
import type { ApiResponse } from '../types/api'
import type {
  ActivityLogFilter,
  ActivityLogItem,
  AppNotification,
  NotificationFilter,
  UnreadCount,
} from '../types/notification'

const NOTIFICATIONS = '/notifications'
const ACTIVITY_LOGS = '/activity-logs'

/** GET /notifications — paginated, filterable inbox for the signed-in user. */
export function listNotifications(
  params: NotificationFilter = {},
): Promise<ApiResponse<AppNotification[]>> {
  return apiRequest<AppNotification[]>(`${NOTIFICATIONS}${toQueryString({ ...params })}`)
}

/** GET /notifications/unread — unread notifications only. */
export function getUnreadNotifications(
  params: NotificationFilter = {},
): Promise<ApiResponse<AppNotification[]>> {
  return apiRequest<AppNotification[]>(`${NOTIFICATIONS}/unread${toQueryString({ ...params })}`)
}

/** GET /notifications/unread-count — fast badge counter for the header bell. */
export function getUnreadNotificationCount(): Promise<ApiResponse<UnreadCount>> {
  return apiRequest<UnreadCount>(`${NOTIFICATIONS}/unread-count`)
}

/** GET /notifications/{notification} */
export function getNotification(id: number | string): Promise<ApiResponse<AppNotification>> {
  return apiRequest<AppNotification>(`${NOTIFICATIONS}/${id}`)
}

/** POST /notifications/{notification}/read — marks a single notification read. */
export function markNotificationRead(id: number | string): Promise<ApiResponse<AppNotification>> {
  return apiRequest<AppNotification>(`${NOTIFICATIONS}/${id}/read`, { method: 'POST' })
}

/** POST /notifications/read-all — marks every unread notification read. */
export function markAllNotificationsRead(): Promise<ApiResponse<{ updated: number }>> {
  return apiRequest<{ updated: number }>(`${NOTIFICATIONS}/read-all`, { method: 'POST' })
}

/** DELETE /notifications/{notification} */
export function deleteNotification(id: number | string): Promise<ApiResponse<null>> {
  return apiRequest<null>(`${NOTIFICATIONS}/${id}`, { method: 'DELETE' })
}

/** GET /activity-logs — paginated, filterable append-only timeline. */
export function listActivityLogs(
  params: ActivityLogFilter = {},
): Promise<ApiResponse<ActivityLogItem[]>> {
  return apiRequest<ActivityLogItem[]>(`${ACTIVITY_LOGS}${toQueryString({ ...params })}`)
}

/** GET /activity-logs/{activityLog} */
export function getActivityLog(id: number | string): Promise<ApiResponse<ActivityLogItem>> {
  return apiRequest<ActivityLogItem>(`${ACTIVITY_LOGS}/${id}`)
}