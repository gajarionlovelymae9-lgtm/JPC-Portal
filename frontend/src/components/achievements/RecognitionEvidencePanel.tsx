import { toRecognitionEvidence } from '../../utils/recognitionEvidence'

interface RecognitionEvidencePanelProps {
  evidence: unknown
  /** Shown when no meaningful evidence survives normalization. */
  emptyMessage?: string
}

/**
 * Readable evidence / criteria snapshot panel.
 *
 * Never renders raw JSON: the stored snapshot is normalised into labelled
 * rows by `toRecognitionEvidence()` (nulls and internal seeder metadata are
 * dropped, percentages and dates formatted). Shared by the HR and Admin
 * recognition-detail modals.
 */
export function RecognitionEvidencePanel({
  evidence,
  emptyMessage = 'No evidence details available',
}: RecognitionEvidencePanelProps) {
  const normalised = toRecognitionEvidence(evidence)

  if (!normalised.hasEvidence) {
    return <p className="text-slate-500">{emptyMessage}</p>
  }

  return (
    <div className="border border-slate-200 rounded-xl px-3 py-1 bg-slate-50/60 overflow-hidden">
      <dl>
        {normalised.rows.map((row, index) => (
          <div
            key={`${row.label}-${index}`}
            className="grid grid-cols-[auto_1fr] gap-x-4 py-2 border-b border-slate-200/70 last:border-0"
          >
            <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap self-center">
              {row.label}
            </dt>
            <dd className="text-slate-700 break-words whitespace-normal min-w-0">{row.value}</dd>
          </div>
        ))}
      </dl>
      {normalised.criteria.length > 0 ? (
        <div className="py-2 border-t border-slate-200/70">
          <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Criteria</p>
          <ul className="list-disc pl-5 space-y-1">
            {normalised.criteria.map((item, index) => (
              <li key={index} className="text-slate-700 break-words whitespace-normal min-w-0">
                {item}
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}
