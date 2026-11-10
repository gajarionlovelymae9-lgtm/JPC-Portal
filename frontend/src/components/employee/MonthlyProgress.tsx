import type { EmployeeMonthlyProgress } from '../../services/employeePortalService'
import { Panel, PanelHeader, ProgressBar } from './primitives'

function Stat({ label, value, tone }: { label: string; value: number; tone?: 'default' | 'success' | 'warning' }) {
  const valueClass =
    tone === 'success' ? 'text-green-700' : tone === 'warning' ? 'text-amber-700' : 'text-gray-900'

  return (
    <div className="text-center">
      <p className={`text-xl font-bold tabular-nums ${valueClass}`}>{value}</p>
      <p className="text-xs text-gray-500 mt-0.5">{label}</p>
    </div>
  )
}

/**
 * Single-month progress. The backend scopes this to the requested month, so the
 * panel never mixes data from other periods.
 */
export default function MonthlyProgress({ progress }: { progress: EmployeeMonthlyProgress }) {
  return (
    <Panel>
      <PanelHeader title="Monthly Progress" description={progress.label} />

      <div className="px-5 py-4">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <Stat label="Tasks Assigned" value={progress.tasks_assigned} />
          <Stat label="Tasks Completed" value={progress.tasks_completed} tone="success" />
          <Stat label="Reports Submitted" value={progress.reports_submitted} />
          <Stat
            label="Late Submissions"
            value={progress.late_submissions}
            tone={progress.late_submissions > 0 ? 'warning' : 'default'}
          />
        </div>

        <div className="mt-4 pt-4 border-t border-gray-100">
          <div className="flex items-center justify-between gap-3 mb-1.5">
            <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
              Task Completion
            </p>
            <p className="text-xs text-gray-500 tabular-nums">
              {progress.tasks_completed} of {progress.tasks_assigned}
            </p>
          </div>
          <ProgressBar
            percent={progress.completion_rate}
            tone={progress.completion_rate !== null && progress.completion_rate >= 100 ? 'green' : 'blue'}
          />
        </div>
      </div>
    </Panel>
  )
}
