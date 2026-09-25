import React, { useEffect, useState } from 'react'
import { reportWorkflowService } from '../../services/reportWorkflowService'
import type { VersionComparisonData, ReportVersionSnapshot } from '../../types/reportWorkflow'


interface VersionComparisonModalProps {
  isOpen: boolean
  onClose: () => void
  reportId: number
  availableVersions: ReportVersionSnapshot[]
  defaultFrom?: number
  defaultTo?: number
}

export const VersionComparisonModal: React.FC<VersionComparisonModalProps> = ({
  isOpen,
  onClose,
  reportId,
  availableVersions,
  defaultFrom,
  defaultTo,
}) => {
  const sortedVersions = [...availableVersions].sort((a, b) => a.version_number - b.version_number)
  const initialFrom = defaultFrom ?? (sortedVersions.length >= 2 ? sortedVersions[sortedVersions.length - 2].version_number : 1)
  const initialTo = defaultTo ?? (sortedVersions.length >= 1 ? sortedVersions[sortedVersions.length - 1].version_number : 1)

  const [fromVersion, setFromVersion] = useState<number>(initialFrom)
  const [toVersion, setToVersion] = useState<number>(initialTo)
  const [diffData, setDiffData] = useState<VersionComparisonData | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (isOpen && reportId) {
      loadComparison(fromVersion, toVersion)
    }
  }, [isOpen, reportId, fromVersion, toVersion])

  const loadComparison = async (from: number, to: number) => {
    try {
      setLoading(true)
      setError(null)
      const res = await reportWorkflowService.compareVersions(reportId, from, to)
      if (res.success && res.data) {
        setDiffData(res.data)
      }
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Failed to compare versions.')
    } finally {
      setLoading(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] flex flex-col overflow-hidden border border-gray-100">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 bg-slate-900 text-white flex-shrink-0">
          <div className="flex items-center gap-2">
            <span className="text-indigo-400 text-xl">⇄</span>
            <div>
              <h3 className="text-lg font-bold">Multi-Version Diff Comparison</h3>
              <p className="text-xs text-slate-300">
                Audited version delta and content modification tracking
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
          >
            <span className="text-lg leading-none">✕</span>
          </button>
        </div>

        {/* Version Selectors Bar */}
        <div className="bg-slate-50 border-b border-slate-200 px-6 py-3 flex flex-wrap items-center justify-between gap-4 flex-shrink-0">
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Base Version:</span>
              <select
                value={fromVersion}
                onChange={(e) => setFromVersion(Number(e.target.value))}
                className="text-xs rounded-lg border-slate-300 shadow-sm font-medium focus:border-indigo-500 focus:ring-indigo-500"
              >
                {sortedVersions.map((v) => (
                  <option key={v.id} value={v.version_number}>
                    v{v.version_number} ({v.status})
                  </option>
                ))}
              </select>
            </div>

            <span className="text-slate-400 font-bold">→</span>

            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold text-slate-600 uppercase tracking-wider">Target Version:</span>
              <select
                value={toVersion}
                onChange={(e) => setToVersion(Number(e.target.value))}
                className="text-xs rounded-lg border-slate-300 shadow-sm font-medium focus:border-indigo-500 focus:ring-indigo-500"
              >
                {sortedVersions.map((v) => (
                  <option key={v.id} value={v.version_number}>
                    v{v.version_number} ({v.status})
                  </option>
                ))}
              </select>
            </div>
          </div>

          {diffData && (
            <div className="flex items-center gap-2 text-xs">
              <span className="px-2 py-1 rounded bg-slate-200 font-medium text-slate-700">
                Line Diff: {diffData.changes.line_difference >= 0 ? `+${diffData.changes.line_difference}` : diffData.changes.line_difference}
              </span>
              <span className="px-2 py-1 rounded bg-slate-200 font-medium text-slate-700">
                Char Diff: {diffData.changes.length_difference >= 0 ? `+${diffData.changes.length_difference}` : diffData.changes.length_difference}
              </span>
            </div>
          )}
        </div>

        {/* Comparison Body */}
        <div className="p-6 overflow-y-auto flex-1 space-y-6">
          {loading ? (
            <div className="flex items-center justify-center p-12">
              <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin" />
            </div>
          ) : error ? (
            <div className="p-4 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-sm">
              {error}
            </div>
          ) : diffData ? (
            <>
              {/* Change Overview Badges */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div className="p-3 rounded-lg border bg-white shadow-sm flex items-center justify-between">
                  <span className="text-xs text-slate-600">Title Changed</span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${diffData.changes.title_changed ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                    {diffData.changes.title_changed ? 'Yes (Modified)' : 'Identical'}
                  </span>
                </div>
                <div className="p-3 rounded-lg border bg-white shadow-sm flex items-center justify-between">
                  <span className="text-xs text-slate-600">Content Body</span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${diffData.changes.content_changed ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                    {diffData.changes.content_changed ? 'Yes (Updated)' : 'Identical'}
                  </span>
                </div>
                <div className="p-3 rounded-lg border bg-white shadow-sm flex items-center justify-between">
                  <span className="text-xs text-slate-600">References</span>
                  <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${diffData.changes.references_changed ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>
                    {diffData.changes.references_changed ? 'Yes (Modified)' : 'Identical'}
                  </span>
                </div>
              </div>

              {diffData.to_version.change_summary && (
                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                  <h4 className="text-xs font-bold text-amber-900 uppercase tracking-wider mb-1">
                    v{diffData.to_version.version_number} Revision Change Summary
                  </h4>
                  <p className="text-xs text-amber-800">{diffData.to_version.change_summary}</p>
                </div>
              )}

              {/* Side by Side Content View */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* From Column */}
                <div className="border border-slate-200 rounded-xl overflow-hidden bg-slate-50 flex flex-col">
                  <div className="px-4 py-2.5 bg-slate-200 border-b border-slate-300 flex items-center justify-between">
                    <span className="font-bold text-xs text-slate-800">
                      v{diffData.from_version.version_number}: {diffData.from_version.title}
                    </span>
                    <span className="text-[11px] text-slate-600 flex items-center gap-1">
                      <span>🕐</span>
                      {diffData.from_version.submitted_at ? new Date(diffData.from_version.submitted_at).toLocaleDateString() : 'Draft'}
                    </span>
                  </div>
                  <div className="p-4 font-mono text-xs text-slate-800 whitespace-pre-wrap overflow-y-auto max-h-96 flex-1 bg-white">
                    {diffData.side_by_side.from_content || <span className="text-slate-400 italic">No content</span>}
                  </div>
                  {diffData.side_by_side.from_references && (
                    <div className="p-3 bg-slate-100 border-t border-slate-200 text-xs text-slate-700">
                      <strong>References:</strong> {diffData.side_by_side.from_references}
                    </div>
                  )}
                </div>

                {/* To Column */}
                <div className="border border-indigo-200 rounded-xl overflow-hidden bg-indigo-50 flex flex-col">
                  <div className="px-4 py-2.5 bg-indigo-100 border-b border-indigo-200 flex items-center justify-between">
                    <span className="font-bold text-xs text-indigo-900">
                      v{diffData.to_version.version_number}: {diffData.to_version.title}
                    </span>
                    <span className="text-[11px] text-indigo-700 flex items-center gap-1">
                      <span>🕐</span>
                      {diffData.to_version.submitted_at ? new Date(diffData.to_version.submitted_at).toLocaleDateString() : 'Draft'}
                    </span>
                  </div>
                  <div className="p-4 font-mono text-xs text-indigo-950 whitespace-pre-wrap overflow-y-auto max-h-96 flex-1 bg-white">
                    {diffData.side_by_side.to_content || <span className="text-slate-400 italic">No content</span>}
                  </div>
                  {diffData.side_by_side.to_references && (
                    <div className="p-3 bg-indigo-50 border-t border-indigo-100 text-xs text-indigo-900">
                      <strong>References:</strong> {diffData.side_by_side.to_references}
                    </div>
                  )}
                </div>
              </div>
            </>
          ) : null}
        </div>
      </div>
    </div>
  )
}
