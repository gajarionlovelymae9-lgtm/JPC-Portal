import { useEffect } from 'react'
import { useAiChat } from '../../hooks/ai/useAiChat'
import { useAiConversations } from '../../hooks/ai/useAiConversations'
import { AiConversationList } from '../../components/ai/AiConversationList'
import { AiConversationHeader } from '../../components/ai/AiConversationHeader'
import { AiChatWindow } from '../../components/ai/AiChatWindow'
import { AiChatInput } from '../../components/ai/AiChatInput'
import { AiSuggestionList } from '../../components/ai/AiSuggestionList'

export default function AiChatPage() {
  const {
    conversations,
    selectedConversation,
    setSelectedConversation,
    deleteConversation
  } = useAiConversations()

  const {
    messages,
    loading: chatLoading,
    error: chatError,
    sendMessage,
    currentConversationId,
    setCurrentConversationId
  } = useAiChat(selectedConversation)

  // Sync selected conversation with chat hook
  useEffect(() => {
    setCurrentConversationId(selectedConversation)
  }, [selectedConversation, setCurrentConversationId])

  const handleNewChat = () => {
    setSelectedConversation(null)
  }

  const handleDelete = () => {
    if (selectedConversation) {
      if (window.confirm('Are you sure you want to delete this conversation?')) {
        void deleteConversation(selectedConversation)
      }
    }
  }

  const currentConvData = conversations.find(c => c.id === currentConversationId)

  return (
    <div className="flex h-[calc(100vh-4rem)] bg-white overflow-hidden border-t">
      {/* Left Sidebar */}
      <div className="w-64 flex-shrink-0 border-r hidden md:block">
        <AiConversationList 
          conversations={conversations} 
          activeId={currentConversationId} 
          onSelect={setSelectedConversation}
          onNewChat={handleNewChat}
        />
      </div>

      {/* Main Chat Area */}
      <div className="flex-1 flex flex-col relative">
        <AiConversationHeader 
          title={currentConvData?.title} 
          onNewChat={handleNewChat}
          onDelete={currentConversationId ? handleDelete : undefined}
        />
        
        {chatError && (
          <div className="bg-red-50 text-red-600 p-3 text-sm text-center border-b border-red-200">
            {chatError}
          </div>
        )}

        <AiChatWindow messages={messages} loading={chatLoading} />
        
        <div className="p-4 bg-white border-t">
          {messages.length === 0 && (
            <div className="mb-4">
              <AiSuggestionList onSelectSuggestion={sendMessage} />
            </div>
          )}
          <AiChatInput onSend={sendMessage} disabled={chatLoading} />
        </div>
      </div>
    </div>
  )
}
