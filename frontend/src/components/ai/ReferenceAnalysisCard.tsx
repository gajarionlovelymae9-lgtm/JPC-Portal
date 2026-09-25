import type { ReferenceMatrixData } from '../../services/aiReportService'

interface Props {
  references: ReferenceMatrixData | null
  loading?: boolean
}

export function ReferenceAnalysisCard({ references, loading }: Props) {
  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/4"></div>
        <div className="h-24 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  if (!references) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm text-center space-y-2">
        <span className="text-4xl">🔗</span>
        <h3 className="font-extrabold text-gray-900 text-base">No Reference Analysis</h3>
        <p className="text-xs text-gray-500">Reference anchoring has not been run for this report.</p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
            🔗
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900">
              Reference Anchoring & Citations
            </h3>
            <span className="text-xs text-gray-400 font-medium">Policy and Guideline Connection</span>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-xs font-bold text-gray-500">Anchor Score:</span>
          <span className="text-sm font-black text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">
            {Math.round(references.anchor_score * 100)}%
          </span>
        </div>
      </div>

      {/* References Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="border-b border-gray-100 bg-gray-50/50 text-[11px] font-extrabold uppercase tracking-wider text-gray-400">
              <th className="py-3 px-4">Required Reference / Guideline</th>
              <th className="py-3 px-4">Status</th>
              <th className="py-3 px-4">Confidence</th>
              <th className="py-3 px-4">Analysis Note</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50 text-xs">
            {references.references.length === 0 ? (
              <tr>
                <td colSpan={4} className="py-6 text-center text-gray-400">
                  No task references specified.
                </td>
              </tr>
            ) : (
              references.references.map((ref, idx) => {
                const isAnchored = ref.status === 'Referenced'

                return (
                  <tr key={idx} className="hover:bg-gray-50/50 transition-colors">
                    <td className="py-3 px-4 font-bold text-gray-900">
                      <div>{ref.title}</div>
                      {ref.url && (
                        <span className="text-[10px] text-blue-500 font-mono truncate max-w-xs block">
                          {ref.url}
                        </span>
                      )}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full ${
                          isAnchored
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        {ref.status}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-mono font-semibold text-gray-700">
                      {Math.round(ref.confidence * 100)}%
                    </td>
                    <td className="py-3 px-4 text-gray-600 leading-relaxed max-w-sm">
                      {ref.note}
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export default ReferenceAnalysisCard
