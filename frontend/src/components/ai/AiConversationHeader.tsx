import { JpcAideLogo, JPC_AIDE_NAME } from './JpcAideLogo'

interface AiConversationHeaderProps {
  title?: string
  onNewChat: () => void
  onDelete?: () => void
}

export function AiConversationHeader({ title, onNewChat, onDelete }: AiConversationHeaderProps) {
  return (
    <div className="flex items-center justify-between p-4 border-b bg-white">
      <div className="flex items-center gap-2 min-w-0">
        <JpcAideLogo size={22} className="shrink-0" />
        <div className="min-w-0">
          <p className="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">{JPC_AIDE_NAME}</p>
          <h2 className="text-base font-semibold text-gray-800 truncate max-w-[100%]">
            {title || 'New Conversation'}
          </h2>
        </div>
      </div>
      <div className="flex items-center gap-2">
        {onDelete && (
          <button 
            onClick={onDelete}
            className="text-sm text-red-600 hover:text-red-800 px-3 py-1.5 border border-red-200 rounded hover:bg-red-50"
          >
            Delete
          </button>
        )}
        <button 
          onClick={onNewChat}
          className="text-sm text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded"
        >
          + New Chat
        </button>
      </div>
    </div>
  )
}
