import React from 'react'
import type { PerformanceKpis } from '../../types/performance'

interface KpiGridProps {
  kpis: PerformanceKpis
  metadata?: {
    total_tasks?: number
    completed_tasks?: number
    expected_reports?: number
    submitted_reports?: number
    on_time_reports?: number
    late_reports?: number
    reviewed_reports?: number
    approved_reports?: number
    rejected_reports?: number
    achieved_target_val?: number
    total_target_val?: number
  }
}

export const KpiGrid: React.FC<KpiGridProps> = ({ kpis, metadata = {} }) => {
  const cards = [
    {
      title: 'Task Completion',
      rate: kpis.task_completion_rate,
      icon: '📋',
      detail: `${metadata.completed_tasks ?? 0} of ${metadata.total_tasks ?? 0} tasks completed`,
      color: 'indigo',
    },
    {
      title: 'Report Completion',
      rate: kpis.report_completion_rate,
      icon: '📄',
      detail: `${metadata.submitted_reports ?? 0} of ${metadata.expected_reports ?? 0} reports submitted`,
      color: 'blue',
    },
    {
      title: 'On-Time Submission',
      rate: kpis.on_time_submission_rate,
      icon: '⏱️',
      detail: `${metadata.on_time_reports ?? 0} on-time (${metadata.late_reports ?? 0} late)`,
      color: 'emerald',
    },
    {
      title: 'Approval Rate',
      rate: kpis.approval_rate,
      icon: '✅',
      detail: `${metadata.approved_reports ?? 0} approved of ${metadata.reviewed_reports ?? 0} reviewed`,
      color: 'teal',
    },
    {
      title: 'Target Achievement',
      rate: kpis.target_achievement_rate,
      icon: '🎯',
      detail: metadata.total_target_val
        ? `${Number(metadata.achieved_target_val ?? 0).toFixed(0)} / ${Number(metadata.total_target_val).toFixed(0)} units`
        : 'Aligned with department goals',
      color: 'purple',
    },
  ]

  const getStatusBadge = (rate: number) => {
    if (rate >= 90) return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">High</span>
    if (rate >= 70) return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800">Good</span>
    if (rate >= 60) return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">Fair</span>
    return <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-800">Low</span>
  }

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
      {cards.map((card) => (
        <div
          key={card.title}
          className="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow transition-shadow flex flex-col justify-between"
        >
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xl p-1.5 rounded-xl bg-gray-50 border border-gray-100">{card.icon}</span>
              {getStatusBadge(card.rate)}
            </div>
            <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{card.title}</h4>
            <p className="text-2xl font-extrabold text-gray-900 mt-1 font-mono">{card.rate.toFixed(1)}%</p>
          </div>

          <div className="mt-3 pt-2 border-t border-gray-100">
            {/* Progress bar */}
            <div className="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1.5">
              <div
                className="bg-indigo-600 h-full rounded-full"
                style={{ width: `${Math.min(100, Math.max(0, card.rate))}%` }}
              />
            </div>
            <p className="text-[11px] text-gray-500 truncate">{card.detail}</p>
          </div>
        </div>
      ))}
    </div>
  )
}
