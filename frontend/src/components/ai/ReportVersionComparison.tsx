import type { VersionComparisonData } from '../../services/aiReportService'

interface Props {
  comparison: VersionComparisonData | null
  loading?: boolean
}

export function ReportVersionComparison({ comparison, loading }: Props) {
  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/4"></div>
        <div className="h-32 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  if (!comparison) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm text-center space-y-2">
        <span className="text-4xl">🔀</span>
        <h3 className="font-extrabold text-gray-900 text-base">Initial Version (v1)</h3>
        <p className="text-xs text-gray-500">
          No previous revision exists to compare against. Version comparison will be available on v2+.
        </p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
            🔀
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900">
              Version Comparison (v{comparison.v1} ➔ v{comparison.v2})
            </h3>
            <span className="text-xs text-gray-400 font-medium">
              Progress & Resolved Findings Between Revisions
            </span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs font-bold bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full">
            {comparison.resolved_findings_count} Resolved Findings
          </span>
          <span className="text-xs font-bold bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
            {comparison.current_findings_count} Current Findings
          </span>
        </div>
      </div>

      {/* Resolved Findings in this Revision */}
      <div className="space-y-3">
        <h4 className="text-xs font-extrabold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
          <span>✓</span> Resolved Findings from Previous Version ({comparison.resolved_findings.length})
        </h4>

        {comparison.resolved_findings.length === 0 ? (
          <div className="p-4 bg-gray-50 rounded-2xl text-xs text-gray-500 text-center">
            No previous findings marked as resolved in this revision.
          </div>
        ) : (
          <div className="space-y-2">
            {comparison.resolved_findings.map((f) => (
              <div
                key={f.id}
                className="p-3.5 bg-emerald-50/50 rounded-2xl border border-emerald-100 flex items-center justify-between text-xs"
              >
                <div className="flex items-center gap-2">
                  <span className="text-emerald-600 font-bold">✓</span>
                  <span className="font-bold text-gray-900">{f.title}</span>
                </div>
                <span className="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                  Resolved in v{comparison.v2}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default ReportVersionComparison
