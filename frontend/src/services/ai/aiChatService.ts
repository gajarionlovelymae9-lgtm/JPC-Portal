import { sendMessage, type AiLanguagePreference } from '../../api/ai/aiChatApi'
import { getApiErrorMessage } from '../../utils/apiError'

/** Flat chat message shape consumed by the chat window and dashboard assistant. */
export interface AiChatMessage {
  id: string | number
  conversation_id: string | null
  role: 'user' | 'assistant' | 'tool' | 'system'
  content: string
  intent?: string | null
  tool_results?: unknown[]
  created_at: string
  /** Alias kept for components that use camelCase. */
  createdAt?: string
}

export const aiChatService = {
  /**
   * Send a chat message and normalise the Laravel envelope into a flat
   * assistant message the UI can append directly.
   *
   * Backend shape:
   *   { data: { message: { id, conversation_id, role, content, created_at },
   *             intent, period, results, actions } }
   */
  async sendMessage(
    conversationId: string | null,
    message: string,
    languagePreference?: AiLanguagePreference | null,
  ): Promise<AiChatMessage> {
    try {
      const response = await sendMessage(conversationId, message, languagePreference)
      const payload = (response.data ?? {}) as Record<string, any>
      const raw = (payload.message ?? {}) as Record<string, any>
      const createdAt: string = raw.created_at ?? new Date().toISOString()

      return {
        id: raw.id ?? `assistant-${Date.now()}`,
        conversation_id: raw.conversation_id ?? conversationId,
        role: (raw.role as AiChatMessage['role']) ?? 'assistant',
        content: typeof raw.content === 'string' ? raw.content : '',
        intent: raw.intent ?? payload.intent ?? null,
        // The assistant reply text is already grounded in the tool data. The
        // legacy result cards expect a different payload shape, so we keep the
        // list empty rather than render a mismatched card.
        tool_results: [],
        created_at: createdAt,
        createdAt,
      }
    } catch (error) {
      const msg = getApiErrorMessage(error, 'AI service is temporarily unavailable. Please try again.')
      throw new Error(msg)
    }
  },
}
