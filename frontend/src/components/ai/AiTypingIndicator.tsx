
import { JpcAideLogo, JPC_AIDE_NAME } from './JpcAideLogo'

export function AiTypingIndicator() {
  return (
    <div className="flex items-center space-x-2 text-gray-500 p-4">
      <JpcAideLogo size={18} className="shrink-0" />
      <span className="flex space-x-1">
        <span className="animate-bounce inline-block w-2 h-2 bg-indigo-400 rounded-full" style={{ animationDelay: '0ms' }} />
        <span className="animate-bounce inline-block w-2 h-2 bg-purple-400 rounded-full" style={{ animationDelay: '150ms' }} />
        <span className="animate-bounce inline-block w-2 h-2 bg-blue-400 rounded-full" style={{ animationDelay: '300ms' }} />
      </span>
      <span className="text-sm font-medium">{JPC_AIDE_NAME} is analyzing...</span>
    </div>
  )
}
