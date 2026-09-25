import { useState } from 'react'

interface Props {
  text: string
  confidence?: number | null
}

export function ExtractedTextViewer({ text, confidence }: Props) {
  const [search, setSearch] = useState('')

  const wordCount = text ? text.trim().split(/\s+/).length : 0
  const charCount = text ? text.length : 0

  const highlightedText = () => {
    if (!search.trim() || !text) return text
    const regex = new RegExp(`(${search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
    return text.split(regex).map((part, i) =>
      regex.test(part) ? (
        <mark key={i} className="bg-amber-200 text-amber-950 font-bold px-0.5 rounded">
          {part}
        </mark>
      ) : (
        part
      )
    )
  }

  return (
    <div className="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 md:p-8 space-y-5">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-5">
        <div>
          <h3 className="font-extrabold text-base md:text-lg text-gray-900 flex items-center gap-2">
            <span>📄</span> Extracted Document Text (OCR)
          </h3>
          <span className="text-xs text-gray-400 font-medium">
            {wordCount.toLocaleString()} words · {charCount.toLocaleString()} characters
            {confidence !== null && confidence !== undefined && (
              <> · OCR Confidence: {(confidence * 100).toFixed(0)}%</>
            )}
          </span>
        </div>

        <div className="w-full sm:w-64">
          <input
            type="text"
            placeholder="Search text in document..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full px-3.5 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      <div className="bg-gray-50/70 p-5 rounded-2xl border border-gray-100 max-h-[500px] overflow-y-auto font-mono text-xs text-gray-800 leading-relaxed whitespace-pre-wrap select-text">
        {text ? highlightedText() : <span className="text-gray-400 italic">No text extracted.</span>}
      </div>
    </div>
  )
}

export default ExtractedTextViewer
