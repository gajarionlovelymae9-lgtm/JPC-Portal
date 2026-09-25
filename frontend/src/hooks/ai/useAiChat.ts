import { useState, useCallback, useEffect } from 'react'
import { aiChatService } from '../../services/ai/aiChatService'
import { getMessages } from '../../api/ai/aiConversationApi'
import { getApiErrorMessage } from '../../utils/apiError'

export function useAiChat(initialConversationId: string | null = null) {
  const [messages, setMessages] = useState<any[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [currentConversationId, setCurrentConversationId] = useState<string | null>(initialConversationId)

  useEffect(() => {
    if (!currentConversationId) {
      setMessages([])
      return
    }

    const loadMessages = async () => {
      setLoading(true)
      setError(null)
      try {
        const response = await getMessages(currentConversationId)
        setMessages((response.data as any[]) || [])
      } catch (err) {
        setError(getApiErrorMessage(err, 'Failed to load messages'))
      } finally {
        setLoading(false)
      }
    }

    void loadMessages()
  }, [currentConversationId])

  const sendTextMessage = useCallback(async (text: string) => {
    setLoading(true)
    setError(null)

    // Optimistic UI for user message
    const tempUserMsg = { id: Date.now(), role: 'user', content: text, created_at: new Date().toISOString() }
    setMessages((prev) => [...prev, tempUserMsg])

    try {
      const responseData = await aiChatService.sendMessage(currentConversationId, text)
      
      // Update conversation ID if it's a new conversation
      if (!currentConversationId && responseData.conversation_id) {
        setCurrentConversationId(responseData.conversation_id)
      }

      setMessages((prev) => [...prev, responseData])
      return responseData
    } catch (err: any) {
      setError(err.message || 'Error sending message')
    } finally {
      setLoading(false)
    }
  }, [currentConversationId])

  return {
    messages,
    loading,
    error,
    sendMessage: sendTextMessage,
    currentConversationId,
    setCurrentConversationId,
    setMessages
  }
}
