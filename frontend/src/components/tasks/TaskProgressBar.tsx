import { clampPercent } from '../../utils/task'

interface TaskProgressBarProps {
  value: number | string | null | undefined
  label?: string
  showValue?: boolean
  size?: 'sm' | 'md'
}

/** Accessible progress bar; percentage is clamped to 0–100 for display. */
export function TaskProgressBar({
  value,
  label,
  showValue = true,
  size = 'md',
}: TaskProgressBarProps) {
  const percent = clampPercent(value)

  return (
    <div className={`task-progress task-progress--${size}`}>
      {label || showValue ? (
        <div className="task-progress__meta">
          <span>{label ?? ''}</span>
          {showValue ? <span className="task-progress__value">{percent}%</span> : null}
        </div>
      ) : null}
      <div
        className="task-progress__track"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={label ?? 'Task progress'}
      >
        <div className="task-progress__fill" style={{ width: `${percent}%` }} />
      </div>
    </div>
  )
}
