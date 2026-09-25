import React from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useDataQualityIssues } from '../../hooks/dataQuality/useDataQualityIssues'
import type { DataQualityIssue } from '../../types/dataQuality'
import { DataQualityIssueTable } from '../../components/DataQuality/DataQualityIssueTable'
import { DataQualityIssueFilters } from '../../components/DataQuality/DataQualityIssueFilters'
import { dataQualityService } from '../../services/dataQuality/dataQualityService'

export const DataQualityIssuesPage: React.FC = () => {
  const {
    issues,
    filters,
    setFilters,
    loading,
    error,
  } = useDataQualityIssues()
  const navigate = useNavigate()

  const handleSelect = (issue: DataQualityIssue) => {
    navigate(`/data-quality/issues/${issue.id}`)
  }

  const handleExport = () => {
    window.location.href = dataQualityService.getExportUrl(filters)
  }

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Link to="/data-quality" className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
              ← Dashboard
            </Link>
          </div>
          <h1 className="text-2xl font-black text-slate-900 dark:text-white mt-1">
            Data Quality Issue Management
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Investigate, review, confirm, dismiss, and resolve potential defects.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={handleExport}
            className="px-4 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm transition-colors"
          >
            Export CSV
          </button>
        </div>
      </div>

      {/* Filters */}
      <DataQualityIssueFilters
        filters={filters}
        onChange={setFilters}
        onReset={() => setFilters({})}
      />

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
          {error}
        </div>
      )}

      {/* Issues Table */}
      <DataQualityIssueTable
        issues={issues}
        loading={loading}
        onSelect={handleSelect}
      />
    </div>
  )
}
