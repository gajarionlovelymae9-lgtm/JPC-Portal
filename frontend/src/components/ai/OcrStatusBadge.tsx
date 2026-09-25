interface Props {
  status: string
  confidence?: number | null
  characters?: number
}

export function OcrStatusBadge({ status, confidence, characters }: Props) {
  const isCompleted = status === 'completed' || status === 'ocr_completed'
  const isFailed = status === 'failed' || status === 'ocr_failed'
  const isProcessing = status === 'processing' || status === 'ai_processing'

  const badgeClass = isCompleted
    ? 'bg-emerald-100 text-emerald-800'
    : isFailed
    ? 'bg-red-100 text-red-800'
    : isProcessing
    ? 'bg-blue-100 text-blue-800 animate-pulse'
    : 'bg-amber-100 text-amber-800'

  return (
    <div className="inline-flex items-center gap-2">
      <span className={`px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider ${badgeClass}`}>
        OCR: {status.replace('_', ' ')}
      </span>
      {confidence !== null && confidence !== undefined && (
        <span className="text-[11px] font-bold text-gray-500 font-mono">
          ({Math.round(confidence * 100)}% quality)
        </span>
      )}
      {characters !== undefined && (
        <span className="text-[10px] text-gray-400 font-medium">
          {characters.toLocaleString()} chars
        </span>
      )}
    </div>
  )
}

export default OcrStatusBadge
