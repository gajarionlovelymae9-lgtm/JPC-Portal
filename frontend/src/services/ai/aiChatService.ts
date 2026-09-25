import { sendMessage } from '../../api/ai/aiChatApi'
import { formatResponse } from '../../utils/ai/formatAiResponse'
import { getApiErrorMessage } from '../../utils/apiError'

export const aiChatService = {
  async sendMessage(conversationId: string | null, message: string) {
    try {
      const response = await sendMessage(conversationId, message)
      return formatResponse(response.data)
    } catch (error) {
      const msg = getApiErrorMessage(error, 'AI service is temporarily unavailable. Please try again.')
      throw new Error(msg)
    }
  },
}
