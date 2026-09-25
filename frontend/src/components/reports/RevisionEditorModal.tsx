import React, { useState } from 'react'

interface RevisionEditorModalProps {
  isOpen: boolean
  onClose: () => void
  currentVersion: number
  initialTitle?: string
  initialContent?: string
  initialReferences?: string
  onSubmitRevision: (formData: FormData) => Promise<void>
}

export const RevisionEditorModal: React.FC<RevisionEditorModalProps> = ({
  isOpen,
  onClose,
  currentVersion,
  initialTitle = '',
  initialContent = '',
  initialReferences = '',
  onSubmitRevision,
}) => {
  const [title, setTitle] = useState(initialTitle)
  const [content, setContent] = useState(initialContent)
  const [references, setReferences] = useState(initialReferences)
  const [changeSummary, setChangeSummary] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!isOpen) return null

  const nextVersion = currentVersion + 1

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!changeSummary.trim()) {
      setError('Please provide a summary of the changes made in this revision.')
      return
    }

    try {
      setSubmitting(true)
      setError(null)
      const formData = new FormData()
      formData.append('title', title)
      formData.append('content', content)
      if (references) formData.append('references', references)
      formData.append('change_summary', changeSummary)
      if (file) formData.append('file', file)

      await onSubmitRevision(formData)
      onClose()
    } catch (err: any) {
      setError(err?.response?.data?.message || err?.message || 'Failed to submit revision.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-3xl w-full overflow-hidden border border-gray-100 animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-amber-500 to-amber-600 text-white">
          <div className="flex items-center gap-2">
            <span className="text-xl">🔄</span>
            <div>
              <h3 className="text-lg font-bold">Submit Revision (Version {nextVersion})</h3>
              <p className="text-xs text-amber-100">
                Prior version (v{currentVersion}) will be preserved in permanent immutable version history.
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-colors"
          >
            <span className="text-lg leading-none">✕</span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 text-sm text-rose-700 bg-rose-50 border border-rose-200 rounded-lg flex items-center gap-2">
              <span className="text-base flex-shrink-0">ℹ️</span>
              <span>{error}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
              Revision Title
            </label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
              className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
              Summary of Changes / Revision Notes <span className="text-rose-500">* (Required)</span>
            </label>
            <textarea
              value={changeSummary}
              onChange={(e) => setChangeSummary(e.target.value)}
              placeholder="Detail what updates, corrections, or additions were made based on reviewer feedback..."
              rows={2}
              required
              className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
              Revised Report Content
            </label>
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              rows={6}
              required
              placeholder="Full revised report body..."
              className="w-full text-sm font-mono rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
            />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                Updated References / Citations
              </label>
              <textarea
                value={references}
                onChange={(e) => setReferences(e.target.value)}
                rows={3}
                placeholder="Institutional policies, CMO guidelines, meeting minutes..."
                className="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                Updated Document / PDF Attachment (Optional)
              </label>
              <div className="border-2 border-dashed border-gray-200 rounded-lg p-3 text-center hover:bg-gray-50 transition-colors">
                <input
                  type="file"
                  id="revised-file-upload"
                  className="hidden"
                  onChange={(e) => setFile(e.target.files?.[0] || null)}
                  accept=".pdf,.docx,.txt,.png,.jpg,.jpeg"
                />
                <label
                  htmlFor="revised-file-upload"
                  className="cursor-pointer flex flex-col items-center justify-center gap-1"
                >
                  <span className="text-2xl">📎</span>
                  <span className="text-xs text-amber-600 font-medium">
                    {file ? file.name : 'Select updated file'}
                  </span>
                  <span className="text-[10px] text-gray-400">PDF, Word, or Scanned Image (Max 20MB)</span>
                </label>
              </div>
            </div>
          </div>

          <div className="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg shadow-sm disabled:opacity-50 transition-colors flex items-center gap-2"
            >
              <span>🔄</span>
              {submitting ? 'Submitting v' + nextVersion + '...' : 'Submit Revision v' + nextVersion}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
