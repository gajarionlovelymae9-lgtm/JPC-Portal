import { useEffect, useMemo, useState } from 'react'
import { Trophy } from 'lucide-react'
import { getMyAchievements } from '../../services/employeePortalService'
import type { EmployeeAchievement } from '../../services/employeePortalService'
import { performanceService } from '../../services/performanceService'
import type { AchievementProgressItem } from '../../services/performanceService'
import { Modal } from '../../components/admin/AdminUi'
import { AchievementBadgeCard, AchievementBadgeDetail } from '../../components/achievements/AchievementBadge'

export default function EmployeeAchievementsPage() {
  const [achievements, setAchievements] = useState<EmployeeAchievement[]>([])
  const [progress, setProgress] = useState<AchievementProgressItem[]>([])
  const [loading, setLoading] = useState(true)
  const [progressError, setProgressError] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [visibility, setVisibility] = useState<'all' | 'earned' | 'in-progress'>('all')
  const [search, setSearch] = useState('')
  const [selected, setSelected] = useState<{ badge: AchievementProgressItem; earnedAt: string | null } | null>(null)

  useEffect(() => {
    Promise.allSettled([
      getMyAchievements(),
      performanceService.getMyAchievementProgress(),
    ]).then(([earned, tracked]) => {
      if (earned.status === 'fulfilled') {
        setAchievements(Array.isArray(earned.value.data?.badges) ? earned.value.data.badges : [])
      } else {
        setError('Unable to load achievements. Please try again.')
      }
      if (tracked.status === 'fulfilled') {
        setProgress(Array.isArray(tracked.value.data?.badges) ? tracked.value.data.badges : [])
      } else {
        setProgressError('Progress tracking is unavailable right now.')
      }
    }).finally(() => setLoading(false))
  }, [])

  /**
   * The progress payload is the single source for both views: earned badges
   * carry their award date there, so the legacy earned list only contributes
   * entries the tracker does not know about (matched by name).
   */
  const rows = useMemo(() => {
    const merged: Array<{
      badge: AchievementProgressItem
      earnedAt: string | null
    }> = progress.map(badge => ({
      badge,
      earnedAt: badge.award_dates.length > 0 ? badge.award_dates[0] : null,
    }))

    // Any earned badge the tracker does not know about still shows up.
    for (const item of achievements) {
      if (!progress.some(b => b.name.toLowerCase() === String(item.name).toLowerCase())) {
        merged.push({ badge: {
          badge_id: item.id ?? 0,
          code: String(item.name).toUpperCase().replace(/\s+/g, '_'),
          name: item.name,
          description: item.description ?? null,
          icon: item.icon ?? null,
          category: null,
          tier: null,
          criteria_type: null,
          earned: true,
          award_dates: item.earned_at ? [String(item.earned_at)] : [],
          progress: 100,
        }, earnedAt: item.earned_at ?? null })
      }
    }

    const filtered = merged.filter(({ badge, earnedAt }) => {
      if (visibility === 'earned' && !earnedAt && !badge.earned) return false
      if (visibility === 'in-progress' && (earnedAt || badge.earned)) return false
      if (search) {
        const haystack = `${badge.name} ${badge.description ?? ''} ${badge.criteria ?? ''} ${earnedAt ?? ''}`.toLowerCase()
        if (!haystack.includes(search.toLowerCase())) return false
      }
      return true
    })

    return filtered.sort((a, b) => {
      const aEarned = a.earnedAt || a.badge.earned ? 1 : 0
      const bEarned = b.earnedAt || b.badge.earned ? 1 : 0
      if (aEarned !== bEarned) return bEarned - aEarned
      return (b.badge.progress ?? 0) - (a.badge.progress ?? 0)
    })
  }, [achievements, progress, visibility, search])

  const earnedCount = rows.filter(r => r.earnedAt || r.badge.earned).length

  if (loading) return <div className="p-6 grid grid-cols-2 md:grid-cols-3 gap-4">{[...Array(6)].map((_,i) => <div key={i} className="h-32 bg-gray-100 rounded-xl animate-pulse"/>)}</div>

  return (
    <div className="p-6 space-y-6 max-w-6xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">My Achievements</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {earnedCount} earned {earnedCount === 1 ? 'badge' : 'badges'} · progress toward the rest of the catalogue
          </p>
        </div>
      </div>

      {error && <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">{error}</div>}

      {/* Search + visibility filters */}
      <div className="flex flex-col sm:flex-row gap-3">
        <input
          type="search"
          value={search}
          onChange={e => setSearch(e.target.value)}
          placeholder="Search badges and criteria…"
          className="w-full sm:flex-1 rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-100"
        />
        <div className="flex gap-1 rounded-xl bg-gray-100 p-1 self-start" role="tablist" aria-label="Badge visibility">
          {(['all', 'earned', 'in-progress'] as const).map(view => (
            <button
              key={view}
              type="button"
              aria-pressed={visibility === view}
              onClick={() => setVisibility(view)}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold ${visibility === view ? 'bg-white shadow text-blue-700' : 'text-gray-500 hover:text-gray-800'}`}
            >
              {view === 'all' ? 'All' : view === 'earned' ? 'Earned' : 'In progress'}
            </button>
          ))}
        </div>
      </div>

      {rows.length === 0 ? (
        <div className="text-center py-16 text-gray-500 bg-white border border-gray-200 rounded-2xl">
          <span className="grid place-items-center w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-50 border border-slate-200">
            <Trophy size={28} strokeWidth={1.75} className="text-slate-400" aria-hidden="true" />
          </span>
          <p>
            {visibility === 'earned'
              ? 'No earned badges match this search yet. Keep up the great work!'
              : visibility === 'in-progress'
                ? 'Nothing in progress — every catalogue badge is already earned.'
                : 'No badges match this search yet. Keep up the great work!'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {rows.map(({ badge, earnedAt }) => (
            <AchievementBadgeCard
              key={`${badge.code}-${badge.badge_id}`}
              badge={badge}
              earnedAt={earnedAt}
              onOpen={() => setSelected({ badge, earnedAt })}
            />
          ))}
        </div>
      )}

      {progressError ? (
        <p className="text-xs text-slate-400" role="status">{progressError}</p>
      ) : null}

      <Modal
        open={selected !== null}
        title={selected?.badge.name ?? 'Badge details'}
        onClose={() => setSelected(null)}
      >
        {selected && <AchievementBadgeDetail badge={selected.badge} earnedAt={selected.earnedAt} />}
      </Modal>
    </div>
  )
}
