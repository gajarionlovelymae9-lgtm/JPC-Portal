import { apiRequest, toQueryString } from './api'

// ─── Types ──────────────────────────────────────────────────────
export interface NotificationItem {
  id: number
  type: string
  title: string
  message: string | null
  priority: string
  data: Record<string, unknown> | null
  read_at: string | null
  created_at: string
}

export interface UnreadCount {
  count: number
}

export interface NotifPreference {
  event: string
  in_app_enabled: boolean
  email_enabled: boolean
  is_critical?: boolean
}

export interface ConversationSummary {
  id: number
  subject: string | null
  type: string
  participants: Array<{ user: { id: number; name: string; email: string } }>
  latest_message?: { id: number; body: string; sender: { id: number; name: string }; created_at: string } | null
  unread_count: number
  last_message_at: string | null
}

export interface MessageItem {
  id: number
  sender: { id: number; name: string }
  body: string
  attachment_name?: string | null
  created_at: string
}

export interface ConversationDetail {
  id: number
  subject: string | null
  type: string
  participants: Array<{ user: { id: number; name: string; email: string } }>
  messages: MessageItem[]
}

export interface RecipientOption {
  id: number
  name: string
  email: string
  role: string
  department?: string | null
}

export interface AnnouncementItem {
  id: number
  title: string
  message: string
  priority: string
  audience_type: string
  audience_id: string | null
  status: string
  start_date: string | null
  end_date: string | null
  published_at: string | null
  created_at: string
  creator?: { id: number; name: string }
}

export interface NotificationMetrics {
  total_sent: number
  in_app_count: number
  email_count: number
  failed_count: number
  queued_count: number
  read_rate: number
}

export interface NotificationLogItem {
  id: number
  notification_id: number | null
  user_id: number
  user?: { id: number; name: string; email: string }
  type: string
  channel: string
  status: string
  error_message: string | null
  retry_count: number
  sent_at: string | null
  failed_at: string | null
  created_at: string
}

// ─── API Methods ────────────────────────────────────────────────

// Notifications
export const getNotifications = (params?: Record<string, unknown>) =>
  apiRequest<{ data: NotificationItem[] }>(`/notifications${toQueryString(params ?? {})}`)

export const getUnreadCount = () =>
  apiRequest<{ count: number }>('/notifications/unread-count')

export const markAsRead = (id: number) =>
  apiRequest<NotificationItem>(`/notifications/${id}/read`, { method: 'POST' })

export const markAllAsRead = () =>
  apiRequest<{ updated: number }>('/notifications/read-all', { method: 'POST' })

export const deleteNotification = (id: number) =>
  apiRequest<void>(`/notifications/${id}`, { method: 'DELETE' })

// Preferences
export const getPreferences = () =>
  apiRequest<NotifPreference[]>('/notifications/preferences')

export const updatePreferences = (payload: Record<string, { in_app: boolean; email: boolean }>) =>
  apiRequest<{ written: number }>('/notifications/preferences', {
    method: 'PUT',
    body: { preferences: payload },
  })

// Internal Messaging
export const getConversations = () =>
  apiRequest<ConversationSummary[]>('/messages/conversations')

export const getConversation = (id: number) =>
  apiRequest<ConversationDetail>(`/messages/conversations/${id}`)

export const sendMessage = (data: { recipient_id: number; subject?: string; body: string }) =>
  apiRequest<MessageItem>('/messages', {
    method: 'POST',
    body: data,
  })

export const markMessageRead = (messageId: number) =>
  apiRequest<void>(`/messages/${messageId}/read`, {
    method: 'POST',
  })

export const getRecipients = () =>
  apiRequest<RecipientOption[]>('/messages/recipients')

// Announcements (user-facing)
export const getActiveAnnouncements = () =>
  apiRequest<AnnouncementItem[]>('/announcements/active')

// Admin — Notification Monitoring
export const getNotificationMetrics = () =>
  apiRequest<NotificationMetrics>('/admin/notifications/metrics')

export const getNotificationLogs = (params?: Record<string, unknown>) =>
  apiRequest<{ data: NotificationLogItem[] }>(`/admin/notifications/logs${toQueryString(params ?? {})}`)

export const retryNotification = (logId: number) =>
  apiRequest<NotificationLogItem>(`/admin/notifications/${logId}/retry`, {
    method: 'POST',
  })

// Admin — Announcements
export const getAnnouncements = (params?: Record<string, unknown>) =>
  apiRequest<{ data: AnnouncementItem[] }>(`/admin/announcements${toQueryString(params ?? {})}`)

export const createAnnouncement = (data: Partial<AnnouncementItem>) =>
  apiRequest<AnnouncementItem>('/admin/announcements', {
    method: 'POST',
    body: data,
  })

export const updateAnnouncement = (id: number, data: Partial<AnnouncementItem>) =>
  apiRequest<AnnouncementItem>(`/admin/announcements/${id}`, {
    method: 'PUT',
    body: data,
  })

export const deleteAnnouncement = (id: number) =>
  apiRequest<void>(`/admin/announcements/${id}`, {
    method: 'DELETE',
  })

export const publishAnnouncement = (id: number) =>
  apiRequest<AnnouncementItem>(`/admin/announcements/${id}/publish`, {
    method: 'POST',
  })
