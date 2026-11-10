import { Fragment, useEffect, useRef } from 'react'
import { AiChatMessage } from './AiChatMessage'
import { AiTypingIndicator } from './AiTypingIndicator'
import { AiToolResultCard } from './AiToolResultCard'
import { AiPerformanceResultCard } from './AiPerformanceResultCard'
import { AiReportResultCard } from './AiReportResultCard'
import { AiAchievementResultCard } from './AiAchievementResultCard'
import { JpcAideLogo, JPC_AIDE_NAME, JPC_AIDE_TAGLINE } from './JpcAideLogo'

interface AiChatWindowProps {
  messages: any[]
  loading: boolean
}

export function AiChatWindow({ messages, loading }: AiChatWindowProps) {
  const bottomRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, loading])

  const renderToolResult = (result: any, idx: number) => {
    const intent = result.intent || 'tool'
    const data = result.data

    if (!data) return null

    switch (intent) {
      case 'performance_result':
        return <AiPerformanceResultCard key={`tool-${idx}`} data={data} />
      case 'report_result':
        return <AiReportResultCard key={`tool-${idx}`} data={data} />
      case 'achievement_result':
        return <AiAchievementResultCard key={`tool-${idx}`} data={data} />
      default:
        // Generic tool result (like list of late reports)
        return (
          <AiToolResultCard 
            key={`tool-${idx}`}
            title={result.title || 'Data Result'}
            count={result.count}
            summary={result.summary}
            data={Array.isArray(data) ? data : [data]}
          />
        )
    }
  }

  if (messages.length === 0) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center p-8 bg-gray-50">
        <div className="mb-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-indigo-100">
          <JpcAideLogo size={44} />
        </div>
        <h2 className="text-2xl font-bold text-gray-800">{JPC_AIDE_NAME}</h2>
        <p className="mt-1 text-sm font-medium text-indigo-700">{JPC_AIDE_TAGLINE}</p>
        <div className="mt-6 bg-white p-6 rounded-lg shadow-sm border max-w-md w-full text-left">
          <p className="text-sm text-gray-700 leading-6">
            Start a conversation by typing your own message below. {JPC_AIDE_NAME} can assist using authorized system data and can respond in English, Filipino/Tagalog, or Taglish based on your message or language preference.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="flex-1 overflow-y-auto p-4 bg-gray-50 flex flex-col">
      {messages.map((msg, idx) => (
        <Fragment key={idx}>
          <AiChatMessage message={msg} />
          {msg.tool_results && msg.tool_results.length > 0 && (
            <div className="my-2 ml-4">
              {msg.tool_results.map((tr: any, trIdx: number) => renderToolResult(tr, trIdx))}
            </div>
          )}
        </Fragment>
      ))}
      {loading && <AiTypingIndicator />}
      <div ref={bottomRef} />
    </div>
  )
}
