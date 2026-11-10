import { apiRequest, toQueryString } from './api'

const BASE = '/employee/ai-assistant'

export interface EmployeeAiMessage {
  id: number | string
  conversation_id: number | string | null
  role: 'user' | 'assistant' | 'system' | 'tool'
  content: string
  intent?: string | null
  tool_results?: unknown
  created_at: string
}

export interface EmployeeAiConversation {
  id: number | string
  title: string | null
  context_summary?: string | null
  is_archived?: boolean
  message_count?: number
  last_message_at?: string | null
  created_at: string
  updated_at?: string | null
}

export interface EmployeeAiSavedSummary {
  id: number
  conversation_id: number | null
  related_task_id?: number | null
  title: string | null
  summary_type: string | null
  summary_content: string
  metadata?: Record<string, unknown> | null
  created_at: string | null
  updated_at?: string | null
}

export interface EmployeeAiUsageStatus {
  allowed: boolean
  role: string | null
  limit: number
  used: number
  remaining: number
  resets_at: string
}

export interface EmployeeAiChatResponse {
  conversation_id: number
  conversation?: EmployeeAiConversation | null
  message: EmployeeAiMessage
  /** The persisted user message, so the UI can replace the optimistic bubble. */
  user_message?: EmployeeAiMessage | null
  ok: boolean
}

export interface EmployeeAiSummaryTransfer {
  summaryId?: number
  title?: string | null
  summaryType?: string | null
  content: string
  conversationId?: number | null
  appliedAt: string
}

const TRANSFER_KEY = 'epms.aiAssistant.summaryToReport'

export function setSummaryTransfer(payload: Omit<EmployeeAiSummaryTransfer, 'appliedAt'>) {
  sessionStorage.setItem(TRANSFER_KEY, JSON.stringify({ ...payload, appliedAt: new Date().toISOString() }))
}

export function getSummaryTransfer(): EmployeeAiSummaryTransfer | null {
  try {
    const raw = sessionStorage.getItem(TRANSFER_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as EmployeeAiSummaryTransfer
    return parsed && typeof parsed.content === 'string' ? parsed : null
  } catch {
    return null
  }
}

export function clearSummaryTransfer() {
  sessionStorage.removeItem(TRANSFER_KEY)
}

export const employeeAiAssistantService = {
  async chat(
    conversationId: string | number | null,
    message: string,
    languagePreference?: string | null,
  ): Promise<EmployeeAiChatResponse> {
    const response = await apiRequest<EmployeeAiChatResponse>(`${BASE}/chat`, {
      method: 'POST',
      body: {
        conversation_id: conversationId,
        message,
        ...(languagePreference ? { language_preference: languagePreference } : {}),
      },
    })
    return (response.data as EmployeeAiChatResponse) ?? (response as unknown as EmployeeAiChatResponse)
  },

  async listConversations(params: { q?: string; page?: number; per_page?: number } = {}) {
    const response = await apiRequest<unknown>(`${BASE}/conversations${toQueryString(params)}`, { method: 'GET' })
    const data = response.data as any
    if (Array.isArray(data)) return { data, meta: null }
    if (data?.data && Array.isArray(data.data)) return { data: data.data as EmployeeAiConversation[], meta: data }
    return { data: (Array.isArray(data) ? data : []) as EmployeeAiConversation[], meta: null }
  },

  async createConversation(title?: string) {
    const response = await apiRequest<EmployeeAiConversation>(`${BASE}/conversations`, {
      method: 'POST',
      body: { title },
    })
    return response.data as EmployeeAiConversation
  },

  async renameConversation(id: string | number, title: string) {
    const response = await apiRequest<EmployeeAiConversation>(`${BASE}/conversations/${id}`, {
      method: 'PATCH',
      body: { title },
    })
    return response.data as EmployeeAiConversation
  },

  async deleteConversation(id: string | number) {
    await apiRequest(`${BASE}/conversations/${id}`, { method: 'DELETE' })
  },

  async getMessages(conversationId: string | number) {
    const response = await apiRequest<EmployeeAiMessage[]>(`${BASE}/conversations/${conversationId}/messages`, { method: 'GET' })
    return (response.data as EmployeeAiMessage[]) ?? []
  },

  async listSummaries(conversationId?: string | number | null) {
    const qs = conversationId ? toQueryString({ conversation_id: conversationId }) : ''
    const response = await apiRequest<EmployeeAiSavedSummary[]>(`${BASE}/summaries${qs}`, { method: 'GET' })
    return (response.data as EmployeeAiSavedSummary[]) ?? []
  },

  async saveSummary(payload: {
    conversation_id: number | string
    assistant_message_id?: number | string | null
    summary_content?: string
    title?: string
    summary_type?: string
    related_task_id?: number | string | null
  }) {
    const response = await apiRequest<EmployeeAiSavedSummary>(`${BASE}/summaries`, {
      method: 'POST',
      body: payload,
    })
    return response.data as EmployeeAiSavedSummary
  },

  async deleteSummary(id: number | string) {
    await apiRequest(`${BASE}/summaries/${id}`, { method: 'DELETE' })
  },

  async getUsage(): Promise<EmployeeAiUsageStatus | null> {
    const response = await apiRequest<EmployeeAiUsageStatus>(`${BASE}/usage`, { method: 'GET' })
    return (response.data as EmployeeAiUsageStatus) ?? null
  },
}
