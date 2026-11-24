import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import type { EmployeeAchievementItem } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, formatLongDate } from './primitives'
import {
  ACHIEVEMENT_ICON_STROKE,
  ACHIEVEMENT_TONE_STYLES,
  resolveAchievementVisual,
} from '../achievements/AchievementBadge'

/** The dashboard widget only ever shows the latest few earned badges. */
const RECENT_LIMIT = 3

/** Tier chips reuse the JPC recognition palette; rendered only when a tier exists. */
const TIER_STYLES: Record<string, string> = {
  gold: 'bg-amber-100 text-amber-800 border-amber-200',
  silver: 'bg-slate-100 text-slate-700 border-slate-300',
  bronze: 'bg-orange-100 text-orange-800 border-orange-200',
  platinum: 'bg-cyan-100 text-cyan-800 border-cyan-200',
}

const FALLBACK_TIER = 'bg-gray-100 text-gray-600 border-gray-200'

function earnedTimestamp(value: string | null): number {
  if (!value) return 0
  const time = new Date(value).getTime()
  return Number.isNaN(time) ? 0 : time
}

/** Badges the employee has actually earned — never a preview of locked ones. */
export default function AchievementOverview({
  badges,
}: {
  badges: EmployeeAchievementItem[]
}) {
  // Newest first, then trimmed to the most recent few recognitions.
  const recent = useMemo(
    () =>
      [...badges]
        .sort((a, b) => earnedTimestamp(b.earned_at) - earnedTimestamp(a.earned_at))
        .slice(0, RECENT_LIMIT),
    [badges],
  )

  return (
    <Panel>
      <PanelHeader
        title="Recent Achievements"
        description="Your latest recognitions and accomplishments"
        action={
          <Link
            to={PATHS.employeeAchievements}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All →
          </Link>
        }
      />

      {recent.length === 0 ? (
        <EmptyState
          message="No achievements yet."
          hint="Keep working towards your goals!"
          icon="🏆"
        />
      ) : (
        <ul className="divide-y divide-gray-100">
          {recent.map(badge => {
            const { Icon, tone } = resolveAchievementVisual(badge)
            const styles = ACHIEVEMENT_TONE_STYLES[tone]
            const tier = badge.tier?.trim()
            const tierClass = tier
              ? TIER_STYLES[tier.toLowerCase()] ?? FALLBACK_TIER
              : null
            const earned = badge.earned_at ? new Date(badge.earned_at) : null
            const earnedLabel =
              earned && !Number.isNaN(earned.getTime()) ? formatLongDate(earned) : null

            return (
              <li key={badge.id} className="flex items-start gap-3 px-5 py-3.5">
                <span
                  aria-hidden="true"
                  className={`grid place-items-center w-12 h-12 shrink-0 rounded-xl border ${styles.pastel}`}
                >
                  <Icon
                    size={24}
                    strokeWidth={ACHIEVEMENT_ICON_STROKE}
                    className={styles.accent}
                  />
                </span>

                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    <p className="text-sm font-bold text-gray-900 truncate">{badge.name}</p>
                    {tier && tierClass ? (
                      <span
                        className={`shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border ${tierClass}`}
                      >
                        {tier}
                      </span>
                    ) : null}
                  </div>

                  {badge.description ? (
                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">
                      {badge.description}
                    </p>
                  ) : null}

                  {earnedLabel ? (
                    <p className="text-[11px] text-gray-400 mt-1">Earned {earnedLabel}</p>
                  ) : null}
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </Panel>
  )
}
