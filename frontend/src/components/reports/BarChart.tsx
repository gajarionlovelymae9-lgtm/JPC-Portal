interface BarChartProps {
  labels: string[]
  completed: number[]
  pending: number[]
  rates: number[]
  monthOverMonthPp: number
  title: string
}

const MAX_BARS = 12

function barHeight(value: number, max: number): number {
  if (max <= 0) {
    return 0
  }
  return Math.max(Math.round((value / max) * 100), value > 0 ? 4 : 0)
}

/** Lightweight CSS bar chart for the report chart feeds. */
export function BarChart({ labels, completed, pending, rates, monthOverMonthPp, title }: BarChartProps) {
  const visibleLabels = labels.slice(-MAX_BARS)
  const visibleCompleted = completed.slice(-MAX_BARS)
  const visiblePending = pending.slice(-MAX_BARS)
  const visibleRates = rates.slice(-MAX_BARS)

  const max = Math.max(...visibleCompleted, ...visiblePending, 1)

  return (
    <div className="dashboard-panel__sub">
      <div className="bar-chart" role="img" aria-label={title}>
        <div className="bar-chart__grid">
          {visibleLabels.map((label, index) => (
            <div className="bar-chart__column" key={label}>
              <div className="bar-chart__bars">
                <span
                  className="bar-chart__bar bar-chart__bar--completed"
                  style={{ height: `${barHeight(visibleCompleted[index] ?? 0, max)}%` }}
                />
                <span
                  className="bar-chart__bar bar-chart__bar--pending"
                  style={{ height: `${barHeight(visiblePending[index] ?? 0, max)}%` }}
                />
              </div>
              <span className="bar-chart__value">{visibleRates[index] ?? 0}%</span>
              <span className="bar-chart__label">{label}</span>
            </div>
          ))}
        </div>
        <div className="bar-chart__legend">
          <span className="bar-chart__legend-item bar-chart__legend-item--completed">Completed</span>
          <span className="bar-chart__legend-item bar-chart__legend-item--pending">Pending</span>
          <span className="bar-chart__legend-item">
            Month-over-month <strong>{monthOverMonthPp > 0 ? '+' : ''}{monthOverMonthPp}pp</strong>
          </span>
        </div>
      </div>
    </div>
  )
}