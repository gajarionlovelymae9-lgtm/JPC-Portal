import { Link } from 'react-router-dom'
import type { EmployeeAchievementItem } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, formatDate } from './primitives'

/** Badges the employee has actually earned — never a preview of locked ones. */
export default function AchievementOverview({
  badges,
}: {
  badges: EmployeeAchievementItem[]
}) {
  return (
    <Panel>
      <PanelHeader
        title="Recent Achievements"
        action={
          <Link
            to={PATHS.employeeAchievements}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All →
          </Link>
        }
      />

      {badges.length === 0 ? (
        <EmptyState
          message="No achievements yet."
          hint="Keep completing tasks and reports to earn badges."
          icon="🏆"
        />
      ) : (
        <ul className="divide-y divide-gray-100">
          {badges.map(badge => (
            <li key={badge.id} className="px-5 py-3 flex items-start gap-3">
              <span className="text-xl shrink-0" aria-hidden="true">
                {badge.icon ?? '🏅'}
              </span>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-gray-900 truncate">{badge.name}</p>
                {badge.description ? (
                  <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{badge.description}</p>
                ) : null}
                {badge.earned_at ? (
                  <p className="text-xs text-gray-400 mt-1">
                    Earned {formatDate(badge.earned_at)}
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
