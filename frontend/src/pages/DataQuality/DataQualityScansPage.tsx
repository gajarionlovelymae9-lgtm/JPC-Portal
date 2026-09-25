import React from 'react'
import { Link } from 'react-router-dom'
import { useDataQualityScans } from '../../hooks/dataQuality/useDataQualityScans'
import { DataQualityScanButton } from '../../components/DataQuality/DataQualityScanButton'
import { DataQualityScanProgress } from '../../components/DataQuality/DataQualityScanProgress'
import { SCAN_STATUS_COLORS } from '../../utils/dataQuality/dataQualityConstants'

export const DataQualityScansPage: React.FC = () => {
  const { scans, loading, scanning, error, triggerScan, refresh } = useDataQualityScans()

  const handleScan = async (type: string) => {
    await triggerScan(type)
    await refresh()
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
            Data Quality Scan History
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Audit logs of all full and incremental validation scans executed across the institution.
          </p>
        </div>

        <DataQualityScanButton onScan={handleScan} loading={scanning} />
      </div>

      <DataQualityScanProgress scanning={scanning} />

      {error && (
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
          {error}
        </div>
      )}

      {/* Scans Table */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        {loading && scans.length === 0 ? (
          <div className="p-8 text-center">
            <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent" />
            <p className="text-sm text-slate-500 mt-2">Loading scan history...</p>
          </div>
        ) : scans.length === 0 ? (
          <div className="p-8 text-center text-sm font-semibold text-slate-600">
            No scans recorded yet.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  <th className="py-3 px-4">Scan ID</th>
                  <th className="py-3 px-4">Type</th>
                  <th className="py-3 px-4">Status</th>
                  <th className="py-3 px-4">Records Evaluated</th>
                  <th className="py-3 px-4">Issues Detected</th>
                  <th className="py-3 px-4">Critical / High</th>
                  <th className="py-3 px-4">Initiated By</th>
                  <th className="py-3 px-4">Timestamp</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {scans.map((scan) => (
                  <tr key={scan.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                    <td className="py-3.5 px-4 font-mono font-bold text-slate-900 dark:text-white">
                      #{scan.id}
                    </td>
                    <td className="py-3.5 px-4 uppercase text-xs font-semibold text-slate-700 dark:text-slate-300">
                      {scan.scan_type}
                    </td>
                    <td className="py-3.5 px-4 whitespace-nowrap">
                      <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${SCAN_STATUS_COLORS[scan.status]}`}>
                        {scan.status}
                      </span>
                    </td>
                    <td className="py-3.5 px-4 text-slate-700 dark:text-slate-300 font-medium">
                      {scan.records_checked.toLocaleString()}
                    </td>
                    <td className="py-3.5 px-4 font-bold text-indigo-600 dark:text-indigo-400">
                      {scan.issues_found}
                    </td>
                    <td className="py-3.5 px-4 text-xs">
                      <span className="text-red-600 font-bold">{scan.critical_count} critical</span>
                      <span className="text-slate-400 mx-1">/</span>
                      <span className="text-orange-600 font-bold">{scan.high_count} high</span>
                    </td>
                    <td className="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">
                      {scan.initiated_by?.name || 'System Automated'}
                    </td>
                    <td className="py-3.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                      {scan.started_at ? new Date(scan.started_at).toLocaleString() : 'N/A'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
