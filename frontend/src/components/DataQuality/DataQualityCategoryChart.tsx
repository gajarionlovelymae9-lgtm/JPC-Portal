import React from 'react'

interface Props {
  byCategory: Record<string, number>
}

export const DataQualityCategoryChart: React.FC<Props> = ({ byCategory }) => {
  const entries = Object.entries(byCategory)
  const total = entries.reduce((acc, [, val]) => acc + val, 0)

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
      <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4">
        Active Issues by Entity Category
      </h3>

      {entries.length === 0 ? (
        <p className="text-sm text-slate-500 dark:text-slate-400 py-6 text-center">
          No open issues detected across entity modules.
        </p>
      ) : (
        <div className="space-y-3">
          {entries.map(([category, count]) => {
            const pct = total > 0 ? Math.round((count / total) * 100) : 0

            return (
              <div key={category}>
                <div className="flex justify-between text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                  <span className="capitalize">{category}</span>
                  <span>{count} ({pct}%)</span>
                </div>
                <div className="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                  <div
                    className="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                    style={{ width: `${Math.max(4, pct)}%` }}
                  />
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
