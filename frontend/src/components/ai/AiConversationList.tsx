

interface Conversation {
  id: string
  title: string
  last_message_at?: string
}

interface AiConversationListProps {
  conversations: Conversation[]
  activeId: string | null
  onSelect: (id: string) => void
  onNewChat: () => void
}

export function AiConversationList({ conversations, activeId, onSelect, onNewChat }: AiConversationListProps) {
  return (
    <div className="flex flex-col h-full bg-gray-50 border-r">
      <div className="p-4 border-b">
        <button 
          onClick={onNewChat}
          className="w-full bg-white border border-gray-300 hover:bg-gray-100 text-gray-800 font-medium py-2 px-4 rounded shadow-sm"
        >
          + New Chat
        </button>
      </div>
      <div className="flex-1 overflow-y-auto">
        {conversations.length === 0 ? (
          <div className="p-4 text-center text-sm text-gray-500">
            No conversations yet.
          </div>
        ) : (
          <ul className="divide-y divide-gray-200">
            {conversations.map(conv => (
              <li key={conv.id}>
                <button
                  onClick={() => onSelect(conv.id)}
                  className={`w-full text-left p-4 hover:bg-gray-100 transition-colors ${
                    activeId === conv.id ? 'bg-indigo-50 border-l-4 border-indigo-600' : ''
                  }`}
                >
                  <div className="font-medium text-gray-800 truncate">{conv.title || 'Untitled'}</div>
                  {conv.last_message_at && (
                    <div className="text-xs text-gray-500 mt-1">
                      {new Date(conv.last_message_at).toLocaleDateString()}
                    </div>
                  )}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
