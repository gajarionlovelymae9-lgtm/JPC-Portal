import React from 'react'
import type { EmployeeAiAlert, EmployeeAiRecommendation } from '../../../services/employeePortalService'

const SEVERITY: Record<string, string> = {
  high: 'bg-rose-50 border-rose-200 text-rose-800',
  medium: 'bg-amber-50 border-amber-200 text-amber-800',
  low: 'bg-blue-50 border-blue-200 text-blue-800',
}

interface AiAlertListProps {
  alerts: EmployeeAiAlert[]
}

/** Threshold alerts, each quoting the counts it was derived from. */
export const AiAlertList: React.FC<AiAlertListProps> = ({ alerts }) => {
  if (alerts.length === 0) {
    return (
      <section aria-label="Alerts" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Alerts</h2>
        <p className="mt-3 text-xs text-gray-600">
          No threshold alerts were triggered by your records for this period.
        </p>
      </section>
    )
  }

  return (
    <section aria-label="Alerts" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
      <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">
        Alerts <span className="text-gray-400">({alerts.length})</span>
      </h2>

      <ul className="mt-4 space-y-3">
        {alerts.map((alert) => (
          <li
            key={alert.key}
            data-testid={`alert-${alert.key}`}
            className={`rounded-xl border p-4 ${SEVERITY[alert.severity] ?? SEVERITY.low}`}
          >
            <div className="flex items-center justify-between gap-2">
              <p className="text-xs font-bold">{alert.title}</p>
              <span className="shrink-0 text-[10px] font-extrabold uppercase opacity-80">{alert.severity}</span>
            </div>
            <p className="mt-1 text-xs font-medium">{alert.detail}</p>
            <p className="mt-2 text-[10px] font-mono opacity-75">
              evidence: {JSON.stringify(alert.evidence)}
            </p>
          </li>
        ))}
      </ul>
    </section>
  )
}

interface AiRecommendationListProps {
  recommendations: EmployeeAiRecommendation[]
}

/** Advice tied to the weakest measured metric, with its measured value. */
export const AiRecommendationList: React.FC<AiRecommendationListProps> = ({ recommendations }) => (
  <section aria-label="Recommendations" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
    <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Recommendations</h2>

    {recommendations.length === 0 ? (
      <p className="mt-3 text-xs text-gray-600">
        No recommendations yet - there is not enough measured data to identify the metrics worth
        improving.
      </p>
    ) : (
      <ol className="mt-4 space-y-3">
        {recommendations.map((rec) => (
          <li key={rec.key} data-testid={`recommendation-${rec.key}`} className="rounded-xl border border-gray-100 bg-gray-50 p-4">
            <p className="text-xs font-bold text-gray-900">{rec.title}</p>
            <p className="mt-1 text-xs text-gray-600">{rec.detail}</p>
            <p className="mt-2 text-[10px] font-mono text-gray-500">
              based on: {JSON.stringify(rec.evidence)}
            </p>
          </li>
        ))}
      </ol>
    )}
  </section>
)
