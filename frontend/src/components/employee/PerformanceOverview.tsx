import { Link } from 'react-router-dom'
import type { EmployeePerformancePayload } from '../../services/employeePortalService'
import { PATHS } from '../../routes/paths'
import { EmptyState, Panel, PanelHeader, ProgressBar, formatPercent } from './primitives'

function toneFor(value: number | null): 'blue' | 'green' | 'amber' {
  if (value === null) return 'blue'
  if (value >= 100) return 'green'
  if (value >= 80) return 'blue'
  return 'amber'
}

/**
 * Performance metrics only. The backend may return an AI-generated score plus a
 * disclaimer; when no score exists the section says so instead of inventing one.
 */
export default function PerformanceOverview({
  performance,
}: {
  performance: EmployeePerformancePayload
}) {
  const entries = Object.entries(performance.metrics ?? {})

  return (
    <Panel>
      <PanelHeader
        title="Performance"
        action={
          <Link
            to={PATHS.employeePerformance}
            className="text-xs font-semibold text-blue-600 hover:underline whitespace-nowrap"
          >
            View Full Report →
          </Link>
        }
      />

      {entries.length === 0 ? (
        <EmptyState
          message="No performance metrics yet."
          hint="Metrics appear once you have completed work."
          icon="📈"
        />
      ) : (
        <>
          <ul className="divide-y divide-gray-100">
            {entries.map(([key, metric]) => (
              <li key={key} className="px-5 py-3">
                <div className="flex items-center justify-between gap-3 mb-1.5">
                  <p className="text-sm font-medium text-gray-700">{metric.label}</p>
                  <p className="text-sm font-semibold text-gray-900 tabular-nums">
                    {formatPercent(metric.value)}
                  </p>
                </div>
                <ProgressBar percent={metric.value} tone={toneFor(metric.value)} />
              </li>
            ))}
          </ul>

          {performance.disclaimer ? (
            <p className="px-5 py-3 text-xs text-gray-500 border-t border-gray-100 bg-gray-50 rounded-b-xl">
              {performance.disclaimer}
            </p>
          ) : null}
        </>
      )}
    </Panel>
  )
}
