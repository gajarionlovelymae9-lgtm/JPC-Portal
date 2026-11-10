import { Link } from 'react-router-dom'
import type {
  EmployeeAnnualTarget,
  EmployeeTargetItem,
} from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, ProgressBar, formatDate } from './primitives'

/** Annual target headline plus the employee's individual targets. */
export default function TargetOverview({
  annual,
  items,
}: {
  annual: EmployeeAnnualTarget
  items: EmployeeTargetItem[]
}) {
  return (
    <Panel>
      <PanelHeader
        title="My Targets"
        description={annual.label}
        action={
          <Link
            to={PATHS.employeeTargets}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View All Targets →
          </Link>
        }
      />

      <div className="px-5 py-4 border-b border-gray-100">
        <div className="flex items-center justify-between gap-3 mb-1.5">
          <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
            Annual Progress
          </p>
          <p className="text-sm font-semibold text-gray-700 tabular-nums">
            {annual.completed} / {annual.target}
          </p>
        </div>
        <ProgressBar
          percent={annual.progress_percent}
          tone={
            annual.progress_percent !== null && annual.progress_percent >= 100 ? 'green' : 'blue'
          }
        />
      </div>

      {items.length === 0 ? (
        <EmptyState message="No targets assigned yet." icon="🎯" />
      ) : (
        <ul className="divide-y divide-gray-100">
          {items.map(target => (
            <li key={target.id} className="px-5 py-3">
              <div className="flex items-start justify-between gap-3 mb-1.5">
                <div className="min-w-0">
                  <p className="text-sm font-semibold text-gray-900 truncate">
                    {target.description ?? target.metric ?? 'Target'}
                  </p>
                  {target.metric && target.description ? (
                    <p className="text-xs text-gray-500 truncate">{target.metric}</p>
                  ) : null}
                </div>
                <p className="shrink-0 text-xs font-semibold text-gray-700 tabular-nums">
                  {target.actual_value} / {target.target_value}
                </p>
              </div>
              <ProgressBar
                percent={target.progress}
                tone={target.status === 'achieved' ? 'green' : 'blue'}
              />
              {target.end_date ? (
                <p className="text-xs text-gray-400 mt-1">Ends {formatDate(target.end_date)}</p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
