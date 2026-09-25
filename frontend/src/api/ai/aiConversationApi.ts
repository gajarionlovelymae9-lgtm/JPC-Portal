import { apiRequest } from '../../services/api'

export async function getConversations() {
  return apiRequest('/ai/conversations', { method: 'GET' })
}

export async function createConversation(title: string) {
  return apiRequest('/ai/conversations', {
    method: 'POST',
    body: { title },
  })
}

export async function getConversation(id: string) {
  return apiRequest(`/ai/conversations/${id}`, { method: 'GET' })
}

export async function deleteConversation(id: string) {
  return apiRequest(`/ai/conversations/${id}`, { method: 'DELETE' })
}

export async function getMessages(conversationId: string) {
  return apiRequest(`/ai/conversations/${conversationId}/messages`, { method: 'GET' })
}
