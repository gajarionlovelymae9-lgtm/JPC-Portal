import { apiRequest } from '../../services/api'

export async function sendMessage(conversationId: string | null, message: string) {
  return apiRequest('/ai/chat', {
    method: 'POST',
    body: { conversationId, message },
  })
}
