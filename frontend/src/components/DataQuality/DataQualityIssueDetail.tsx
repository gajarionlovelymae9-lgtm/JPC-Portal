import React from 'react'
import type { DataQualityIssue } from '../../types/dataQuality'
import { DataQualitySeverityBadge } from './DataQualitySeverityBadge'
import { DataQualityStatusBadge } from './DataQualityStatusBadge'
import { formatJsonValue } from '../../utils/dataQuality/formatDataQualityIssue'

interface Props {
  issue: DataQualityIssue
  onReview: () => void
  onConfirm: () => void
  onDismiss: () => void
  onResolve: () => void
}

export const DataQualityIssueDetail: React.FC<Props> = ({
  issue,
  onReview,
  onConfirm,
  onDismiss,
  onResolve,
}) => {
  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-700">
        <div>
          <div className="flex items-center gap-2 mb-2">
            <DataQualitySeverityBadge severity={issue.severity} />
            <DataQualityStatusBadge status={issue.status} />
            <span className="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
              {issue.category} Issue
            </span>
          </div>
          <h2 className="text-xl font-bold text-slate-900 dark:text-white">{issue.title}</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Detected on {new Date(issue.created_at).toLocaleString()} via {issue.detected_by}
          </p>
        </div>

        {/* Action Buttons */}
        <div className="flex flex-wrap items-center gap-2">
          {issue.status === 'OPEN' && (
            <button
              onClick={onReview}
              className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg transition-colors"
            >
              Start Review
            </button>
          )}

          {issue.status !== 'CONFIRMED' && issue.status !== 'RESOLVED' && issue.status !== 'DISMISSED' && (
            <button
              onClick={onConfirm}
              className="px-3 py-1.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-lg transition-colors"
            >
              Confirm Defect
            </button>
          )}

          {issue.status !== 'RESOLVED' && issue.status !== 'DISMISSED' && (
            <button
              onClick={onDismiss}
              className="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-lg transition-colors"
            >
              Dismiss False Positive
            </button>
          )}

          {issue.status !== 'RESOLVED' && (
            <button
              onClick={onResolve}
              className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors"
            >
              Resolve Issue
            </button>
          )}
        </div>
      </div>

      {/* Description */}
      <div>
        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
          Detailed Finding
        </h4>
        <p className="text-sm text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/50 p-3 rounded-lg border border-slate-200 dark:border-slate-800">
          {issue.description}
        </p>
      </div>

      {/* Values comparison */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="bg-red-50/50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/40 rounded-lg p-4">
          <span className="text-xs font-bold text-red-700 dark:text-red-400 uppercase tracking-wider">
            Detected / Actual Value
          </span>
          <pre className="mt-2 text-xs text-red-900 dark:text-red-200 font-mono whitespace-pre-wrap overflow-x-auto">
            {formatJsonValue(issue.detected_value)}
          </pre>
        </div>

        <div className="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 rounded-lg p-4">
          <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">
            Expected Constraint / Baseline
          </span>
          <pre className="mt-2 text-xs text-emerald-900 dark:text-emerald-200 font-mono whitespace-pre-wrap overflow-x-auto">
            {formatJsonValue(issue.expected_value)}
          </pre>
        </div>
      </div>

      {/* Audit History Timeline */}
      {issue.histories && issue.histories.length > 0 && (
        <div className="pt-4 border-t border-slate-200 dark:border-slate-700">
          <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">
            Resolution & Review Audit History
          </h4>
          <div className="space-y-3">
            {issue.histories.map((h) => (
              <div
                key={h.id}
                className="flex items-start gap-3 text-xs bg-slate-50 dark:bg-slate-900/40 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800"
              >
                <div className="w-2 h-2 rounded-full bg-indigo-500 mt-1.5" />
                <div className="flex-1">
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-slate-900 dark:text-white capitalize">
                      Action: {h.action} → {h.new_status}
                    </span>
                    <span className="text-slate-400">
                      {new Date(h.created_at).toLocaleString()}
                    </span>
                  </div>
                  {h.performed_by && (
                    <div className="text-slate-500 dark:text-slate-400 mt-0.5">
                      By: {h.performed_by.name}
                    </div>
                  )}
                  {h.notes && (
                    <div className="text-slate-700 dark:text-slate-300 mt-1 italic">
                      "{h.notes}"
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
