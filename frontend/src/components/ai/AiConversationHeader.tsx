

interface AiConversationHeaderProps {
  title?: string
  onNewChat: () => void
  onDelete?: () => void
}

export function AiConversationHeader({ title, onNewChat, onDelete }: AiConversationHeaderProps) {
  return (
    <div className="flex items-center justify-between p-4 border-b bg-white">
      <h2 className="text-lg font-semibold text-gray-800 truncate max-w-[60%]">
        {title || 'New Conversation'}
      </h2>
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
