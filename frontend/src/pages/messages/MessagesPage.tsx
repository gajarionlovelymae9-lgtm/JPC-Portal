import { useState, useEffect, useRef } from 'react'
import {
  getConversations,
  getConversation,
  sendMessage,
  getRecipients,
  type ConversationSummary,
  type ConversationDetail,
  type RecipientOption,
} from '../../services/notificationCenterService'
import { useAuth } from '../../auth/useAuth'

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  return `${days}d ago`
}

export default function MessagesPage() {
  const { user } = useAuth()
  const [conversations, setConversations] = useState<ConversationSummary[]>([])
  const [activeId, setActiveId] = useState<number | null>(null)
  const [activeDetail, setActiveDetail] = useState<ConversationDetail | null>(null)
  const [loadingList, setLoadingList] = useState(true)
  const [loadingThread, setLoadingThread] = useState(false)
  const [replyText, setReplyText] = useState('')
  const [sending, setSending] = useState(false)

  // New Message Modal State
  const [showModal, setShowModal] = useState(false)
  const [recipients, setRecipients] = useState<RecipientOption[]>([])
  const [selectedRecipientId, setSelectedRecipientId] = useState<number | ''>('')
  const [newSubject, setNewSubject] = useState('')
  const [newBody, setNewBody] = useState('')
  const [modalSubmitting, setModalSubmitting] = useState(false)
  const [modalError, setModalError] = useState<string | null>(null)

  const messagesEndRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    loadConversations()
    loadRecipients()
  }, [])

  useEffect(() => {
    if (activeId) {
      loadThread(activeId)
    }
  }, [activeId])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [activeDetail?.messages])

  const loadConversations = async () => {
    setLoadingList(true)
    try {
      const res = await getConversations()
      const dataAny = res as unknown
      const list: ConversationSummary[] = Array.isArray(dataAny)
        ? dataAny
        : (dataAny as { data?: ConversationSummary[] })?.data ?? []
      setConversations(list)
      if (list.length > 0 && !activeId) {
        setActiveId(list[0].id)
      }
    } catch {
      setConversations([])
    } finally {
      setLoadingList(false)
    }
  }

  const loadRecipients = async () => {
    try {
      const res = await getRecipients()
      const dataAny = res as unknown
      const list: RecipientOption[] = Array.isArray(dataAny)
        ? dataAny
        : (dataAny as { data?: RecipientOption[] })?.data ?? []
      setRecipients(list)
    } catch {
      setRecipients([])
    }
  }

  const loadThread = async (id: number) => {
    setLoadingThread(true)
    try {
      const res = await getConversation(id)
      const dataAny = res as unknown
      const detail = (dataAny as { data?: ConversationDetail })?.data ?? (dataAny as ConversationDetail)
      setActiveDetail(detail)
      // Clear unread in local list
      setConversations((prev) =>
        prev.map((c) => (c.id === id ? { ...c, unread_count: 0 } : c))
      )
    } catch {
      setActiveDetail(null)
    } finally {
      setLoadingThread(false)
    }
  }

  const handleSendReply = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!replyText.trim() || !activeDetail) return

    // Find the other participant to reply to
    const other = activeDetail.participants.find((p) => p.user.id !== user?.id)
    if (!other) return

    setSending(true)
    try {
      await sendMessage({
        recipient_id: other.user.id,
        subject: activeDetail.subject ?? undefined,
        body: replyText.trim(),
      })
      setReplyText('')
      await loadThread(activeDetail.id)
      await loadConversations()
    } catch {
      // ignore
    } finally {
      setSending(false)
    }
  }

  const handleStartConversation = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!selectedRecipientId || !newBody.trim()) {
      setModalError('Please select a recipient and enter your message.')
      return
    }

    setModalSubmitting(true)
    setModalError(null)
    try {
      await sendMessage({
        recipient_id: Number(selectedRecipientId),
        subject: newSubject.trim() || undefined,
        body: newBody.trim(),
      })
      setShowModal(false)
      setSelectedRecipientId('')
      setNewSubject('')
      setNewBody('')
      await loadConversations()
    } catch (err: unknown) {
      setModalError((err as Error).message || 'Failed to send message.')
    } finally {
      setModalSubmitting(false)
    }
  }

  return (
    <div className="p-6 md:p-8 max-w-7xl mx-auto space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
        <div>
          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <span>💬</span> Internal Communications
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Secure intra-departmental and administrative messaging channels.
          </p>
        </div>

        <button
          onClick={() => setShowModal(true)}
          className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded-2xl shadow-sm transition-all"
        >
          <span>✍️</span> New Message
        </button>
      </div>

      {/* Split Pane Container */}
      <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden grid grid-cols-1 md:grid-cols-12 min-h-[620px]">
        {/* Left Pane: Conversation List */}
        <div className="md:col-span-5 lg:col-span-4 border-r border-gray-100 flex flex-col">
          <div className="p-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <h2 className="font-extrabold text-sm text-gray-900 uppercase tracking-wider">
              Conversations
            </h2>
            <span className="text-xs font-bold text-gray-500">{conversations.length} total</span>
          </div>

          <div className="flex-1 overflow-y-auto divide-y divide-gray-50">
            {loadingList ? (
              <div className="p-8 text-center text-sm text-gray-400">Loading conversations...</div>
            ) : conversations.length === 0 ? (
              <div className="p-8 text-center text-gray-500 space-y-2">
                <span className="text-3xl block">📭</span>
                <p className="text-sm font-semibold">No messages yet</p>
                <p className="text-xs text-gray-400">Start a new thread with authorized staff.</p>
              </div>
            ) : (
              conversations.map((conv) => {
                const isActive = conv.id === activeId
                const other = conv.participants.find((p) => p.user.id !== user?.id)?.user

                return (
                  <button
                    key={conv.id}
                    onClick={() => setActiveId(conv.id)}
                    className={`w-full text-left p-4 hover:bg-blue-50/40 transition-colors flex items-start gap-3 ${
                      isActive ? 'bg-blue-50/70 border-l-4 border-blue-600' : ''
                    }`}
                  >
                    <div className="w-10 h-10 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold flex items-center justify-center text-sm flex-shrink-0 shadow-sm">
                      {other?.name ? other.name.charAt(0).toUpperCase() : 'U'}
                    </div>

                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between gap-1 mb-0.5">
                        <h4 className="font-bold text-sm text-gray-900 truncate">
                          {other?.name ?? 'System User'}
                        </h4>
                        {conv.last_message_at && (
                          <span className="text-[10px] text-gray-400 font-medium">
                            {timeAgo(conv.last_message_at)}
                          </span>
                        )}
                      </div>

                      {conv.subject && (
                        <p className="text-xs font-semibold text-gray-700 truncate mb-0.5">
                          {conv.subject}
                        </p>
                      )}

                      <p className="text-xs text-gray-500 truncate">
                        {conv.latest_message?.body ?? 'No messages yet'}
                      </p>
                    </div>

                    {conv.unread_count > 0 && (
                      <span className="bg-blue-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full flex-shrink-0">
                        {conv.unread_count}
                      </span>
                    )}
                  </button>
                )
              })
            )}
          </div>
        </div>

        {/* Right Pane: Message Thread */}
        <div className="md:col-span-7 lg:col-span-8 flex flex-col bg-gray-50/30">
          {activeDetail ? (
            <>
              {/* Thread Header */}
              <div className="p-4 bg-white border-b border-gray-100 flex items-center justify-between shadow-xs">
                <div className="flex items-center gap-3 min-w-0">
                  <div className="w-10 h-10 rounded-full bg-blue-100 text-blue-800 font-extrabold flex items-center justify-center text-sm flex-shrink-0">
                    {activeDetail.participants
                      .find((p) => p.user.id !== user?.id)
                      ?.user.name.charAt(0)
                      .toUpperCase() ?? 'U'}
                  </div>
                  <div className="min-w-0">
                    <h3 className="font-extrabold text-sm text-gray-900 truncate">
                      {activeDetail.participants.find((p) => p.user.id !== user?.id)?.user.name ??
                        'Conversation'}
                    </h3>
                    <p className="text-xs text-gray-500 truncate">
                      {activeDetail.subject ? `Subject: ${activeDetail.subject}` : 'Direct Message'}
                    </p>
                  </div>
                </div>
              </div>

              {/* Messages Container */}
              <div className="flex-1 p-6 overflow-y-auto space-y-4 max-h-[500px]">
                {loadingThread ? (
                  <div className="text-center text-sm text-gray-400 py-12">Loading messages...</div>
                ) : activeDetail.messages.length === 0 ? (
                  <div className="text-center text-sm text-gray-400 py-12">No messages in this conversation.</div>
                ) : (
                  activeDetail.messages.map((msg) => {
                    const isMine = msg.sender.id === user?.id

                    return (
                      <div
                        key={msg.id}
                        className={`flex flex-col ${isMine ? 'items-end' : 'items-start'}`}
                      >
                        <div
                          className={`max-w-[78%] rounded-2xl px-4 py-3 text-sm shadow-xs ${
                            isMine
                              ? 'bg-blue-600 text-white rounded-br-xs'
                              : 'bg-white border border-gray-100 text-gray-900 rounded-bl-xs'
                          }`}
                        >
                          <p className="whitespace-pre-line leading-relaxed">{msg.body}</p>
                        </div>
                        <span className="text-[10px] text-gray-400 mt-1 px-1 font-medium">
                          {msg.sender.name} · {timeAgo(msg.created_at)}
                        </span>
                      </div>
                    )
                  })
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Composer */}
              <form onSubmit={handleSendReply} className="p-4 bg-white border-t border-gray-100 flex gap-3 items-center">
                <input
                  type="text"
                  placeholder="Type your message..."
                  value={replyText}
                  onChange={(e) => setReplyText(e.target.value)}
                  className="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <button
                  type="submit"
                  disabled={sending || !replyText.trim()}
                  className="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-sm rounded-2xl shadow-sm transition-all"
                >
                  {sending ? 'Sending...' : 'Send'}
                </button>
              </form>
            </>
          ) : (
            <div className="flex-1 flex flex-col items-center justify-center p-12 text-center text-gray-400">
              <span className="text-4xl mb-3">💬</span>
              <p className="font-bold text-base text-gray-700">No conversation selected</p>
              <p className="text-xs text-gray-400 max-w-sm mt-1">
                Select an existing conversation from the list or start a new thread.
              </p>
            </div>
          )}
        </div>
      </div>

      {/* New Message Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-xs p-4">
          <div className="bg-white rounded-3xl border border-gray-100 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-scale-in">
            <div className="flex items-center justify-between border-b border-gray-100 pb-4">
              <h3 className="font-extrabold text-lg text-gray-900 flex items-center gap-2">
                <span>✍️</span> Compose New Message
              </h3>
              <button
                onClick={() => setShowModal(false)}
                className="text-gray-400 hover:text-gray-700 text-lg font-bold"
              >
                ✕
              </button>
            </div>

            {modalError && (
              <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl font-medium">
                {modalError}
              </div>
            )}

            <form onSubmit={handleStartConversation} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Recipient (Authorized Personnel) <span className="text-red-500">*</span>
                </label>
                <select
                  value={selectedRecipientId}
                  onChange={(e) => setSelectedRecipientId(e.target.value ? Number(e.target.value) : '')}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500"
                  required
                >
                  <option value="">-- Select Recipient --</option>
                  {recipients.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.name} ({r.role.toUpperCase()}) {r.department ? `· ${r.department}` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">Subject (Optional)</label>
                <input
                  type="text"
                  placeholder="e.g., Q1 Syllabus Target Clarification"
                  value={newSubject}
                  onChange={(e) => setNewSubject(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Message <span className="text-red-500">*</span>
                </label>
                <textarea
                  rows={4}
                  placeholder="Write your message here..."
                  value={newBody}
                  onChange={(e) => setNewBody(e.target.value)}
                  className="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div className="flex justify-end gap-3 pt-3 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={modalSubmitting}
                  className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-sm rounded-xl shadow-sm transition-all"
                >
                  {modalSubmitting ? 'Sending...' : 'Send Message'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
