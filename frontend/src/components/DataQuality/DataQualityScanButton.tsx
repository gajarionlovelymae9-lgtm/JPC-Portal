import React, { useState } from 'react'

interface Props {
  onScan: (type: string) => Promise<any>
  loading?: boolean
}

export const DataQualityScanButton: React.FC<Props> = ({ onScan, loading = false }) => {
  const [open, setOpen] = useState(false)

  const handleSelect = async (type: string) => {
    setOpen(false)
    await onScan(type)
  }

  return (
    <div className="relative inline-block text-left">
      <div className="inline-flex rounded-lg shadow-sm">
        <button
          type="button"
          onClick={() => handleSelect('full')}
          disabled={loading}
          className="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-l-lg border-r border-indigo-500 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          {loading ? (
            <span className="flex items-center gap-2">
              <svg className="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
              </svg>
              Scanning...
            </span>
          ) : (
            'Run Full Scan'
          )}
        </button>
        <button
          type="button"
          onClick={() => setOpen(!open)}
          disabled={loading}
          className="inline-flex items-center px-2 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-r-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
      </div>

      {open && (
        <div className="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-slate-800 ring-1 ring-black ring-opacity-5 z-20 border border-slate-200 dark:border-slate-700">
          <div className="py-1">
            <button
              onClick={() => handleSelect('full')}
              className="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              Full Institutional Scan
            </button>
            <button
              onClick={() => handleSelect('incremental')}
              className="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              Incremental Scan
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
