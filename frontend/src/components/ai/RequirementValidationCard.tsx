import { useState } from 'react'
import type { AiValidationData } from '../../services/aiReportService'

interface Props {
  validation: AiValidationData | null
  loading?: boolean
}

export function RequirementValidationCard({ validation, loading }: Props) {
  const [expandedId, setExpandedId] = useState<number | null>(null)

  if (loading) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm animate-pulse space-y-4">
        <div className="h-6 bg-gray-200 rounded w-1/3"></div>
        <div className="h-28 bg-gray-100 rounded-2xl"></div>
      </div>
    )
  }

  if (!validation) {
    return (
      <div className="bg-white rounded-3xl border border-gray-100 p-8 shadow-sm text-center space-y-2">
        <span className="text-4xl">📋</span>
        <h3 className="font-extrabold text-gray-900 text-base">No Validation Data</h3>
        <p className="text-xs text-gray-500">Task requirement checks have not been run for this report.</p>
      </div>
    )
  }

  const matchStatus = validation.requirement_match?.toLowerCase()
  const isMet = matchStatus === 'met' || matchStatus === 'passed'
  const isPartial = matchStatus === 'partially_met' || matchStatus === 'partial'

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
            ✓
          </div>
          <div>
            <h3 className="font-extrabold text-base md:text-lg text-gray-900 flex items-center gap-2">
              {validation.advisory_label}
            </h3>
            <span className="text-xs text-gray-400 font-medium">Task Requirement Compliance</span>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <span
            className={`px-3.5 py-1.5 rounded-full font-extrabold text-xs uppercase tracking-wider ${
              isMet
                ? 'bg-emerald-100 text-emerald-800'
                : isPartial
                ? 'bg-amber-100 text-amber-800'
                : 'bg-red-100 text-red-800'
            }`}
          >
            {validation.requirement_match}
          </span>
          <span className="text-xs font-bold text-gray-500 bg-gray-100 px-3 py-1.5 rounded-full">
            Confidence: {(validation.confidence_score * 100).toFixed(0)}%
          </span>
        </div>
      </div>

      {/* Advisory Disclaimer */}
      <div className="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-100 text-emerald-900 text-xs flex items-start gap-2.5">
        <span className="text-base flex-shrink-0 mt-0.5">ℹ️</span>
        <p className="leading-relaxed font-medium">
          <strong>Advisory Notice:</strong> {validation.disclaimer}
        </p>
      </div>

      {/* Score Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 text-center">
          <span className="text-[10px] uppercase font-bold text-gray-400 block mb-1">Overall Score</span>
          <span className="text-xl font-black text-gray-900">{Math.round(validation.overall_score * 100)}%</span>
        </div>
        <div className="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 text-center">
          <span className="text-[10px] uppercase font-bold text-gray-400 block mb-1">Task Alignment</span>
          <span className="text-xl font-black text-blue-600">{Math.round(validation.task_alignment_score * 100)}%</span>
        </div>
        <div className="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 text-center">
          <span className="text-[10px] uppercase font-bold text-gray-400 block mb-1">Reference Anchor</span>
          <span className="text-xl font-black text-indigo-600">{Math.round(validation.reference_anchor_score * 100)}%</span>
        </div>
        <div className="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 text-center">
          <span className="text-[10px] uppercase font-bold text-gray-400 block mb-1">Evidence Support</span>
          <span className="text-xl font-black text-emerald-600">{Math.round(validation.evidence_score * 100)}%</span>
        </div>
      </div>

      {/* Explanation */}
      {validation.explanation && (
        <div className="space-y-1.5">
          <h4 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">
            Assessment Rationale
          </h4>
          <p className="text-xs text-gray-700 bg-gray-50/70 p-4 rounded-2xl border border-gray-100 leading-relaxed">
            {validation.explanation}
          </p>
        </div>
      )}

      {/* Requirement Items with Verbatim Evidence */}
      <div className="space-y-3">
        <h4 className="text-xs font-extrabold uppercase tracking-wider text-gray-400">
          Requirement Evidence & Findings ({validation.requirements.length})
        </h4>

        <div className="space-y-2.5">
          {validation.requirements.map((item) => {
            const isExpanded = expandedId === item.id
            const isCrit = item.severity === 'critical'
            const isHigh = item.severity === 'high'

            return (
              <div
                key={item.id}
                className={`rounded-2xl border transition-all ${
                  isCrit
                    ? 'border-red-200 bg-red-50/20'
                    : isHigh
                    ? 'border-amber-200 bg-amber-50/20'
                    : 'border-gray-100 bg-white hover:border-gray-200'
                }`}
              >
                <div
                  onClick={() => setExpandedId(isExpanded ? null : item.id)}
                  className="p-4 flex items-center justify-between gap-3 cursor-pointer select-none"
                >
                  <div className="flex items-center gap-2.5 min-w-0 flex-1">
                    <span
                      className={`text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full flex-shrink-0 ${
                        isCrit
                          ? 'bg-red-100 text-red-700'
                          : isHigh
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-blue-100 text-blue-700'
                      }`}
                    >
                      {item.severity}
                    </span>
                    <h5 className="font-bold text-xs text-gray-900 truncate">{item.finding}</h5>
                  </div>

                  <span className="text-xs text-gray-400 font-bold">{isExpanded ? '▲' : '▼'}</span>
                </div>

                {isExpanded && (
                  <div className="px-4 pb-4 pt-1 border-t border-gray-100 space-y-2 text-xs">
                    {item.evidence && (
                      <div className="p-3 bg-gray-50 rounded-xl border border-gray-100 font-mono text-[11px] text-gray-800">
                        <strong className="block text-gray-400 font-sans text-[10px] uppercase mb-1">
                          Report Evidence Quote:
                        </strong>
                        "{item.evidence}"
                      </div>
                    )}
                    {item.recommendation && (
                      <div className="text-blue-900 bg-blue-50/60 p-2.5 rounded-xl border border-blue-100">
                        <strong>Recommendation:</strong> {item.recommendation}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}

export default RequirementValidationCard
