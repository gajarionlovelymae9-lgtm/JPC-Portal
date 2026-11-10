import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { ShieldCheck } from 'lucide-react'
import { JpcAideLogo, JPC_AIDE_NAME, JPC_AIDE_TAGLINE } from '../../components/ai/JpcAideLogo'
import { formatAssistantContent } from '../../utils/ai/formatAssistantContent'
import { getApiErrorMessage } from '../../utils/apiError'
import {
  AI_LANGUAGE_OPTIONS,
  getAiLanguagePreference,
  updateAiLanguagePreference,
  type AiLanguagePreference,
} from '../../api/ai/aiLanguagePreferenceApi'
import {
  adminAiAssistantService,
  type AdminAiConversation,
  type AdminAiMessage,
  type AdminAiUsageStatus,
} from '../../services/adminAiAssistantService'

type Notice = { type: 'success' | 'error' | 'info'; message: string } | null

function formatDate(value?: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

function shortDate(value?: string | null): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

function TypingIndicator() {
  return (
    <div className="flex items-center gap-2 px-4 py-3 text-sm text-slate-500">
      <JpcAideLogo size={18} className="shrink-0" />
      <span className="flex gap-1">
        <span className="h-2 w-2 animate-bounce rounded-full bg-indigo-400 [animation-delay:0ms]" />
        <span className="h-2 w-2 animate-bounce rounded-full bg-purple-400 [animation-delay:150ms]" />
        <span className="h-2 w-2 animate-bounce rounded-full bg-blue-400 [animation-delay:300ms]" />
      </span>
      {JPC_AIDE_NAME} is analyzing authorized records...
    </div>
  )
}

export default function AdminAiAssistantPage() {
  const [conversations, setConversations] = useState<AdminAiConversation[]>([])
  const [selectedConversationId, setSelectedConversationId] = useState<number | null>(null)
  const [messages, setMessages] = useState<AdminAiMessage[]>([])
  const [input, setInput] = useState('')
  const [search, setSearch] = useState('')
  const [loadingConversations, setLoadingConversations] = useState(false)
  const [loadingMessages, setLoadingMessages] = useState(false)
  const [sending, setSending] = useState(false)
  const [notice, setNotice] = useState<Notice>(null)
  const [showHistory, setShowHistory] = useState(true)
  const [renameTarget, setRenameTarget] = useState<AdminAiConversation | null>(null)
  const [renameValue, setRenameValue] = useState('')
  const [deleteTarget, setDeleteTarget] = useState<AdminAiConversation | null>(null)
  const [languagePreference, setLanguagePreference] = useState<AiLanguagePreference>('auto')
  const [savingLanguage, setSavingLanguage] = useState(false)
  const [usage, setUsage] = useState<AdminAiUsageStatus | null>(null)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const selectedConversation = useMemo(
    () => conversations.find((c) => Number(c.id) === selectedConversationId) ?? null,
    [conversations, selectedConversationId],
  )

  const scrollToBottom = () => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  useEffect(() => { scrollToBottom() }, [messages, sending])

  const loadUsage = useCallback(async () => {
    try {
      const data = await adminAiAssistantService.getUsage()
      if (data) setUsage(data)
    } catch {
      // Usage is informational; keep the chat usable.
    }
  }, [])

  const loadConversations = useCallback(async (q = '') => {
    setLoadingConversations(true)
    try {
      const result = await adminAiAssistantService.listConversations({ q: q || undefined, per_page: 50 })
      setConversations(Array.isArray(result.data) ? result.data : [])
    } catch (e) {
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to load conversation history.') })
    } finally {
      setLoadingConversations(false)
    }
  }, [])

  const loadMessages = useCallback(async (conversationId: number) => {
    setLoadingMessages(true)
    try {
      const items = await adminAiAssistantService.getMessages(conversationId)
      setMessages(Array.isArray(items) ? items : [])
    } catch (e) {
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to load conversation messages.') })
      setMessages([])
    } finally {
      setLoadingMessages(false)
    }
  }, [])

  useEffect(() => { void loadConversations() }, [loadConversations])
  useEffect(() => { void loadUsage() }, [loadUsage])
  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const pref = await getAiLanguagePreference()
        if (!cancelled && pref?.ai_language_preference) setLanguagePreference(pref.ai_language_preference)
      } catch {
        // Default to auto-detect.
      }
    })()
    return () => { cancelled = true }
  }, [])
  useEffect(() => {
    if (selectedConversationId) void loadMessages(selectedConversationId)
    else setMessages([])
  }, [selectedConversationId, loadMessages])

  const handleNewChat = async () => {
    setNotice(null)
    try {
      const conv = await adminAiAssistantService.createConversation('New Chat')
      setConversations((prev) => [conv, ...prev.filter((c) => String(c.id) !== String(conv.id))])
      setSelectedConversationId(Number(conv.id))
      setMessages([])
      setInput('')
    } catch (e) {
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to start a new chat.') })
    }
  }

  const handleSend = async () => {
    const text = input.trim()
    if (!text || sending) return
    if (usage && usage.remaining <= 0) {
      setNotice({ type: 'error', message: 'You have reached your daily AI usage limit. Please try again tomorrow.' })
      return
    }

    setSending(true)
    setNotice(null)
    setInput('')

    const optimistic: AdminAiMessage = {
      id: `temp-${Date.now()}`,
      conversation_id: selectedConversationId,
      role: 'user',
      content: text,
      created_at: new Date().toISOString(),
    }
    setMessages((prev) => [...prev, optimistic])

    try {
      const result = await adminAiAssistantService.chat(selectedConversationId, text, languagePreference)
      if (result.conversation_id && !selectedConversationId) setSelectedConversationId(Number(result.conversation_id))

      setMessages((prev) => {
        const next = prev.filter((m) => String(m.id) !== String(optimistic.id))
        const persistedUser = result.user_message ?? null
        if (persistedUser && !next.some((m) => String(m.id) === String(persistedUser.id))) next.push(persistedUser)
        else if (!persistedUser) next.push(optimistic)
        if (result.message && !next.some((m) => String(m.id) === String(result.message.id))) next.push(result.message)
        return next
      })

      if (result.conversation) {
        setConversations((prev) => [result.conversation as AdminAiConversation, ...prev.filter((c) => String(c.id) !== String(result.conversation_id))])
      } else {
        void loadConversations(search)
      }

      if (!result.ok) {
        setNotice({ type: 'error', message: `${JPC_AIDE_NAME} could not complete the live AI response. Review the message below for details.` })
      }
    } catch (e) {
      setMessages((prev) => prev.filter((m) => String(m.id) !== String(optimistic.id)))
      setInput(text)
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to send your message.') })
    } finally {
      setSending(false)
      void loadUsage()
    }
  }

  const handleRename = async () => {
    if (!renameTarget || !renameValue.trim()) return
    try {
      const updated = await adminAiAssistantService.renameConversation(renameTarget.id, renameValue.trim())
      setConversations((prev) => prev.map((c) => (String(c.id) === String(updated.id) ? { ...c, ...updated } : c)))
      setRenameTarget(null)
      setRenameValue('')
      setNotice({ type: 'success', message: 'Conversation renamed.' })
    } catch (e) {
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to rename conversation.') })
    }
  }

  const handleDeleteConversation = async () => {
    if (!deleteTarget) return
    try {
      await adminAiAssistantService.deleteConversation(deleteTarget.id)
      setConversations((prev) => prev.filter((c) => String(c.id) !== String(deleteTarget.id)))
      if (String(selectedConversationId) === String(deleteTarget.id)) {
        setSelectedConversationId(null)
        setMessages([])
      }
      setDeleteTarget(null)
      setNotice({ type: 'success', message: 'Conversation deleted.' })
    } catch (e) {
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to delete conversation.') })
    }
  }

  const handleLanguagePreferenceChange = async (next: AiLanguagePreference) => {
    const previous = languagePreference
    setLanguagePreference(next)
    setSavingLanguage(true)
    try {
      await updateAiLanguagePreference(next)
    } catch (e) {
      setLanguagePreference(previous)
      setNotice({ type: 'error', message: getApiErrorMessage(e, 'Failed to save language preference.') })
    } finally {
      setSavingLanguage(false)
    }
  }

  return (
    <div className="space-y-4 p-4 sm:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-start gap-3">
          <div className="mt-0.5 rounded-2xl bg-gradient-to-br from-blue-50 to-purple-50 p-2 ring-1 ring-indigo-100">
            <JpcAideLogo size={34} />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-slate-900">{JPC_AIDE_NAME} <span className="ml-2 rounded-full bg-slate-900 px-2 py-0.5 align-middle text-[11px] font-semibold uppercase tracking-wide text-white">Admin</span></h1>
            <p className="text-sm font-medium text-indigo-700">{JPC_AIDE_TAGLINE}</p>
            <p className="mt-0.5 text-sm text-slate-500">Ask your own questions about authorized JPC Portal records. Responses are AI-generated, grounded in available evidence, and must be verified before administrative action.</p>
            {usage && (
              <div className="mt-1.5 flex items-center gap-2">
                <span className={`inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-semibold ${usage.remaining > 0 ? 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-700/10' : 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-700/10'}`}>
                  Daily AI Requests: {usage.remaining} / {usage.limit} remaining
                </span>
              </div>
            )}
          </div>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          <label className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            <span>Language Preference</span>
            <select
              value={languagePreference}
              onChange={(e) => void handleLanguagePreferenceChange(e.target.value as AiLanguagePreference)}
              disabled={savingLanguage}
              className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 disabled:opacity-60"
              aria-label="AI language preference"
            >
              {AI_LANGUAGE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </label>
          <button type="button" onClick={() => setShowHistory((v) => !v)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 lg:hidden">{showHistory ? 'Hide History' : 'Show History'}</button>
          <button type="button" onClick={handleNewChat} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">New Chat</button>
        </div>
      </div>

      {notice && (
        <div className={`rounded-lg border px-4 py-3 text-sm ${notice.type === 'error' ? 'border-red-200 bg-red-50 text-red-700' : notice.type === 'success' ? 'border-green-200 bg-green-50 text-green-700' : 'border-blue-200 bg-blue-50 text-blue-700'}`}>
          <div className="flex items-start justify-between gap-3">
            <span>{notice.message}</span>
            <button type="button" onClick={() => setNotice(null)} className="text-current opacity-70 hover:opacity-100">×</button>
          </div>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside className={`${showHistory ? 'block' : 'hidden'} space-y-4 lg:block`}>
          <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-slate-900">Conversation History</h2>
              {loadingConversations && <span className="text-xs text-slate-400">Loading…</span>}
            </div>
            <form onSubmit={(e) => { e.preventDefault(); void loadConversations(search) }} className="mb-3 flex gap-2">
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search conversations" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
              <button type="submit" className="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Search</button>
            </form>
            <div className="max-h-[520px] space-y-2 overflow-y-auto pr-1">
              {conversations.length === 0 ? (
                <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-500">No conversations yet. Start a new chat to ask your first question.</p>
              ) : conversations.map((conv) => {
                const active = String(conv.id) === String(selectedConversationId)
                return (
                  <div key={String(conv.id)} className={`rounded-lg border p-3 ${active ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300'}`}>
                    <button type="button" onClick={() => setSelectedConversationId(Number(conv.id))} className="w-full text-left">
                      <div className="truncate text-sm font-medium text-slate-900">{conv.title || 'Untitled conversation'}</div>
                      <div className="mt-1 text-xs text-slate-500">{shortDate(conv.last_message_at || conv.updated_at || conv.created_at)} · {conv.message_count ?? 0} messages</div>
                    </button>
                    <div className="mt-2 flex gap-2">
                      <button type="button" onClick={() => { setRenameTarget(conv); setRenameValue(conv.title || '') }} className="text-xs font-medium text-blue-700 hover:underline">Rename</button>
                      <button type="button" onClick={() => setDeleteTarget(conv)} className="text-xs font-medium text-red-600 hover:underline">Delete</button>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
          <div
            role="note"
            aria-label="JPC AIDE AI Safety Notice"
            className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 shadow-sm"
          >
            <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-200">
              <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            </span>
            <div className="min-w-0 space-y-1">
              <p className="text-xs font-semibold text-slate-900">JPC AIDE – AI Safety Notice</p>
              <p className="text-[11px] leading-relaxed text-slate-600">
                AI-generated responses are based on available authorized JPC Portal records. Please verify important
                information before taking administrative action. Final decisions remain with authorized personnel.
              </p>
              <p className="text-[11px] font-medium text-amber-700">
                Human review is required for important administrative decisions.
              </p>
            </div>
          </div>
        </aside>

        <section className="flex min-h-[70vh] flex-col rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-200 px-4 py-3 sm:px-5">
            <div className="flex items-center gap-2">
              <JpcAideLogo size={22} />
              <div>
                <h2 className="text-base font-semibold text-slate-900">{selectedConversation?.title || 'New conversation'}</h2>
                <p className="text-xs text-slate-500">Admin-only. Real AI responses require a configured OpenAI-compatible provider in the backend environment.</p>
              </div>
            </div>
          </div>

          <div className="flex-1 space-y-4 overflow-y-auto bg-slate-50/60 px-4 py-4 sm:px-5">
            {loadingMessages ? (
              <div className="rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm">Loading messages…</div>
            ) : messages.length === 0 ? (
              <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex items-start gap-3">
                  <JpcAideLogo size={36} className="shrink-0" />
                  <div>
                    <p className="text-sm font-semibold text-slate-900">Start a conversation with {JPC_AIDE_NAME}</p>
                    <p className="mt-2 text-sm text-slate-600">Type your own question in the message box below. The assistant can help summarize and explain authorized employee reports, tasks, progress, and related administrative information when the AI provider is configured.</p>
                    <p className="mt-2 text-sm text-slate-500">This chat opens empty by design. No predefined prompts or sample conversations are shown.</p>
                  </div>
                </div>
              </div>
            ) : null}

            {messages.filter((m) => m.role === 'user' || m.role === 'assistant').map((message) => {
              const isUser = message.role === 'user'
              return (
                <div key={String(message.id)} className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
                  <div className={`max-w-[92%] rounded-2xl px-4 py-3 shadow-sm sm:max-w-[82%] ${isUser ? 'rounded-br-md bg-blue-600 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'}`}>
                    {!isUser && (
                      <div className="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-indigo-700">
                        <JpcAideLogo size={14} />
                        <span>{JPC_AIDE_NAME}</span>
                        <span className="rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold normal-case text-indigo-600">AI-generated advisory</span>
                      </div>
                    )}
                    {isUser && <div className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-blue-100">Admin</div>}
                    <div className="whitespace-pre-wrap text-sm leading-6">{formatAssistantContent(message.content)}</div>
                    <div className={`mt-2 text-[11px] ${isUser ? 'text-blue-100' : 'text-slate-400'}`}>{formatDate(message.created_at)}</div>
                  </div>
                </div>
              )
            })}

            {sending && <TypingIndicator />}
            <div ref={messagesEndRef} />
          </div>

          {usage && usage.remaining <= 0 && (
            <div className="border-t border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800">
              You have reached your daily AI usage limit. Please try again tomorrow.
            </div>
          )}

          <div className="border-t border-slate-200 bg-white p-4 sm:p-5">
            <label htmlFor="admin-ai-message" className="mb-2 block text-sm font-medium text-slate-700">Message</label>
            <textarea
              id="admin-ai-message"
              rows={4}
              value={input}
              onChange={(e) => setInput(e.target.value.slice(0, 4000))}
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void handleSend() } }}
              placeholder={usage && usage.remaining <= 0 ? 'You have reached your daily AI usage limit. Please try again tomorrow.' : 'Ask a question about authorized employee reports, tasks, progress, or administrative records...'}
              className="w-full resize-y rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-50"
              disabled={sending || Boolean(usage && usage.remaining <= 0)}
            />
            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
              <span className="text-xs text-slate-400">{input.length}/4000 · Enter to send, Shift+Enter for a new line</span>
              <button type="button" onClick={() => void handleSend()} disabled={sending || !input.trim() || Boolean(usage && usage.remaining <= 0)} className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                {sending ? 'Sending...' : 'Send'}
              </button>
            </div>
          </div>
        </section>
      </div>

      {renameTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 className="text-lg font-semibold text-slate-900">Rename conversation</h3>
            <input value={renameValue} onChange={(e) => setRenameValue(e.target.value)} className="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" maxLength={120} />
            <div className="mt-5 flex justify-end gap-2">
              <button type="button" onClick={() => setRenameTarget(null)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700">Cancel</button>
              <button type="button" onClick={() => void handleRename()} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Save</button>
            </div>
          </div>
        </div>
      )}

      {deleteTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
            <h3 className="text-lg font-semibold text-slate-900">Delete conversation?</h3>
            <p className="mt-2 text-sm text-slate-600">“{deleteTarget.title || 'Untitled conversation'}” will be removed from your history.</p>
            <div className="mt-5 flex justify-end gap-2">
              <button type="button" onClick={() => setDeleteTarget(null)} className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-700">Cancel</button>
              <button type="button" onClick={() => void handleDeleteConversation()} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white">Delete</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
