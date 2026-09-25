import type { SimilarityMatrixData } from '../../services/aiReportService'

interface Props {
  similarity: SimilarityMatrixData | null
  loading?: boolean
}

export function SimilarityCard({ similarity, loading }: Props) {
  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/4"></div>
        <div className="h-24 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  if (!similarity) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm text-center space-y-2">
        <span className="text-4xl">👥</span>
        <h3 className="font-extrabold text-gray-900 text-base">No Similarity Comparison</h3>
        <p className="text-xs text-gray-500">Cross-submission similarity analysis has not been executed.</p>
      </div>
    )
  }

  const isDuplicate = similarity.risk_level === 'potential_duplicate'
  const isHigh = similarity.risk_level === 'high_similarity'
  const isMod = similarity.risk_level === 'moderate_similarity'

  const badgeColor = isDuplicate
    ? 'bg-red-100 text-red-800'
    : isHigh
    ? 'bg-amber-100 text-amber-800'
    : isMod
    ? 'bg-blue-100 text-blue-800'
    : 'bg-emerald-100 text-emerald-800'

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold">
            👥
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900">
              {similarity.advisory_label}
            </h3>
            <span className="text-xs text-gray-400 font-medium">Cross-Submission Overlap Index</span>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <span className={`px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider ${badgeColor}`}>
            {similarity.risk_level.replace('_', ' ')}
          </span>
          <span className="text-xl font-black text-gray-900 font-mono">
            {similarity.overall_similarity_score}%
          </span>
        </div>
      </div>

      {/* Advisory Disclaimer */}
      <div className="p-3.5 rounded-2xl bg-purple-50/70 border border-purple-100 text-purple-900 text-xs flex items-start gap-2.5">
        <span className="text-base flex-shrink-0 mt-0.5">ℹ️</span>
        <p className="leading-relaxed font-medium">
          <strong>Advisory Notice:</strong> {similarity.disclaimer}
        </p>
      </div>

      {/* Comparisons List */}
      <div className="space-y-3">
        <h4 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">
          Internal Report Comparisons ({similarity.comparisons.length})
        </h4>

        {similarity.comparisons.length === 0 ? (
          <div className="p-6 text-center text-xs text-gray-400 bg-gray-50/50 rounded-2xl">
            No other active reports on this task for comparison.
          </div>
        ) : (
          <div className="space-y-2.5">
            {similarity.comparisons.map((c) => (
              <div
                key={c.compared_report_id}
                className="p-4 rounded-2xl border border-gray-100 bg-gray-50/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              >
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 mb-1">
                    <h5 className="font-bold text-xs text-gray-900">
                      Report #{c.compared_report_id} — {c.author}
                    </h5>
                    <span className="text-[10px] text-gray-400 font-medium truncate">
                      ({c.task_title})
                    </span>
                  </div>
                  <p className="text-xs text-gray-500 line-clamp-1">{c.explanation}</p>
                </div>

                <div className="flex items-center gap-2 flex-shrink-0">
                  <span
                    className={`text-xs font-black font-mono px-2.5 py-1 rounded-xl ${
                      c.similarity_score >= 80
                        ? 'bg-red-100 text-red-700'
                        : c.similarity_score >= 50
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-gray-100 text-gray-700'
                    }`}
                  >
                    {c.similarity_score}%
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default SimilarityCard
