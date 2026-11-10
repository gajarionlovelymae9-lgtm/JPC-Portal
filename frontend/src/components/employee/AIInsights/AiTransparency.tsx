import React from 'react'
import type {
  EmployeeAiForecast,
  EmployeeAiScoreTransparency,
} from '../../../services/employeePortalService'

interface AiForecastCardProps {
  forecast: EmployeeAiForecast
}

/**
 * Projection panel.
 *
 * With too little history there is no number at all - the card states the
 * requirement, how many months exist and what would make it possible. When a
 * projection is offered it is always labelled with its method and confidence.
 */
export const AiForecastCard: React.FC<AiForecastCardProps> = ({ forecast }) => (
  <section aria-label="Performance projection" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm h-full">
    <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Performance Projection</h2>

    {forecast.value === null ? (
      <div className="mt-4">
        <p className="text-3xl font-extrabold text-gray-400">&mdash;</p>
        <p className="mt-2 text-xs font-semibold text-gray-700">Projection unavailable</p>
        <p className="mt-2 text-xs text-gray-600">{forecast.reason}</p>
        {forecast.needed && (
          <p className="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">
            <span className="font-bold text-gray-700">What would help: </span>
            {forecast.needed}
          </p>
        )}
        <p className="mt-3 text-[11px] text-gray-500">
          Months measured: {forecast.based_on_months} of {forecast.required_months} required
        </p>
      </div>
    ) : (
      <div className="mt-4">
        <p className="text-3xl font-extrabold tabular-nums text-gray-900">{forecast.value.toFixed(2)}</p>
        <p className="mt-1 text-xs font-semibold text-gray-700">
          next-period estimate &middot; {forecast.confidence} confidence
        </p>
        <dl className="mt-3 space-y-1 text-xs text-gray-600">
          <div className="flex justify-between">
            <dt>Method</dt>
            <dd className="font-mono">{forecast.method}</dd>
          </div>
          <div className="flex justify-between">
            <dt>Direction</dt>
            <dd className="font-bold capitalize">{forecast.direction}</dd>
          </div>
          <div className="flex justify-between">
            <dt>Months measured</dt>
            <dd className="font-bold">{forecast.based_on_months}</dd>
          </div>
        </dl>
        <p className="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900">
          {forecast.reason}
        </p>
      </div>
    )}
  </section>
)

interface AiTransparencyPanelProps {
  transparency: EmployeeAiScoreTransparency
}

/**
 * Shows exactly how the score was produced: the configured weight, the
 * renormalised weight that was actually applied, and the resulting contribution
 * for every metric - including the ones dropped for lack of data.
 */
export const AiTransparencyPanel: React.FC<AiTransparencyPanelProps> = ({ transparency }) => (
  <section aria-label="How this score was calculated" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
    <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">How This Score Was Calculated</h2>
    <p className="mt-2 text-xs text-gray-600">{transparency.formula}</p>
    <p className="mt-1 text-[11px] font-mono text-gray-500">
      weights: {transparency.weights_source} &middot; version {transparency.calculation_version ?? '—'} &middot;{' '}
      {transparency.metrics_used} of {transparency.metrics_used + transparency.metrics_excluded} metrics included
    </p>

    <div className="mt-4 overflow-x-auto">
      <table className="w-full text-xs">
        <thead>
          <tr className="text-left text-[10px] uppercase tracking-wider text-gray-500 border-b border-gray-200">
            <th className="py-2 pr-3 font-bold">Metric</th>
            <th className="py-2 pr-3 font-bold">Measured</th>
            <th className="py-2 pr-3 font-bold">Configured</th>
            <th className="py-2 pr-3 font-bold">Applied</th>
            <th className="py-2 font-bold">Contribution</th>
          </tr>
        </thead>
        <tbody>
          {transparency.metrics.map((metric) => (
            <tr
              key={metric.key}
              data-testid={`transparency-${metric.key}`}
              className="border-b border-gray-100 last:border-0 align-top"
            >
              <td className="py-2 pr-3 font-semibold text-gray-800">
                {metric.label}
                {!metric.available && (
                  <span className="block text-[10px] font-medium text-gray-500">{metric.excluded_reason}</span>
                )}
              </td>
              <td className="py-2 pr-3 tabular-nums text-gray-700">
                {metric.available && metric.raw_value !== null ? `${metric.raw_value.toFixed(2)}%` : 'No data'}
              </td>
              <td className="py-2 pr-3 tabular-nums text-gray-500">{metric.configured_weight.toFixed(2)}%</td>
              <td className="py-2 pr-3 tabular-nums text-gray-700">
                {metric.applied_weight !== null ? `${metric.applied_weight.toFixed(2)}%` : '—'}
              </td>
              <td className="py-2 tabular-nums font-bold text-gray-900">
                {metric.contribution !== null ? metric.contribution.toFixed(2) : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  </section>
)
