import type { ReactNode } from 'react'

interface ReportSectionProps {
  title: string
  hint?: string
  action?: ReactNode
  children: ReactNode
}

/** Consistent section shell used across the report pages. */
export function ReportSection({ title, hint, action, children }: ReportSectionProps) {
  return (
    <section className="task-section">
      <div className="dashboard-panel__head">
        <h3>{title}</h3>
        <div className="dashboard-panel__hint">
          {hint ? <span>{hint}</span> : null}
          {action}
        </div>
      </div>
      {children}
    </section>
  )
}