
import { sanitize } from '../../utils/ai/sanitizeAiContent'
import { JpcAideLogo, JPC_AIDE_NAME } from './JpcAideLogo'

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
        {!isUser && (
          <div className="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">
            <JpcAideLogo size={12} />
            <span>{JPC_AIDE_NAME}</span>
            <span className="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold normal-case text-indigo-600">AI-generated</span>
          </div>
        )}
        <div className="whitespace-pre-wrap font-sans">{sanitizedContent}</div>
      </div>
    </div>
  )
}
