import React from 'react'
import type { EmployeeAiMetric } from '../../../services/employeePortalService'

interface AiMetricGridProps {
  metrics: EmployeeAiMetric[]
}

const BAR: Record<string, string> = {
  emerald: 'bg-emerald-500',
  blue: 'bg-blue-500',
  amber: 'bg-amber-500',
  orange: 'bg-orange-500',
  rose: 'bg-rose-500',
}

function toneFor(value: number): string {
  if (value >= 90) return 'emerald'
  if (value >= 80) return 'blue'
  if (value >= 70) return 'amber'
  if (value >= 60) return 'orange'
  return 'rose'
}

/**
 * Productivity metrics.
 *
 * A metric the backend could not measure renders as "No data" with its reason -
 * never as 0%, which would misrepresent missing records as a measured failure.
 */
export const AiMetricGrid: React.FC<AiMetricGridProps> = ({ metrics }) => (
  <section aria-label="Productivity metrics" className="space-y-4">
    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
      {metrics.map((metric) => {
        const isScore = metric.key === 'productivity_score'
        const bar = metric.value === null ? 'bg-gray-300' : BAR[toneFor(metric.value)] ?? 'bg-blue-500'
        const width = metric.value === null ? 0 : Math.max(2, Math.min(100, metric.value))

        return (
          <article
            key={metric.key}
            data-testid={`metric-${metric.key}`}
            className="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm"
          >
            <div className="flex items-start justify-between gap-2">
              <h3 className="text-xs font-bold text-gray-900 uppercase tracking-wide">{metric.label}</h3>
              {metric.available ? (
                <span className="shrink-0 text-[10px] font-bold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">
                  {isScore ? metric.unit : `weight ${metric.weight}%`}
                </span>
              ) : (
                <span className="shrink-0 text-[10px] font-bold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">
                  No data
                </span>
              )}
            </div>

            {metric.available && metric.value !== null ? (
              <>
                <p className="mt-3 text-3xl font-extrabold tabular-nums text-gray-900">
                  {metric.value.toFixed(1)}
                  {metric.unit === '%' ? '%' : ''}
                </p>
                <div className="mt-3 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                  <div className={`h-full rounded-full ${bar}`} style={{ width: `${width}%` }} />
                </div>
              </>
            ) : (
              <>
                <p className="mt-3 text-3xl font-extrabold text-gray-400">&mdash;</p>
                <p className="mt-2 text-[11px] text-gray-500">{metric.unavailable_reason}</p>
              </>
            )}

            <p className="mt-3 text-[11px] text-gray-500">{metric.basis}</p>
          </article>
        )
      })}
    </div>
  </section>
)
