
import { sanitize } from '../../utils/ai/sanitizeAiContent'

interface AiChatMessageProps {
  message: {
    role: string
    content: string
    tool_results?: any[]
  }
}

export function AiChatMessage({ message }: AiChatMessageProps) {
  // Do not render internal system/tool messages
  if (message.role === 'system' || message.role === 'tool') {
    return null
  }

  const isUser = message.role === 'user'
  const sanitizedContent = sanitize(message.content)

  return (
    <div className={`flex w-full my-4 ${isUser ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[80%] rounded-lg p-4 text-sm shadow ${
          isUser
            ? 'bg-blue-600 text-white rounded-br-none'
            : 'bg-white text-gray-800 border rounded-bl-none'
        }`}
      >
        <div className="whitespace-pre-wrap font-sans">{sanitizedContent}</div>
      </div>
    </div>
  )
}
