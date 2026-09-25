import React from 'react'
import type { DataQualityIssue } from '../../types/dataQuality'
import { DataQualitySeverityBadge } from './DataQualitySeverityBadge'
import { DataQualityStatusBadge } from './DataQualityStatusBadge'

interface Props {
  issues: DataQualityIssue[]
  loading?: boolean
  onSelect: (issue: DataQualityIssue) => void
}

export const DataQualityIssueTable: React.FC<Props> = ({ issues, loading = false, onSelect }) => {
  if (loading) {
    return (
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-8 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent" />
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-2">Loading data quality issues...</p>
      </div>
    )
  }

  if (issues.length === 0) {
    return (
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-8 text-center">
        <p className="text-sm font-semibold text-slate-700 dark:text-slate-300">
          No data quality issues match the selected filters.
        </p>
      </div>
    )
  }

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse text-sm">
          <thead>
            <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th className="py-3 px-4">Severity</th>
              <th className="py-3 px-4">Issue / Title</th>
              <th className="py-3 px-4">Entity</th>
              <th className="py-3 px-4">Department / Employee</th>
              <th className="py-3 px-4">Status</th>
              <th className="py-3 px-4">Detected</th>
              <th className="py-3 px-4 text-right">Action</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
            {issues.map((issue) => (
              <tr
                key={issue.id}
                onClick={() => onSelect(issue)}
                className="hover:bg-slate-50 dark:hover:bg-slate-700/40 cursor-pointer transition-colors"
              >
                <td className="py-3.5 px-4 whitespace-nowrap">
                  <DataQualitySeverityBadge severity={issue.severity} />
                </td>
                <td className="py-3.5 px-4">
                  <div className="font-semibold text-slate-900 dark:text-white line-clamp-1">
                    {issue.title}
                  </div>
                  <div className="text-xs text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5">
                    {issue.description}
                  </div>
                </td>
                <td className="py-3.5 px-4 whitespace-nowrap">
                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 uppercase">
                    {issue.entity_type} #{issue.entity_id}
                  </span>
                </td>
                <td className="py-3.5 px-4 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                  {issue.employee ? (
                    <div>{issue.employee.name}</div>
                  ) : issue.department ? (
                    <div>{issue.department.name}</div>
                  ) : (
                    <span className="text-slate-400">Institutional</span>
                  )}
                </td>
                <td className="py-3.5 px-4 whitespace-nowrap">
                  <DataQualityStatusBadge status={issue.status} />
                </td>
                <td className="py-3.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                  {issue.created_at ? new Date(issue.created_at).toLocaleDateString() : 'N/A'}
                </td>
                <td className="py-3.5 px-4 whitespace-nowrap text-right">
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation()
                      onSelect(issue)
                    }}
                    className="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:hover:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-xs font-semibold rounded-lg transition-colors"
                  >
                    Investigate
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
