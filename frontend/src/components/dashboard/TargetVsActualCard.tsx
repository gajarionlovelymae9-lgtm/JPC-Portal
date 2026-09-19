import { clampPercent } from '../../utils/task'
import type { TargetVsActual } from '../../types/dashboard'

interface TargetVsActualCardProps {
  data: TargetVsActual | null
}

/** Target attainment snapshot: targets planned vs achieved, with the rate bar. */
export function TargetVsActualCard({ data }: TargetVsActualCardProps) {
  if (!data) {
    return null
  }

  const width = clampPercent(data.achievement_rate)

  return (
    <section className="task-section" aria-label="Target vs actual">
      <div className="dashboard-panel__head">
        <h3>Target vs actual</h3>
        <span className="dashboard-panel__hint">{data.period}</span>
      </div>

      <div className="task-stats">
        <div className="task-stat">
          <span className="task-stat__value">{data.target}</span>
          <span className="task-stat__label">Targets</span>
        </div>
        <div className="task-stat task-stat--ok">
          <span className="task-stat__value">{data.achieved}</span>
          <span className="task-stat__label">Achieved</span>
        </div>
        <div className="task-stat task-stat--warning">
          <span className="task-stat__value">{data.remaining}</span>
          <span className="task-stat__label">Remaining</span>
        </div>
        <div className="task-stat task-stat--info">
          <span className="task-stat__value">{data.achievement_rate}%</span>
          <span className="task-stat__label">Achievement</span>
        </div>
      </div>

      <div className="task-chart" role="img" aria-label={`${width}% of employee targets achieved`}>
        <ul className="task-chart__list">
          <li className="task-chart__row">
            <span className="task-chart__label">Achieved</span>
            <span className="task-chart__track">
              <span className="task-chart__fill task-chart__fill--ok" style={{ width: `${width}%` }} />
            </span>
            <span className="task-chart__value">
              {data.achieved}/{data.target}
            </span>
          </li>
        </ul>
      </div>
    </section>
  )
}