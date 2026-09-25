import React from 'react'
import { Link } from 'react-router-dom'
import { useDataQuality } from '../../hooks/dataQuality/useDataQuality'
import { useDataQualityScans } from '../../hooks/dataQuality/useDataQualityScans'
import { DataQualityScoreCard } from '../../components/DataQuality/DataQualityScoreCard'
import { DataQualityScanButton } from '../../components/DataQuality/DataQualityScanButton'
import { DataQualityScanProgress } from '../../components/DataQuality/DataQualityScanProgress'
import { DataQualityCategoryChart } from '../../components/DataQuality/DataQualityCategoryChart'
import { DataQualityTrendChart } from '../../components/DataQuality/DataQualityTrendChart'
import { SCAN_STATUS_COLORS } from '../../utils/dataQuality/dataQualityConstants'

export const DataQualityDashboardPage: React.FC = () => {
  const { data, loading, error, refresh } = useDataQuality()
  const { scanning, triggerScan } = useDataQualityScans()

  const handleScan = async (type: string) => {
    await triggerScan(type)
    await refresh()
  }

  if (loading && !data) {
    return (
      <div className="p-8 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent" />
        <p className="text-sm text-slate-500 mt-2">Loading Data Quality Dashboard...</p>
      </div>
    )
  }

  if (error && !data) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm">
          {error}
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      {/* Top Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-black text-slate-900 dark:text-white">
            Data Quality & Integrity Monitoring
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Automated institutional rule checks, defect detection, and human review workflows.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Link
            to="/data-quality/issues"
            className="px-4 py-2 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-700 transition-colors shadow-sm"
          >
            View All Issues ({data?.open_issues ?? 0})
          </Link>
          <DataQualityScanButton onScan={handleScan} loading={scanning} />
        </div>
      </div>

      <DataQualityScanProgress scanning={scanning} />

      {/* Main Score Card */}
      {data && (
        <DataQualityScoreCard
          score={data.score}
          totalIssues={data.total_issues}
          openIssues={data.open_issues}
          critical={data.critical}
          high={data.high}
          medium={data.medium}
          low={data.low}
          resolved={data.resolved}
          dismissed={data.dismissed}
        />
      )}

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <DataQualityCategoryChart byCategory={data?.by_category ?? {}} />
        <DataQualityTrendChart trends={data?.trends ?? []} />
      </div>

      {/* Recent Scans & Department Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Issues by Department */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
          <h3 className="text-base font-bold text-slate-900 dark:text-white mb-4">
            Open Issues by Department
          </h3>
          {(!data?.by_department || data.by_department.length === 0) ? (
            <p className="text-sm text-slate-500 py-6 text-center">No departmental issues flagged.</p>
          ) : (
            <div className="divide-y divide-slate-100 dark:divide-slate-700">
              {data.by_department.map((dept) => (
                <div key={dept.department_id} className="py-2.5 flex items-center justify-between text-sm">
                  <span className="font-semibold text-slate-800 dark:text-slate-200">{dept.department_name}</span>
                  <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                    {dept.count} issues
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Recent Scans Table */}
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-base font-bold text-slate-900 dark:text-white">Recent Automated Scans</h3>
            <Link to="/data-quality/scans" className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
              View All History
            </Link>
          </div>

          {(!data?.recent_scans || data.recent_scans.length === 0) ? (
            <p className="text-sm text-slate-500 py-6 text-center">No scans executed yet.</p>
          ) : (
            <div className="divide-y divide-slate-100 dark:divide-slate-700">
              {data.recent_scans.map((scan) => (
                <div key={scan.id} className="py-2.5 flex items-center justify-between text-xs">
                  <div>
                    <span className="font-bold text-slate-800 dark:text-slate-200 capitalize">
                      {scan.scan_type} Scan #{scan.id}
                    </span>
                    <span className="text-slate-400 ml-2">
                      {new Date(scan.created_at).toLocaleDateString()}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-slate-500">{scan.issues_found} issues</span>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${SCAN_STATUS_COLORS[scan.status]}`}>
                      {scan.status}
                    </span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
