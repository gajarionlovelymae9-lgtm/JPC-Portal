import { apiRequest } from '../../services/api'

export type AiLanguagePreference = 'auto' | 'english' | 'filipino' | 'tagalog' | 'taglish'

export interface AiUsageStatus {
  allowed: boolean
  role: string | null
  limit: number
  used: number
  remaining: number
  resets_at: string
}

export async function sendMessage(
  conversationId: string | null,
  message: string,
  languagePreference?: AiLanguagePreference | null,
) {
  return apiRequest('/ai/chat', {
    method: 'POST',
    body: {
      conversation_id: conversationId,
      message,
      ...(languagePreference ? { language_preference: languagePreference } : {}),
    },
  })
}

export async function getAiUsage() {
  const response = await apiRequest<AiUsageStatus>('/ai/usage', { method: 'GET' })
  return (response.data as AiUsageStatus) ?? null
}
