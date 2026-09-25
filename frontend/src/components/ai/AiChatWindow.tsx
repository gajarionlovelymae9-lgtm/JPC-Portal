import { Fragment, useEffect, useRef } from 'react'
import { AiChatMessage } from './AiChatMessage'
import { AiTypingIndicator } from './AiTypingIndicator'
import { AiToolResultCard } from './AiToolResultCard'
import { AiPerformanceResultCard } from './AiPerformanceResultCard'
import { AiReportResultCard } from './AiReportResultCard'
import { AiAchievementResultCard } from './AiAchievementResultCard'

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
        <h2 className="text-2xl font-bold text-gray-700 mb-6">JPC AI Assistant</h2>
        <div className="bg-white p-6 rounded-lg shadow-sm border max-w-md w-full">
          <p className="text-sm text-gray-600 mb-4 text-center">I can help you with:</p>
          <ul className="space-y-3 text-sm text-gray-700">
            <li className="flex items-center">
              <span className="mr-2">📊</span> Generating performance reports
            </li>
            <li className="flex items-center">
              <span className="mr-2">🔍</span> Finding pending or late tasks
            </li>
            <li className="flex items-center">
              <span className="mr-2">📈</span> Analyzing department metrics
            </li>
            <li className="flex items-center">
              <span className="mr-2">🏆</span> Tracking achievements
            </li>
          </ul>
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
