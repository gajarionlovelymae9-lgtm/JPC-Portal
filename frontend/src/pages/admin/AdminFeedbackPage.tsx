import { useCallback, useEffect, useState } from 'react'
import {
  getAiFeedbackList,
  getFeedbackList,
  updateFeedback,
  type AiFeedbackItem,
  type UserFeedbackItem,
} from '../../services/systemMaintenanceService'

const STATUS_COLOR: Record<string, string> = {
  OPEN: 'bg-blue-100 text-blue-700',
  UNDER_REVIEW: 'bg-amber-100 text-amber-700',
  IN_PROGRESS: 'bg-purple-100 text-purple-700',
  RESOLVED: 'bg-emerald-100 text-emerald-700',
  REJECTED: 'bg-red-100 text-red-700',
  CLOSED: 'bg-gray-200 text-gray-700',
}

const NEXT_STATES: Record<string, string[]> = {
  OPEN: ['UNDER_REVIEW', 'IN_PROGRESS', 'REJECTED', 'CLOSED'],
  UNDER_REVIEW: ['IN_PROGRESS', 'RESOLVED', 'REJECTED', 'CLOSED'],
  IN_PROGRESS: ['RESOLVED', 'REJECTED', 'CLOSED'],
  RESOLVED: ['CLOSED'],
  REJECTED: ['CLOSED'],
  CLOSED: [],
}

export default function FeedbackAdminPage() {
  const [feedbacks, setFeedbacks] = useState<UserFeedbackItem[]>([])
  const [aiFeedback, setAiFeedback] = useState<AiFeedbackItem[]>([])
  const [filter, setFilter] = useState('')
  const [error, setError] = useState('')

  const load = useCallback(async () => {
    try {
      const [listRes, aiRes] = await Promise.all([
        getFeedbackList(filter ? { status: filter } : {}),
        getAiFeedbackList(),
      ])
      setFeedbacks(listRes.data.data)
      setAiFeedback(aiRes.data)
      setError('')
    } catch {
      setError('Unable to load feedback.')
    }
  }, [filter])

  useEffect(() => {
    void load()
  }, [load])

  const mark = async (id: number, status: string) => {
    try {
      await updateFeedback(id, { status })
      await load()
    } catch {
      setError('Unable to update feedback status.')
    }
  }

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Feedback Management</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          Bug reports, incorrect-information flags, AI & OCR concerns, and feature requests from all users.
        </p>
      </div>

      {error && <div className="bg-red-50 border border-red-200 text-red-700 text-sm p-4 rounded-2xl">{error}</div>}

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-bold text-gray-900">User Feedback</h2>
          <select
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            className="border border-gray-300 rounded-xl px-3 py-1.5 text-sm bg-white"
          >
            <option value="">All statuses</option>
            {Object.keys(STATUS_COLOR).map((status) => (
              <option key={status} value={status}>{status}</option>
            ))}
          </select>
        </div>

        {feedbacks.length === 0 ? (
          <p className="text-sm text-gray-500">No feedback matching the filter.</p>
        ) : (
          <ul className="space-y-3">
            {feedbacks.map((item) => (
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
                <p className="text-xs text-gray-400 mt-2">
                  {item.user?.name ?? 'Unknown'} · priority {item.priority} · {new Date(item.created_at).toLocaleString()}
                </p>
                {item.resolution ? (
                  <p className="text-xs text-gray-500 mt-2">Resolution: {item.resolution}</p>
                ) : null}
                {NEXT_STATES[item.status]?.length ? (
                  <div className="flex flex-wrap gap-2 mt-3">
                    {NEXT_STATES[item.status].map((next) => (
                      <button
                        key={next}
                        onClick={() => void mark(item.id, next)}
                        className="px-3 py-1 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50"
                      >
                        {next.replace('_', ' ')}
                      </button>
                    ))}
                  </div>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="bg-white border border-gray-200 rounded-3xl p-6 shadow-sm space-y-4">
        <h2 className="text-lg font-bold text-gray-900">AI Quality Feedback</h2>
        <p className="text-xs text-gray-500">
          Verdicts are recorded against the AI output history without modifying the original AI results or performance scores.
        </p>
        {aiFeedback.length === 0 ? (
          <p className="text-sm text-gray-500">No AI feedback recorded yet.</p>
        ) : (
          <ul className="space-y-2">
            {aiFeedback.map((item) => (
              <li key={item.id} className="border border-gray-100 rounded-xl px-3 py-2 text-sm flex items-center justify-between">
                <div>
                  <span className="font-semibold text-gray-900">{item.verdict}</span>
                  <span className="text-gray-400"> — {item.ai_type}{item.ai_record_id ? ` #${item.ai_record_id}` : ''}</span>
                  {item.comment ? <span className="text-gray-600"> · {item.comment}</span> : null}
                </div>
                <span className="text-xs text-gray-400">{new Date(item.created_at).toLocaleString()}</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}