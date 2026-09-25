import React from 'react'

interface Props {
  scanning: boolean
}

export const DataQualityScanProgress: React.FC<Props> = ({ scanning }) => {
  if (!scanning) return null

  return (
    <div className="bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4 flex items-center gap-3">
      <div className="animate-spin rounded-full h-5 w-5 border-2 border-indigo-600 border-t-transparent" />
      <div>
        <p className="text-sm font-semibold text-indigo-900 dark:text-indigo-200">
          Automated Data Quality Scan in Progress...
        </p>
        <p className="text-xs text-indigo-700 dark:text-indigo-400 mt-0.5">
          Evaluating employee records, task dates, report relationships, and KPI mathematical bounds.
        </p>
      </div>
    </div>
  )
}
