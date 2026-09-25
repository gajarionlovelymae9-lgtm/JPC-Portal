import { useState, useCallback, useEffect } from 'react'
import { getConversations, createConversation as apiCreateConversation, deleteConversation as apiDeleteConversation } from '../../api/ai/aiConversationApi'
import { getApiErrorMessage } from '../../utils/apiError'

export function useAiConversations() {
  const [conversations, setConversations] = useState<any[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectedConversation, setSelectedConversation] = useState<string | null>(null)

  const loadConversations = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await getConversations()
      setConversations((response.data as any[]) || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load conversations'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadConversations()
  }, [loadConversations])

  const createConversation = useCallback(async (title: string) => {
    try {
      const response = await apiCreateConversation(title)
      setConversations((prev) => [response.data, ...prev])
      return response.data
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to create conversation'))
      throw err
    }
  }, [])

  const deleteConversation = useCallback(async (id: string) => {
    try {
      await apiDeleteConversation(id)
      setConversations((prev) => prev.filter((c) => c.id !== id))
      if (selectedConversation === id) {
        setSelectedConversation(null)
      }
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to delete conversation'))
      throw err
    }
  }, [selectedConversation])

  return {
    conversations,
    selectedConversation,
    setSelectedConversation,
    loading,
    error,
    loadConversations,
    createConversation,
    deleteConversation
  }
}
