import React from 'react'
import type { PerformanceScoreBreakdownItem } from '../../types/performance'

interface ScoreBreakdownViewProps {
  breakdowns: PerformanceScoreBreakdownItem[]
  finalScore: number
}

export const ScoreBreakdownView: React.FC<ScoreBreakdownViewProps> = ({ breakdowns, finalScore }) => {
  return (
    <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
        <div>
          <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
            <span>📊</span>
            <span>Transparent Calculation Breakdown</span>
          </h3>
          <p className="text-xs text-gray-500 mt-0.5">
            Institutional weighted formula applied to authoritative database metrics
          </p>
        </div>
        <div className="text-right">
          <span className="text-xs text-gray-400 font-medium">Weighted Total</span>
          <p className="text-base font-extrabold text-indigo-600 font-mono">{finalScore.toFixed(1)}%</p>
        </div>
      </div>

      <div className="space-y-3">
        {breakdowns.map((item) => (
          <div
            key={item.metric_key}
            className="p-3.5 bg-gray-50 hover:bg-indigo-50/40 rounded-xl border border-gray-200/80 transition-colors"
          >
            <div className="flex items-center justify-between text-xs mb-1.5">
              <span className="font-semibold text-gray-800">{item.metric_label}</span>
              <div className="flex items-center gap-3 font-mono">
                <span className="text-gray-500 font-medium">
                  Raw: <strong className="text-gray-900">{Number(item.raw_value).toFixed(1)}%</strong>
                </span>
                <span className="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-bold">
                  Weight: {Number(item.weight).toFixed(0)}%
                </span>
                <span className="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded">
                  + {Number(item.weighted_value).toFixed(1)}%
                </span>
              </div>
            </div>

            {/* Sub-progress bar */}
            <div className="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
              <div
                className="bg-indigo-600 h-full rounded-full"
                style={{ width: `${Math.min(100, Math.max(0, Number(item.raw_value)))}%` }}
              />
            </div>
          </div>
        ))}
      </div>

      <div className="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-xl text-[11px] text-slate-600 flex items-start gap-2">
        <span className="mt-0.5">ℹ️</span>
        <p>
          Overall Score is computed via: <code className="bg-slate-200 px-1 py-0.5 rounded text-slate-800 font-mono text-[10px]">Σ (Raw KPI × Weight)</code>. No hidden AI adjustments are applied to official performance calculations.
        </p>
      </div>
    </div>
  )
}
