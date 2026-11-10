import { apiRequest, toQueryString } from './api'

const BASE = '/admin/ai-assistant'

export interface AdminAiMessage {
  id: number | string
  conversation_id: number | string | null
  role: 'user' | 'assistant' | 'system' | 'tool'
  content: string
  intent?: string | null
  tool_results?: unknown
  created_at: string
}

export interface AdminAiConversation {
  id: number | string
  title: string | null
  context_summary?: string | null
  is_archived?: boolean
  message_count?: number
  last_message_at?: string | null
  created_at: string
  updated_at?: string | null
}

export interface AdminAiUsageStatus {
  allowed: boolean
  role: string | null
  limit: number
  used: number
  remaining: number
  resets_at: string
}

export interface AdminAiChatResponse {
  conversation_id: number
  conversation?: AdminAiConversation | null
  message: AdminAiMessage
  user_message?: AdminAiMessage | null
  ok: boolean
}

export const adminAiAssistantService = {
  async chat(
    conversationId: string | number | null,
    message: string,
    languagePreference?: string | null,
  ): Promise<AdminAiChatResponse> {
    const response = await apiRequest<AdminAiChatResponse>(`${BASE}/chat`, {
      method: 'POST',
      body: {
        conversation_id: conversationId,
        message,
        ...(languagePreference ? { language_preference: languagePreference } : {}),
      },
    })
    return (response.data as AdminAiChatResponse) ?? (response as unknown as AdminAiChatResponse)
  },

  async getUsage(): Promise<AdminAiUsageStatus | null> {
    const response = await apiRequest<AdminAiUsageStatus>(`${BASE}/usage`, { method: 'GET' })
    return (response.data as AdminAiUsageStatus) ?? null
  },

  async listConversations(params: { q?: string; page?: number; per_page?: number } = {}) {
    const response = await apiRequest<unknown>(`${BASE}/conversations${toQueryString(params)}`, { method: 'GET' })
    const data = response.data as any
    if (Array.isArray(data)) return { data: data as AdminAiConversation[], meta: null }
    if (data?.data && Array.isArray(data.data)) return { data: data.data as AdminAiConversation[], meta: data }
    return { data: [] as AdminAiConversation[], meta: null }
  },

  async createConversation(title?: string) {
    const response = await apiRequest<AdminAiConversation>(`${BASE}/conversations`, {
      method: 'POST',
      body: { title },
    })
    return response.data as AdminAiConversation
  },

  async renameConversation(id: string | number, title: string) {
    const response = await apiRequest<AdminAiConversation>(`${BASE}/conversations/${id}`, {
      method: 'PATCH',
      body: { title },
    })
    return response.data as AdminAiConversation
  },

  async deleteConversation(id: string | number) {
    await apiRequest(`${BASE}/conversations/${id}`, { method: 'DELETE' })
  },

  async getMessages(conversationId: string | number) {
    const response = await apiRequest<AdminAiMessage[]>(`${BASE}/conversations/${conversationId}/messages`, { method: 'GET' })
    return (response.data as AdminAiMessage[]) ?? []
  },
}
