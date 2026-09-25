import React from 'react'
import type { DataQualityFilterParams } from '../../types/dataQuality'

interface Props {
  filters: DataQualityFilterParams
  onChange: (filters: DataQualityFilterParams) => void
  onReset: () => void
}

export const DataQualityIssueFilters: React.FC<Props> = ({ filters, onChange, onReset }) => {
  const handleChange = (key: keyof DataQualityFilterParams, value: any) => {
    onChange({
      ...filters,
      [key]: value || undefined,
      page: 1,
    })
  }

  return (
    <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-wrap items-center gap-3">
      {/* Search text */}
      <div className="flex-1 min-w-[200px]">
        <input
          type="text"
          placeholder="Search issues..."
          value={filters.search || ''}
          onChange={(e) => handleChange('search', e.target.value)}
          className="w-full px-3 py-1.5 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      {/* Severity Filter */}
      <div>
        <select
          value={filters.severity || ''}
          onChange={(e) => handleChange('severity', e.target.value)}
          className="px-3 py-1.5 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Severities</option>
          <option value="CRITICAL">Critical</option>
          <option value="HIGH">High</option>
          <option value="MEDIUM">Medium</option>
          <option value="LOW">Low</option>
        </select>
      </div>

      {/* Status Filter */}
      <div>
        <select
          value={filters.status || ''}
          onChange={(e) => handleChange('status', e.target.value)}
          className="px-3 py-1.5 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Statuses</option>
          <option value="OPEN">Open</option>
          <option value="UNDER_REVIEW">Under Review</option>
          <option value="CONFIRMED">Confirmed</option>
          <option value="RESOLVED">Resolved</option>
          <option value="DISMISSED">Dismissed</option>
        </select>
      </div>

      {/* Entity Type Filter */}
      <div>
        <select
          value={filters.entity_type || ''}
          onChange={(e) => handleChange('entity_type', e.target.value)}
          className="px-3 py-1.5 text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option value="">All Entities</option>
          <option value="employee">Employee</option>
          <option value="task">Task</option>
          <option value="target">Target</option>
          <option value="report">Report</option>
          <option value="performance">Performance</option>
          <option value="achievement">Achievement</option>
        </select>
      </div>

      {/* Reset */}
      <button
        type="button"
        onClick={onReset}
        className="px-3 py-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
      >
        Reset
      </button>
    </div>
  )
}
