import React from 'react'
import type { PerformanceHistoryPoint } from '../../types/performance'

interface PerformanceTrendChartProps {
  history: PerformanceHistoryPoint[]
}

export const PerformanceTrendChart: React.FC<PerformanceTrendChartProps> = ({ history }) => {
  if (!history || history.length === 0) {
    return (
      <div className="p-6 bg-white rounded-2xl border border-gray-200 text-center text-xs text-gray-500">
        No performance history recorded yet.
      </div>
    );
  }

  const maxScore = 100

  return (
    <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
        <div>
          <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
            <span>📈</span>
            <span>Monthly Performance Trend</span>
          </h3>
          <p className="text-xs text-gray-500 mt-0.5">Historical productivity score trajectory over evaluated months</p>
        </div>
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
        {history.map((point, index) => {
          const heightPercent = Math.max(8, (point.score / maxScore) * 100)
          const isLatest = index === history.length - 1

          return (
            <div
              key={`${point.year}-${point.month}`}
              className={`p-3 rounded-xl border flex flex-col items-center justify-between text-center transition-all ${
                isLatest ? 'bg-indigo-50/50 border-indigo-200 shadow-sm' : 'bg-gray-50 border-gray-100'
              }`}
            >
              <span className="text-[11px] font-bold text-gray-600 mb-2">{point.period_label}</span>

              {/* Vertical bar container */}
              <div className="w-8 h-24 bg-gray-200 rounded-full flex flex-col justify-end p-0.5 overflow-hidden my-1">
                <div
                  className={`w-full rounded-full transition-all duration-700 ${
                    point.score >= 90
                      ? 'bg-emerald-500'
                      : point.score >= 80
                      ? 'bg-blue-500'
                      : point.score >= 70
                      ? 'bg-amber-500'
                      : 'bg-rose-500'
                  }`}
                  style={{ height: `${heightPercent}%` }}
                />
              </div>

              <div className="mt-2">
                <p className="text-xs font-mono font-extrabold text-gray-900">{point.score.toFixed(1)}%</p>
                <span className="text-[10px] text-gray-400 font-medium block truncate max-w-[80px]">
                  {point.rating}
                </span>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
