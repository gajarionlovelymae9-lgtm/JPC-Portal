import { useState } from 'react'
import type { AiSummaryData } from '../../services/aiReportService'

interface Props {
  summary: AiSummaryData | null
  loading?: boolean
  onReanalyze?: () => void
}

export function AiSummaryCard({ summary, loading, onReanalyze }: Props) {
  const [level, setLevel] = useState<'short' | 'medium' | 'detailed'>('medium')

  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/4"></div>
        <div className="h-20 bg-gray-100 rounded-2xl"></div>
        <div className="h-32 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  if (!summary) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm text-center space-y-3">
        <span className="text-4xl">🤖</span>
        <h3 className="font-extrabold text-gray-900 text-base">No AI Summary Available</h3>
        <p className="text-xs text-gray-500 max-w-md mx-auto">
          AI summarization has not been run for this report version yet.
        </p>
        {onReanalyze && (
          <button
            onClick={onReanalyze}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition-colors shadow-xs"
          >
            Generate AI Summary
          </button>
        )}
      </div>
    )
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header & Level Selector */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
            🤖
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900 flex items-center gap-2">
              {summary.advisory_label}
            </h3>
            <span className="text-xs text-gray-400 font-medium">NLP Grounded Summarization</span>
          </div>
        </div>

        <div className="flex items-center gap-1 bg-gray-50 p-1 rounded-xl border border-gray-100">
          {(['short', 'medium', 'detailed'] as const).map((l) => (
            <button
              key={l}
              onClick={() => setLevel(l)}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold capitalize transition-colors ${
                level === l ? 'bg-white text-blue-600 shadow-xs' : 'text-gray-500 hover:text-gray-900'
              }`}
            >
              {l}
            </button>
          ))}
        </div>
      </div>

      {/* Mandatory Advisory Disclaimer */}
      <div className="p-3.5 rounded-2xl bg-blue-50/70 border border-blue-100/80 text-blue-900 text-xs flex items-start gap-2.5">
        <span className="text-base flex-shrink-0 mt-0.5">ℹ️</span>
        <p className="leading-relaxed font-medium">
          <strong>Advisory Notice:</strong> {summary.disclaimer}
        </p>
      </div>

      {/* Executive Overview */}
      <div className="space-y-2">
        <h4 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">
          Executive Overview
        </h4>
        <p className="text-sm text-gray-800 leading-relaxed whitespace-pre-line bg-gray-50/50 p-4 rounded-2xl border border-gray-100">
          {summary.overview}
        </p>
      </div>

      {/* Accomplishments & Activities */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {summary.key_accomplishments && summary.key_accomplishments.length > 0 && (
          <div className="p-5 rounded-2xl bg-emerald-50/40 border border-emerald-100/60 space-y-2">
            <h4 className="text-xs font-extrabold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
              <span>🏆</span> Key Accomplishments
            </h4>
            <ul className="space-y-1.5 text-xs text-emerald-950">
              {summary.key_accomplishments.map((item, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-emerald-500 font-bold">•</span>
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {summary.key_activities && summary.key_activities.length > 0 && (
          <div className="p-5 rounded-2xl bg-blue-50/40 border border-blue-100/60 space-y-2">
            <h4 className="text-xs font-extrabold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
              <span>📋</span> Activities Completed
            </h4>
            <ul className="space-y-1.5 text-xs text-blue-950">
              {summary.key_activities.map((item, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-blue-500 font-bold">•</span>
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {/* Issues & Recommendations */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {summary.issues && summary.issues.length > 0 && (
          <div className="p-5 rounded-2xl bg-amber-50/40 border border-amber-100/60 space-y-2">
            <h4 className="text-xs font-extrabold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
              <span>⚠️</span> Challenges / Issues
            </h4>
            <ul className="space-y-1.5 text-xs text-amber-950">
              {summary.issues.map((item, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-amber-500 font-bold">•</span>
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {summary.recommendations && summary.recommendations.length > 0 && (
          <div className="p-5 rounded-2xl bg-indigo-50/40 border border-indigo-100/60 space-y-2">
            <h4 className="text-xs font-extrabold uppercase tracking-wider text-indigo-800 flex items-center gap-1.5">
              <span>💡</span> AI Recommendations
            </h4>
            <ul className="space-y-1.5 text-xs text-indigo-950">
              {summary.recommendations.map((item, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-indigo-500 font-bold">•</span>
                  <span>{item}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  )
}

export default AiSummaryCard
