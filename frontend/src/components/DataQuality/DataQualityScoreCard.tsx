import React from 'react'

interface Props {
  score: number
  totalIssues: number
  openIssues: number
  critical: number
  high: number
  medium: number
  low: number
  resolved: number
  dismissed: number
}

export const DataQualityScoreCard: React.FC<Props> = ({
  score,
  critical,
  high,
  medium,
  low,
}) => {
  const getScoreColor = (s: number) => {
    if (s >= 90) return 'text-emerald-600 dark:text-emerald-400'
    if (s >= 75) return 'text-blue-600 dark:text-blue-400'
    if (s >= 60) return 'text-amber-600 dark:text-amber-400'
    return 'text-red-600 dark:text-red-400'
  }

  const getScoreBg = (s: number) => {
    if (s >= 90) return 'bg-emerald-500'
    if (s >= 75) return 'bg-blue-500'
    if (s >= 60) return 'bg-amber-500'
    return 'bg-red-500'
  }

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
        {/* Overall Health Score Gauge */}
        <div className="flex items-center gap-5">
          <div className="relative flex items-center justify-center w-24 h-24 rounded-full bg-slate-50 dark:bg-slate-900/60 border-4 border-slate-100 dark:border-slate-800 shadow-inner">
            <div className={`text-3xl font-extrabold ${getScoreColor(score)}`}>
              {score}%
            </div>
          </div>
          <div>
            <h3 className="text-lg font-bold text-slate-900 dark:text-white">
              Data Quality Health Score
            </h3>
            <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Automated institutional integrity & accuracy index.
            </p>
            <div className="w-48 bg-slate-100 dark:bg-slate-700 rounded-full h-2 mt-3 overflow-hidden">
              <div
                className={`h-2 rounded-full transition-all duration-500 ${getScoreBg(score)}`}
                style={{ width: `${Math.max(5, Math.min(100, score))}%` }}
              />
            </div>
          </div>
        </div>

        {/* Severity counts summary */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="bg-red-50/70 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 rounded-lg p-3 text-center">
            <span className="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider">Critical</span>
            <div className="text-xl font-bold text-red-700 dark:text-red-300 mt-0.5">{critical}</div>
          </div>

          <div className="bg-orange-50/70 dark:bg-orange-950/30 border border-orange-200 dark:border-orange-900/50 rounded-lg p-3 text-center">
            <span className="text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider">High</span>
            <div className="text-xl font-bold text-orange-700 dark:text-orange-300 mt-0.5">{high}</div>
          </div>

          <div className="bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-lg p-3 text-center">
            <span className="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Medium</span>
            <div className="text-xl font-bold text-amber-700 dark:text-amber-300 mt-0.5">{medium}</div>
          </div>

          <div className="bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-900/50 rounded-lg p-3 text-center">
            <span className="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Low</span>
            <div className="text-xl font-bold text-blue-700 dark:text-blue-300 mt-0.5">{low}</div>
          </div>
        </div>
      </div>
    </div>
  )
}
