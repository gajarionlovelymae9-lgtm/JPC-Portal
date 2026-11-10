import { useEffect, useState } from 'react'
import { useAiChat } from '../../hooks/ai/useAiChat'
import { useAiConversations } from '../../hooks/ai/useAiConversations'
import { AiConversationList } from '../../components/ai/AiConversationList'
import { AiConversationHeader } from '../../components/ai/AiConversationHeader'
import { AiChatWindow } from '../../components/ai/AiChatWindow'
import { AiChatInput } from '../../components/ai/AiChatInput'
import {
  AI_LANGUAGE_OPTIONS,
  getAiLanguagePreference,
  updateAiLanguagePreference,
  type AiLanguagePreference,
} from '../../api/ai/aiLanguagePreferenceApi'

export default function AiChatPage() {
  const [languagePreference, setLanguagePreference] = useState<AiLanguagePreference>('auto')
  const [savingLanguage, setSavingLanguage] = useState(false)

  const {
    conversations,
    selectedConversation,
    setSelectedConversation,
    loadConversations,
    deleteConversation
  } = useAiConversations()

  const {
    messages,
    loading: chatLoading,
    error: chatError,
    sendMessage,
    currentConversationId,
    setCurrentConversationId
  } = useAiChat(selectedConversation, languagePreference)

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const pref = await getAiLanguagePreference()
        if (!cancelled && pref?.ai_language_preference) setLanguagePreference(pref.ai_language_preference)
      } catch {
        // Auto-detect by default.
      }
    })()
    return () => { cancelled = true }
  }, [])

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

  // Send the message, then refresh the conversation list so a newly created
  // conversation (or an updated title/timestamp) shows up without a page reload.
  const handleSend = async (message: string) => {
    const result = await sendMessage(message)
    if (result) {
      void loadConversations()
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
          <div className="mb-3 flex items-center justify-end">
            <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
              <span>Language</span>
              <select
                value={languagePreference}
                onChange={(e) => {
                  const next = e.target.value as AiLanguagePreference
                  const prev = languagePreference
                  setLanguagePreference(next)
                  setSavingLanguage(true)
                  void updateAiLanguagePreference(next).catch(() => setLanguagePreference(prev)).finally(() => setSavingLanguage(false))
                }}
                disabled={savingLanguage || chatLoading}
                className="rounded-md border border-slate-200 bg-white px-2 py-1 text-sm text-slate-700"
                aria-label="AI language preference"
              >
                {AI_LANGUAGE_OPTIONS.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            </label>
          </div>
          <AiChatInput onSend={handleSend} disabled={chatLoading} />
        </div>
      </div>
    </div>
  )
}
