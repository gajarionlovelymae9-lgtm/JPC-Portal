import React from 'react'
import type { DataQualityIssue } from '../../types/dataQuality'
import { DataQualitySeverityBadge } from './DataQualitySeverityBadge'
import { DataQualityStatusBadge } from './DataQualityStatusBadge'

interface Props {
  issue: DataQualityIssue
  onClick?: () => void
}

export const DataQualityIssueCard: React.FC<Props> = ({ issue, onClick }) => {
  return (
    <div
      onClick={onClick}
      className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm hover:shadow-md transition-shadow cursor-pointer flex flex-col justify-between gap-3"
    >
      <div>
        <div className="flex items-center justify-between gap-2 mb-2">
          <DataQualitySeverityBadge severity={issue.severity} />
          <DataQualityStatusBadge status={issue.status} />
        </div>
        <h4 className="text-sm font-bold text-slate-900 dark:text-white line-clamp-2">
          {issue.title}
        </h4>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
          {issue.description}
        </p>
      </div>

      <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-700">
        <span className="font-medium text-slate-700 dark:text-slate-300 capitalize">
          {issue.entity_type} #{issue.entity_id}
        </span>
        <span>{new Date(issue.created_at).toLocaleDateString()}</span>
      </div>
    </div>
  )
}
