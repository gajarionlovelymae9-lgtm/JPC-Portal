import React from 'react'
import type { DataQualityTrendItem } from '../../types/dataQuality'

interface Props {
  trends: DataQualityTrendItem[]
}

export const DataQualityTrendChart: React.FC<Props> = ({ trends }) => {
  const maxIssues = Math.max(1, ...trends.map((t) => Math.max(t.issues_detected, t.issues_resolved)))

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
      <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4">
        Monthly Integrity Trends (Last 6 Months)
      </h3>

      {trends.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400 py-6 text-center">
          No historical trend data available.
        </p>
      ) : (
        <div className="flex items-end justify-between gap-4 h-40 pt-4">
          {trends.map((item) => {
            const detectedHeight = Math.round((item.issues_detected / maxIssues) * 100)
            const resolvedHeight = Math.round((item.issues_resolved / maxIssues) * 100)

            return (
              <div key={item.period} className="flex-1 flex flex-col items-center gap-2 h-full justify-end">
                <div className="flex items-end gap-1.5 w-full justify-center h-28">
                  {/* Detected Bar */}
                  <div
                    title={`Detected: ${item.issues_detected}`}
                    className="w-3.5 bg-red-400 hover:bg-red-500 rounded-t transition-all"
                    style={{ height: `${Math.max(4, detectedHeight)}%` }}
                  />
                  {/* Resolved Bar */}
                  <div
                    title={`Resolved: ${item.issues_resolved}`}
                    className="w-3.5 bg-emerald-400 hover:bg-emerald-500 rounded-t transition-all"
                    style={{ height: `${Math.max(4, resolvedHeight)}%` }}
                  />
                </div>
                <span className="text-[10px] font-medium text-slate-500 dark:text-slate-400 text-center">
                  {item.period}
                </span>
              </div>
            )
          })}
        </div>
      )}

      <div className="flex items-center justify-center gap-6 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-400">
        <div className="flex items-center gap-1.5">
          <div className="w-2.5 h-2.5 bg-red-400 rounded-sm" />
          <span>Issues Detected</span>
        </div>
        <div className="flex items-center gap-1.5">
          <div className="w-2.5 h-2.5 bg-emerald-400 rounded-sm" />
          <span>Issues Resolved</span>
        </div>
      </div>
    </div>
  )
}
