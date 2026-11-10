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

/** A row in the People Directory (authorized recipients only). */
export interface DirectoryUser {
  id: number
  name: string
  email: string
  role: string
  role_label: string
  department: string | null
  position: string | null
  status: string
  avatar_url: string | null
  initials: string
}

export interface ConversationParticipant {
  user: {
    id: number
    name: string
    email: string
    role?: string
    department?: string | null
    position?: string | null
    avatar_url?: string | null
  }
  role?: string
  is_admin?: boolean
  last_read_at?: string | null
  archived?: boolean
}

export interface MessageAttachment {
  name: string
  type: string
  url: string
}

export interface MessageReplyRef {
  id: number
  body: string
  sender_name: string | null
}

export interface ConversationSummary {
  id: number
  subject: string | null
  description: string | null
  icon: string | null
  type: string
  is_group: boolean
  created_by: number
  participants: ConversationParticipant[]
  latest_message?: { id: number; body: string; sender: { id: number; name: string }; created_at: string } | null
  unread_count: number
  last_message_at: string | null
  viewer_id: number
}

export interface MessageItem {
  id: number
  sender: { id: number; name: string }
  body: string
  attachment?: MessageAttachment | null
  attachment_name?: string | null
  reply_to?: MessageReplyRef | null
  read_by_count?: number
  is_read?: boolean
  is_mine?: boolean
  created_at: string
}

export interface ConversationDetail {
  id: number
  subject: string | null
  description: string | null
  icon: string | null
  type: string
  is_group: boolean
  created_by: number
  participants: ConversationParticipant[]
  messages: MessageItem[]
  read_receipts?: Array<{ user_id: number; name: string | null; last_read_at: string | null }>
  last_message_at?: string | null
  created_at?: string | null
}

export interface UnreadSummary {
  unread_conversations: number
  unread_messages: number
}

/** @deprecated use DirectoryUser */
export type RecipientOption = DirectoryUser

export interface AnnouncementItem {
  id: number
  title: string
  message: string
  priority: string
  category?: string
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

// ─── Internal Messaging (§1–§11) ────────────────────────────────
export interface SendMessagePayload {
  conversation_id?: number
  recipient_id?: number
  subject?: string
  body?: string
  reply_to_message_id?: number
  /** Idempotency key so a double submit cannot create two messages. */
  client_token?: string
}

export interface SendMessageResult {
  message_id: number
  conversation_id: number
}

export const getConversations = (params?: { filter?: string; search?: string }) =>
  apiRequest<ConversationSummary[]>(`/messages/conversations${toQueryString(params ?? {})}`).then((r) => r.data)

export const getConversation = (id: number, sinceId?: number) =>
  apiRequest<ConversationDetail>(
    `/messages/conversations/${id}${toQueryString({ since_id: sinceId })}`,
  ).then((r) => r.data)

export const getUnreadMessageSummary = () =>
  apiRequest<UnreadSummary>('/messages/unread-summary').then((r) => r.data)

export const getRecipients = (params?: { search?: string; role?: string; department_id?: number }) =>
  apiRequest<DirectoryUser[]>(`/messages/recipients${toQueryString(params ?? {})}`).then((r) => r.data)

export const sendMessage = (data: SendMessagePayload, attachment?: File | null) => {
  if (attachment) {
    const form = new FormData()
    Object.entries(data).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') form.append(key, String(value))
    })
    form.append('attachment', attachment)
    return apiRequest<SendMessageResult>('/messages', { method: 'POST', body: form }).then((r) => r.data)
  }
  return apiRequest<SendMessageResult>('/messages', { method: 'POST', body: data }).then((r) => r.data)
}

export const createGroupConversation = (data: {
  name: string
  description?: string
  icon?: string
  participant_ids: number[]
}) =>
  apiRequest<ConversationSummary>('/messages/groups', { method: 'POST', body: data }).then((r) => r.data)

export const updateGroupConversation = (
  id: number,
  data: { name?: string; description?: string; icon?: string },
) =>
  apiRequest<ConversationSummary>(`/messages/conversations/${id}`, { method: 'PUT', body: data }).then((r) => r.data)

export const addGroupParticipants = (id: number, participantIds: number[]) =>
  apiRequest<{ added: number }>(`/messages/conversations/${id}/participants`, {
    method: 'POST',
    body: { participant_ids: participantIds },
  }).then((r) => r.data)

export const removeGroupParticipant = (id: number, userId: number) =>
  apiRequest<null>(`/messages/conversations/${id}/participants/${userId}`, { method: 'DELETE' }).then(() => undefined)

export const leaveConversation = (id: number) =>
  apiRequest<null>(`/messages/conversations/${id}/leave`, { method: 'POST' }).then(() => undefined)

export const markConversationRead = (id: number) =>
  apiRequest<null>(`/messages/conversations/${id}/read`, { method: 'POST' }).then(() => undefined)

export const markMessageRead = (messageId: number) =>
  apiRequest<null>(`/messages/${messageId}/read`, { method: 'POST' }).then(() => undefined)

export const archiveConversation = (id: number, archived = true) =>
  apiRequest<null>(`/messages/conversations/${id}/archive`, {
    method: 'POST',
    body: { archived },
  }).then(() => undefined)

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

// Admin — Notification centre detail actions (View Details / View Recipients
// / Mark as Read). These extend — not replace — the governance list above.
export interface NotificationRecipientRow {
  notification_id: number
  name: string
  email: string
  employee_number: string | null
  department: string | null
  delivery_status: string
  is_read: boolean
  read_at: string | null
}

export interface NotificationRecipientsResult {
  notification: {
    id: number
    type: string
    title: string
    priority: string
  }
  summary: {
    total: number
    delivered: number
    failed: number
    read: number
    unread: number
  }
  recipients: NotificationRecipientRow[]
}

export interface NotificationRelatedRecord {
  type: 'announcement' | 'task'
  id: number
  label: string
}

export interface NotificationDetail {
  id: number
  type: string
  title: string
  message: string | null
  priority: string
  is_read: boolean
  read_at: string | null
  recipient_count?: number
  triggered_by?: string
  department?: string | null
  employee_number?: string | null
  delivery_channel?: string
  delivery_status?: string
  related?: NotificationRelatedRecord | null
  task_id?: number | null
  recipient?: string
  recipient_email?: string
  created_at: string
}

export const getNotificationDetails = (id: number): Promise<NotificationDetail> => {
  return apiRequest<NotificationDetail>(`/admin/notifications-centre/${id}`).then(
    (res) => (res as unknown as { data: NotificationDetail }).data,
  )
}

export const getNotificationRecipients = (id: number): Promise<NotificationRecipientsResult> => {
  return apiRequest<NotificationRecipientsResult>(`/admin/notifications-centre/${id}/recipients`).then(
    (res) => (res as unknown as { data: NotificationRecipientsResult }).data,
  )
}

export const adminMarkNotificationRead = (id: number): Promise<NotificationDetail> => {
  return apiRequest<NotificationDetail>(`/admin/notifications-centre/${id}/read`, {
    method: 'POST',
  }).then((res) => (res as unknown as { data: NotificationDetail }).data)
}

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

export const scheduleAnnouncement = (id: number, publishedAt: string) =>
  apiRequest<AnnouncementItem>(`/admin/announcements/${id}/schedule`, {
    method: 'POST',
    body: { published_at: publishedAt },
  })

export const unscheduleAnnouncement = (id: number) =>
  apiRequest<AnnouncementItem>(`/admin/announcements/${id}/schedule`, {
    method: 'DELETE',
  })

export const archiveAnnouncement = (id: number) =>
  apiRequest<AnnouncementItem>(`/admin/announcements/${id}/archive`, {
    method: 'POST',
  })
