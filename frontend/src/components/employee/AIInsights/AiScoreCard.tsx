import React from 'react'
import type { EmployeeAiScore } from '../../../services/employeePortalService'

interface AiScoreCardProps {
  score: EmployeeAiScore
  periodLabel: string
}

const TONE: Record<string, { ring: string; text: string; badge: string }> = {
  emerald: { ring: 'border-emerald-200 bg-emerald-50/60', text: 'text-emerald-700', badge: 'bg-emerald-100 text-emerald-800' },
  blue: { ring: 'border-blue-200 bg-blue-50/60', text: 'text-blue-700', badge: 'bg-blue-100 text-blue-800' },
  amber: { ring: 'border-amber-200 bg-amber-50/60', text: 'text-amber-700', badge: 'bg-amber-100 text-amber-800' },
  orange: { ring: 'border-orange-200 bg-orange-50/60', text: 'text-orange-700', badge: 'bg-orange-100 text-orange-800' },
  rose: { ring: 'border-rose-200 bg-rose-50/60', text: 'text-rose-700', badge: 'bg-rose-100 text-rose-800' },
}

/**
 * The AI Performance Score.
 *
 * When no metric is measurable the score is null, and this renders an explicit
 * "not enough data" panel rather than a zero - a 0 would read as a measured
 * result and would be dishonest.
 */
export const AiScoreCard: React.FC<AiScoreCardProps> = ({ score, periodLabel }) => {
  if (score.value === null) {
    return (
      <section
        aria-label="AI Performance Score"
        className="bg-white rounded-2xl border border-dashed border-gray-300 p-6 shadow-sm h-full"
      >
        <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">AI Performance Score</h2>
        <p className="mt-4 text-3xl font-extrabold text-gray-400">—</p>
        <p className="mt-2 text-xs font-semibold text-gray-600">Not enough data to score</p>
        <p className="mt-2 text-xs text-gray-500">
          No measurable task, report, or target records exist for {periodLabel}, so no score has been
          calculated. Nothing is shown here rather than a guessed number.
        </p>
      </section>
    )
  }

  const tone = TONE[score.color ?? 'blue'] ?? TONE.blue

  return (
    <section aria-label="AI Performance Score" className={`rounded-2xl border p-6 shadow-sm h-full ${tone.ring}`}>
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">AI Performance Score</h2>
        <span className={`px-2 py-1 rounded-full text-[10px] font-extrabold uppercase ${tone.badge}`}>
          {score.label}
        </span>
      </div>

      <p className={`mt-4 text-5xl font-extrabold tabular-nums ${tone.text}`}>{score.value.toFixed(2)}</p>
      <p className="mt-1 text-xs font-semibold text-gray-600">out of 100 &middot; {periodLabel}</p>

      <dl className="mt-4 space-y-1 text-xs text-gray-600">
        <div className="flex justify-between">
          <dt>Metrics included</dt>
          <dd className="font-bold">{score.metrics_used}</dd>
        </div>
        <div className="flex justify-between">
          <dt>Metrics excluded (no data)</dt>
          <dd className="font-bold">{score.metrics_excluded}</dd>
        </div>
        <div className="flex justify-between">
          <dt>Calculation version</dt>
          <dd className="font-mono">{score.basis ?? '—'}</dd>
        </div>
      </dl>
    </section>
  )
}
