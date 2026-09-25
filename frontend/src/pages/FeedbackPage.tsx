import { useCallback, useEffect, useState } from 'react'
import {
  getMyFeedback,
  submitAiFeedback,
  submitFeedback,
  type UserFeedbackItem,
} from '../services/systemMaintenanceService'

const CATEGORIES = [
  { value: 'bug', label: 'Bug / Error' },
  { value: 'incorrect_information', label: 'Incorrect Information' },
  { value: 'ui_problem', label: 'UI Problem' },
  { value: 'ai_concern', label: 'AI Concern' },
  { value: 'ocr_concern', label: 'OCR Concern' },
  { value: 'feature_request', label: 'Feature Request' },
  { value: 'usability', label: 'Usability' },
  { value: 'other', label: 'Other' },
]

const STATUS_COLOR: Record<string, string> = {
  OPEN: 'bg-blue-100 text-blue-700',
  UNDER_REVIEW: 'bg-amber-100 text-amber-700',
  IN_PROGRESS: 'bg-purple-100 text-purple-700',
  RESOLVED: 'bg-emerald-100 text-emerald-700',
  REJECTED: 'bg-red-100 text-red-700',
  CLOSED: 'bg-gray-200 text-gray-700',
}

export default function FeedbackPage() {
  const [form, setForm] = useState({ category: 'bug', subject: '', description: '', priority: 'low' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [mine, setMine] = useState<UserFeedbackItem[]>([])

  const loadMine = useCallback(async () => {
    try {
      const res = await getMyFeedback()
      setMine(res.data)
    } catch {
      setError('Unable to load your feedback.')
    }
  }, [])

  useEffect(() => {
    void loadMine()
  }, [loadMine])

  const handleSubmit = async () => {
    setSaving(true)
    setError('')
    setSuccess('')
    try {
      await submitFeedback(form)
      setForm({ category: 'bug', subject: '', description: '', priority: 'low' })
      setSuccess('Feedback submitted. The admin team will review it.')
      await loadMine()
    } catch {
      setError('Unable to submit feedback. Please fill in the subject and description.')
    } finally {
      setSaving(false)
    }
  }

  const handleAiVerdict = async (verdict: string) => {
    try {
      await submitAiFeedback({ ai_type: 'chat', verdict, category: 'quality_feedback' })
      setSuccess(`AI feedback recorded: ${verdict}.`)
    } catch {
      setError('Unable to record AI feedback.')
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-4xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Feedback & Help</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Report issues, incorrect information, or suggest improvements. AI quality feedback helps the team improve
          accuracy without changing official results.
        </p>
      </div>

      {error && <div className="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-2xl">{error}</div>}
      {success && <div className="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm p-4 rounded-2xl">{success}</div>}

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h2 className="text-lg font-bold text-gray-900">Submit Feedback</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <select
            value={form.category}
            onChange={(e) => setForm({ ...form, category: e.target.value })}
            className="border border-gray-300 rounded-2xl px-4 py-2.5 text-sm bg-white"
          >
            {CATEGORIES.map((category) => (
              <option key={category.value} value={category.value}>{category.label}</option>
            ))}
          </select>
          <select
            value={form.priority}
            onChange={(e) => setForm({ ...form, priority: e.target.value })}
            className="border border-gray-300 rounded-2xl px-4 py-2.5 text-sm bg-white"
          >
            <option value="low">Low priority</option>
            <option value="medium">Medium priority</option>
            <option value="high">High priority</option>
            <option value="critical">Critical</option>
          </select>
        </div>
        <input
          value={form.subject}
          onChange={(e) => setForm({ ...form, subject: e.target.value })}
          placeholder="Subject / summary"
          className="w-full border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
        />
        <textarea
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.target.value })}
          placeholder="Describe the issue or suggestion in detail"
          rows={4}
          className="w-full border border-gray-300 rounded-2xl px-4 py-2.5 text-sm"
        />
        <button
          onClick={() => void handleSubmit()}
          disabled={saving || !form.subject || !form.description}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-2xl"
        >
          {saving ? 'Submitting…' : 'Submit'}
        </button>
      </section>

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h2 className="text-lg font-bold text-gray-900">Rate This AI Answer</h2>
        <p className="text-xs text-gray-500">
          After chatting with the AI assistant, rate whether its answer was correct. This never changes the official
          performance data — it only guides prompt improvements.
        </p>
        <div className="flex flex-wrap gap-2">
          {['Correct', 'Partially Correct', 'Incorrect', 'Needs Human Review'].map((verdict) => (
            <button
              key={verdict}
              onClick={() => void handleAiVerdict(verdict)}
              className="px-4 py-2 border border-gray-300 rounded-2xl text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
              {verdict}
            </button>
          ))}
        </div>
      </section>

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-3">
        <h2 className="text-lg font-bold text-gray-900">My Feedback</h2>
        {mine.length === 0 ? (
          <p className="text-sm text-gray-500">You have not submitted any feedback yet.</p>
        ) : (
          <ul className="space-y-3">
            {mine.map((item) => (
              <li key={item.id} className="border border-gray-100 rounded-2xl p-4 text-sm">
                <div className="flex items-center justify-between gap-4">
                  <p className="font-semibold text-gray-900">
                    {item.subject}
                    <span className="text-gray-400 font-normal"> — {item.category}</span>
                  </p>
                  <span className={`shrink-0 px-2 py-0.5 rounded-full text-xs font-bold ${STATUS_COLOR[item.status]}`}>
                    {item.status}
                  </span>
                </div>
                <p className="text-gray-600 mt-1">{item.description}</p>
                {item.resolution ? <p className="text-xs text-gray-500 mt-2">Resolution: {item.resolution}</p> : null}
                <p className="text-xs text-gray-400 mt-2">{new Date(item.created_at).toLocaleString()}</p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}