import { useNavigate } from 'react-router-dom'
import type { EmployeeActionRequired } from '../../services/employeePortalService'
import { employeeReportDetailPath, employeeReportEditPath, employeeTaskDetailPath } from '../../routes/paths'
import { Panel, PanelHeader } from './primitives'
/**
 * Resolve where an action-required item should take the employee. The backend
 * stays authoritative about *what* needs doing; this only maps the action to an
 * existing employee route.
 */
function actionTarget(item: EmployeeActionRequired): string {
  if (item.kind === 'task') {
    return employeeTaskDetailPath(item.id)
  }

  if (item.action === 'revise') {
    return `/employee/reports/${item.id}/revise`
  }

  if (item.action === 'edit') {
    return employeeReportEditPath(item.id)
  }

  return employeeReportDetailPath(item.id)
}

/**
 * Only rendered when there is something to do. Renders the "all caught up"
 * confirmation when the employee genuinely has no open work.
 */
export default function ActionRequired({
  items,
  caughtUp,
}: {
  items: EmployeeActionRequired[]
  caughtUp: boolean
}) {
  const navigate = useNavigate()

  if (items.length === 0) {
    if (!caughtUp) {
      return null
    }

    return (
      <Panel>
        <PanelHeader title="Action Required" />
        <div className="px-5 py-8 text-center">
          <div className="text-2xl mb-2" aria-hidden="true">✅</div>
          <p className="text-sm font-semibold text-gray-900">You&apos;re all caught up!</p>
          <p className="text-xs text-gray-500 mt-1">
            No revisions, unsubmitted reports or overdue tasks right now.
          </p>
        </div>
      </Panel>
    )
  }

  return (
    <Panel className="border-amber-200">
      <PanelHeader
        title="Action Required"
        description={`${items.length} item${items.length === 1 ? '' : 's'} need your attention`}
      />
      <ul className="divide-y divide-gray-100">
        {items.map(item => (
          <li
            key={`${item.kind}-${item.id}-${item.reason}`}
            className="px-5 py-3 flex items-start justify-between gap-3"
          >
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span
                  className={`w-1.5 h-1.5 rounded-full shrink-0 ${
                    item.severity === 'high' ? 'bg-red-500' : 'bg-amber-500'
                  }`}
                  aria-hidden="true"
                />
                <p className="text-sm font-semibold text-gray-900 truncate">{item.title}</p>
              </div>
              <p className="text-xs text-gray-600 mt-1 ml-3.5">{item.reason}</p>
            </div>
            <button
              type="button"
              onClick={() => navigate(actionTarget(item))}
              className="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 transition-colors"
            >
              {item.action_label}
            </button>
          </li>
        ))}
      </ul>
    </Panel>
  )
}
