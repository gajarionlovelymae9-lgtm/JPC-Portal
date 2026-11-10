import { useState, useCallback, useEffect, useRef } from 'react'
import { aiChatService } from '../../services/ai/aiChatService'
import type { AiChatMessage } from '../../services/ai/aiChatService'
import type { AiLanguagePreference } from '../../api/ai/aiChatApi'
import { deleteConversation, getMessages } from '../../api/ai/aiConversationApi'
import { getApiErrorMessage } from '../../utils/apiError'

export function useAiChat(
  initialConversationId: string | null = null,
  languagePreference?: AiLanguagePreference | null,
) {
  const [messages, setMessages] = useState<AiChatMessage[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [currentConversationId, setCurrentConversationId] = useState<string | null>(initialConversationId)

  // The first assistant reply sets the conversation id. We already have both
  // messages locally, so skip the follow-up server reload it would trigger.
  const skipNextLoad = useRef(false)

  useEffect(() => {
    if (!currentConversationId) {
      setMessages([])
      return
    }

    if (skipNextLoad.current) {
      skipNextLoad.current = false
      return
    }

    let cancelled = false

    const loadMessages = async () => {
      setLoading(true)
      setError(null)
      try {
        const response = await getMessages(currentConversationId)
        if (cancelled) return

        const items = ((response.data as any[]) || []).map((item: any) => ({
          ...item,
          createdAt: item.created_at,
        }))

        setMessages(items)
      } catch (err) {
        if (!cancelled) {
          setError(getApiErrorMessage(err, 'Failed to load messages'))
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    void loadMessages()

    return () => {
      cancelled = true
    }
  }, [currentConversationId])

  const sendTextMessage = useCallback(
    async (text: string) => {
      setLoading(true)
      setError(null)

      // Optimistic UI for the user's message.
      const tempUserMsg: AiChatMessage = {
        id: `temp-${Date.now()}`,
        conversation_id: currentConversationId,
        role: 'user',
        content: text,
        created_at: new Date().toISOString(),
        createdAt: new Date().toISOString(),
      }
      setMessages((prev) => [...prev, tempUserMsg])

      try {
        const responseData = await aiChatService.sendMessage(currentConversationId, text, languagePreference)

        // A new conversation id arrives with the first assistant reply.
        if (!currentConversationId && responseData.conversation_id) {
          skipNextLoad.current = true
          setCurrentConversationId(responseData.conversation_id)
        }

        setMessages((prev) => [...prev, responseData])
        return responseData
      } catch (err: any) {
        setError(err?.message || 'Error sending message')
      } finally {
        setLoading(false)
      }
    },
    [currentConversationId, languagePreference],
  )

  /**
   * Clear the visible conversation. The server thread is archived (never
   * deleted) so history is not lost, and the widget starts a fresh thread on
   * the next message.
   */
  const clearConversation = useCallback(async () => {
    const conversationId = currentConversationId

    setMessages([])
    setError(null)
    setCurrentConversationId(null)

    if (!conversationId) return

    try {
      await deleteConversation(conversationId)
    } catch {
      // Best-effort archive; the local thread is already cleared.
    }
  }, [currentConversationId])

  return {
    messages,
    loading,
    error,
    sendMessage: sendTextMessage,
    currentConversationId,
    setCurrentConversationId,
    setMessages,
    clearConversation,
  }
}
