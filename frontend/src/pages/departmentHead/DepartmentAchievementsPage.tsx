import { useCallback, useEffect, useState } from 'react'
import {
  createRecommendation,
  getDepartmentAchievements,
  getMyRecommendations,
  getRecommendationOptions,
} from '../../services/departmentHeadService'
import type {
  AchievementRecommendation,
  AchievementRecommendationOptions,
  DepartmentAchievementsData,
} from '../../services/departmentHeadService'
import { getApiErrorMessage } from '../../utils/apiError'

const STATUS_TONES: Record<AchievementRecommendation['verification_status'], string> = {
  pending: 'bg-amber-50 text-amber-700 border-amber-200',
  verified: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  rejected: 'bg-red-50 text-red-700 border-red-200',
}

const STATUS_LABELS: Record<AchievementRecommendation['verification_status'], string> = {
  pending: 'Pending HR review',
  verified: 'Approved',
  rejected: 'Rejected',
}

export default function DepartmentAchievementsPage() {
  const [data, setData] = useState<DepartmentAchievementsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const [options, setOptions] = useState<AchievementRecommendationOptions | null>(null)
  const [recommendations, setRecommendations] = useState<AchievementRecommendation[]>([])
  const [recLoading, setRecLoading] = useState(true)
  const [recError, setRecError] = useState<string | null>(null)

  const [employeeId, setEmployeeId] = useState('')
  const [badgeId, setBadgeId] = useState('')
  const [details, setDetails] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [formMessage, setFormMessage] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    getDepartmentAchievements()
      .then(res => setData(res.data))
      .catch(() => setError('Failed to load department achievements.'))
      .finally(() => setLoading(false))
  }, [])

  const loadRecommendations = useCallback(() => {
    setRecLoading(true)
    setRecError(null)
    Promise.all([
      getMyRecommendations(),
      getRecommendationOptions().catch(() => null),
    ])
      .then(([recs, opts]) => {
        setRecommendations(recs.data.recommendations)
        if (opts) setOptions(opts.data)
      })
      .catch(cause => setRecError(getApiErrorMessage(cause, 'Failed to load your recommendations.')))
      .finally(() => setRecLoading(false))
  }, [])

  useEffect(() => {
    loadRecommendations()
  }, [loadRecommendations])

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSubmitting(true)
    setFormMessage(null)
    setFormError(null)
    try {
      await createRecommendation({
        employee_id: Number(employeeId),
        badge_id: Number(badgeId),
        details,
      })
      setDetails('')
      setBadgeId('')
      setEmployeeId('')
      setFormMessage('Recommendation submitted. HR will review it in the pending queue.')
      loadRecommendations()
    } catch (cause) {
      setFormError(getApiErrorMessage(cause, 'Unable to submit the recommendation.'))
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return <div className="p-6 space-y-4">{[...Array(4)].map((_, i) => <div key={i} className="h-20 bg-gray-100 rounded-xl animate-pulse" />)}</div>
  }

  if (error || !data) {
    return <div className="p-6 bg-red-50 text-red-700 rounded-xl border border-red-200">{error ?? 'No achievements data.'}</div>
  }

  return (
    <div className="p-6 space-y-6 max-w-7xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Department Achievements & Badges</h1>
        <p className="text-sm text-gray-500 mt-0.5">Recognize faculty excellence based on objective milestone criteria</p>
      </div>

      {/* Top Performers */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg flex items-center gap-2">
          <span>🏆</span> Monthly Top Faculty Performers
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {data.top_performers.slice(0, 3).map((tp, idx) => (
            <div key={tp.employee_id} className="p-5 rounded-2xl bg-gradient-to-br from-amber-50/60 to-yellow-50/40 border border-amber-200 flex items-center gap-4">
              <div className="text-3xl">{tp.icon}</div>
              <div className="min-w-0">
                <span className="text-[11px] font-bold uppercase tracking-wider text-amber-800">Rank #{idx + 1}</span>
                <h4 className="font-bold text-gray-900 text-sm break-words">{tp.name}</h4>
                <p className="text-xs text-gray-500 break-words">{tp.badge} · <b>{tp.completion_rate}% Completion</b></p>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Standard Department Badges */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg">Department Badges & Standards</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {data.badges.map((b, i) => (
            <div key={i} className="p-4 rounded-xl border border-gray-100 bg-gray-50/50 space-y-2">
              <span className="text-2xl block">{b.icon}</span>
              <h4 className="font-bold text-gray-900 text-sm">{b.title}</h4>
              <p className="text-xs text-gray-600">{b.description}</p>
              <span className="text-[11px] text-gray-400 font-medium block pt-1 border-t border-gray-200">
                Criteria: {b.criteria}
              </span>
            </div>
          ))}
        </div>
      </div>

      {/* Recommend an employee for official recognition */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div>
          <h3 className="font-bold text-gray-900 text-lg flex items-center gap-2">
            <span>⭐</span> Recommend for Official Recognition
          </h3>
          <p className="text-xs text-gray-500 mt-0.5">
            Nominate one of your department's employees for a badge. Recommendations are recorded
            as pending recognitions and reviewed by HR — you cannot approve your own recommendation.
          </p>
        </div>

        {formMessage ? (
          <p className="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2" role="status">
            {formMessage}
          </p>
        ) : null}
        {formError ? (
          <p className="text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert">
            {formError}
          </p>
        ) : null}

        {options ? (
          <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label className="space-y-1.5">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Employee</span>
              <select
                required
                value={employeeId}
                onChange={e => setEmployeeId(e.target.value)}
                className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option value="">Select an employee…</option>
                {options.employees.map(emp => (
                  <option key={emp.id} value={emp.id}>
                    {emp.name} ({emp.employee_number ?? '—'}){emp.position ? ` · ${emp.position}` : ''}
                  </option>
                ))}
              </select>
            </label>

            <label className="space-y-1.5">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Badge</span>
              <select
                required
                value={badgeId}
                onChange={e => setBadgeId(e.target.value)}
                className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option value="">Select a badge…</option>
                {options.badges.map(badge => (
                  <option key={badge.id} value={badge.id}>
                    {badge.icon ? `${badge.icon} ` : ''}{badge.name}{badge.tier ? ` · ${badge.tier}` : ''}
                  </option>
                ))}
              </select>
            </label>

            <label className="space-y-1.5 md:col-span-2">
              <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500">Supporting details</span>
              <textarea
                required
                minLength={10}
                maxLength={2000}
                rows={4}
                value={details}
                onChange={e => setDetails(e.target.value)}
                placeholder="Why does this employee deserve recognition? Cite concrete tasks, reports, deadlines met or customer feedback."
                className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
            </label>

            <div className="md:col-span-2">
              <button
                type="submit"
                disabled={submitting}
                className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60"
              >
                {submitting ? 'Submitting…' : 'Submit recommendation'}
              </button>
            </div>
          </form>
        ) : (
          <div className="p-4 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-500 animate-pulse">
            Loading recommendation options…
          </div>
        )}
      </div>

      {/* My submitted recommendations */}
      <div className="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h3 className="font-bold text-gray-900 text-lg flex items-center gap-2">
          <span>📋</span> My Recommendations
        </h3>

        {recError ? (
          <p className="text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert">
            {recError}
          </p>
        ) : null}

        {recLoading ? (
          <div className="p-4 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-500 animate-pulse">
            Loading your recommendations…
          </div>
        ) : recommendations.length === 0 ? (
          <div className="p-6 rounded-xl bg-gray-50 border border-gray-200 text-center text-sm text-gray-500">
            You have not submitted any recommendations yet.
          </div>
        ) : (
          <div className="space-y-3">
            {recommendations.map(rec => (
              <div key={rec.id} className="p-4 rounded-xl border border-gray-200 bg-gray-50/50 space-y-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-bold text-gray-900 break-words">
                      {rec.badge_icon ? `${rec.badge_icon} ` : ''}{rec.badge_name}
                      <span className="text-gray-400 font-medium"> · for</span>
                      <span className="text-gray-900"> {rec.employee_name}</span>
                    </p>
                    <p className="text-[11px] text-gray-500">
                      {rec.employee_number ?? '—'}{rec.position ? ` · ${rec.position}` : ''}
                      {rec.recommended_at ? ` · submitted ${new Date(rec.recommended_at).toLocaleDateString()}` : ''}
                    </p>
                  </div>
                  <span className={`text-[11px] font-bold px-2.5 py-1 rounded-full border ${STATUS_TONES[rec.verification_status]}`}>
                    {STATUS_LABELS[rec.verification_status]}
                  </span>
                </div>

                {rec.recommendation_details ? (
                  <p className="text-xs text-gray-600 break-words">{rec.recommendation_details}</p>
                ) : null}

                {rec.verification_status === 'rejected' && rec.rejection_reason ? (
                  <p className="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-1.5 break-words">
                    HR feedback: {rec.rejection_reason}
                  </p>
                ) : null}
                {rec.verification_status === 'verified' && rec.reviewed_by ? (
                  <p className="text-[11px] text-emerald-700">
                    Approved by {rec.reviewed_by}
                    {rec.verified_at ? ` on ${new Date(rec.verified_at).toLocaleDateString()}` : ''}
                  </p>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
