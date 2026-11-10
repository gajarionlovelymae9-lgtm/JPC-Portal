import { useCallback, useEffect, useRef, useState } from 'react'
import { useAiChat } from '../../hooks/ai/useAiChat'
import { useAuth } from '../../auth/useAuth'
import { AiChatInput } from './AiChatInput'
import { AiTypingIndicator } from './AiTypingIndicator'
import { sanitize } from '../../utils/ai/sanitizeAiContent'
import { JpcAideLogo, JPC_AIDE_NAME, JPC_AIDE_TAGLINE } from './JpcAideLogo'
import { getAiLanguagePreference, type AiLanguagePreference } from '../../api/ai/aiLanguagePreferenceApi'
import { getAiUsage, type AiUsageStatus } from '../../api/ai/aiChatApi'

export type AssistantScope = 'admin' | 'employee'

interface DashboardAiAssistantProps {
  /** Chooses the greeting shown to the caller. */
  scope: AssistantScope
  /** Optional extra classes for placement. */
  className?: string
}

function buildGreeting(scope: AssistantScope, name: string): string {
  if (scope === 'admin') {
    return `Hello, ${name}! I'm ${JPC_AIDE_NAME}, your intelligent system assistant.\n\nI can help analyze authorized performance data, summarize reports, identify trends, review recorded task and report information, and provide data-based recommendations.\n\nI only use authorized system data and respect existing role permissions. You can message me in English, Filipino/Tagalog, or Taglish.`
  }

  return `Hello, ${name}! I'm ${JPC_AIDE_NAME}, your intelligent productivity assistant.\n\nI can help with task progress, deadlines, accomplishment summaries, and report preparation using the information available to you. You can message me in English, Filipino/Tagalog, or Taglish.`
}

/**
 * Floating, role-aware JPC AIDE assistant for dashboards.
 *
 * It reuses the existing /api/v1/ai/chat pipeline and chat components. Every
 * answer is produced by the backend from real records; this component only
 * renders the grounded text. The backend independently enforces the caller's
 * role scope, so nothing here is trusted for authorisation.
 */
export function DashboardAiAssistant({ scope, className = '' }: DashboardAiAssistantProps) {
  const { user } = useAuth()
  const displayName = user?.name?.trim() || (scope === 'admin' ? 'Admin' : 'Employee')
  const [open, setOpen] = useState(false)
  const [languagePreference, setLanguagePreference] = useState<AiLanguagePreference>('auto')
  const [usage, setUsage] = useState<AiUsageStatus | null>(null)
  const { messages, loading, error, sendMessage, clearConversation } = useAiChat(null, languagePreference)
  const bottomRef = useRef<HTMLDivElement>(null)

  const refreshUsage = useCallback(async () => {
    try {
      const data = await getAiUsage()
      if (data) setUsage(data)
    } catch {
      // Auto-detect or fail silently
    }
  }, [])

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

  useEffect(() => {
    if (open) {
      void refreshUsage()
    }
  }, [open, refreshUsage])

  useEffect(() => {
    if (!open) return
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, loading, open])

  const greeting = buildGreeting(scope, displayName)

  const handleSend = async (text: string) => {
    if (loading) return
    if (usage && usage.remaining <= 0) return
    await sendMessage(text)
    void refreshUsage()
  }

  const isLimitReached = Boolean(usage && usage.remaining <= 0)

  return (
    <div className={`fixed bottom-5 right-5 z-40 print:hidden ${className}`}>
      {open && (
        <section
          role="dialog"
          aria-label={JPC_AIDE_NAME}
          className="mb-3 flex h-[70vh] max-h-[36rem] w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:h-[34rem]"
        >
          <header className="jpc-gradient flex items-start justify-between gap-3 px-4 py-3 text-white">
            <div className="flex min-w-0 items-start gap-2.5">
              <div className="mt-0.5 rounded-xl bg-white/15 p-1">
                <JpcAideLogo size={22} title={JPC_AIDE_NAME} />
              </div>
              <div className="min-w-0">
                <h2 className="truncate text-sm font-bold">{JPC_AIDE_NAME}</h2>
                <p className="mt-0.5 text-[11px] text-blue-100">{JPC_AIDE_TAGLINE}</p>
                {usage && (
                  <p className="mt-0.5 text-[10px] text-blue-200 font-medium">
                    Requests left today: {usage.remaining} / {usage.limit}
                  </p>
                )}
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
              <button
                type="button"
                onClick={() => void clearConversation()}
                disabled={loading || messages.length === 0}
                className="rounded-md px-2 py-1 text-xs font-semibold text-blue-50 hover:bg-white/15 disabled:opacity-40"
              >
                Clear
              </button>
              <button
                type="button"
                onClick={() => setOpen(false)}
                aria-label={`Close ${JPC_AIDE_NAME}`}
                className="rounded-md px-2 py-1 text-sm leading-none text-blue-50 hover:bg-white/15"
              >
                ✕
              </button>
            </div>
          </header>

          {isLimitReached && (
            <div role="alert" className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800">
              You have reached your daily AI usage limit. Please try again tomorrow.
            </div>
          )}

          {error && (
            <div role="alert" className="border-b border-red-200 bg-red-50 px-4 py-2 text-xs text-red-700">
              {error}
            </div>
          )}

          <div className="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-4 py-4">
            {messages.length === 0 && !loading ? (
              <div>
                <div className="rounded-xl border border-indigo-100 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                  <div className="mb-2 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-indigo-700">
                    <JpcAideLogo size={14} />
                    <span>{JPC_AIDE_NAME}</span>
                  </div>
                  <div className="whitespace-pre-wrap leading-6">{greeting}</div>
                  <p className="mt-3 text-sm font-medium text-slate-900">Type your message below to get started.</p>
                </div>
              </div>
            ) : (
              messages
                .filter((message) => message.role === 'user' || message.role === 'assistant')
                .map((message) => {
                  const isUser = message.role === 'user'
                  return (
                    <div
                      key={String(message.id)}
                      className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}
                    >
                      <div
                        className={`max-w-[85%] rounded-xl px-3 py-2 text-sm shadow-sm ${
                          isUser
                            ? 'rounded-br-none bg-blue-600 text-white'
                            : 'rounded-bl-none border border-slate-200 bg-white text-slate-800'
                        }`}
                      >
                        {!isUser && (
                          <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700">
                            <JpcAideLogo size={12} />
                            <span>{JPC_AIDE_NAME}</span>
                          </div>
                        )}
                        <div className="whitespace-pre-wrap leading-6">{sanitize(message.content)}</div>
                      </div>
                    </div>
                  )
                })
            )}

            {loading && <AiTypingIndicator />}
            <div ref={bottomRef} />
          </div>

          <AiChatInput onSend={handleSend} disabled={loading || isLimitReached} />
        </section>
      )}

      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-label={open ? `Close ${JPC_AIDE_NAME}` : `Open ${JPC_AIDE_NAME}`}
        className="ml-auto flex items-center gap-2 rounded-full bg-gradient-to-r from-blue-700 to-purple-700 px-4 py-3 text-sm font-bold text-white shadow-lg transition-colors hover:from-blue-800 hover:to-purple-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2"
      >
        <JpcAideLogo size={20} className="shrink-0" />
        <span className="hidden sm:inline">{JPC_AIDE_NAME}</span>
      </button>
    </div>
  )
}
