import type { ReactNode } from 'react'

interface ReportingIntroProps {
  title: string
  children?: ReactNode
}

/** Consistent page header for the Phase 9 reporting area. */
export function ReportingIntro({ title, children }: ReportingIntroProps) {
  return (
    <div className="page__intro">
      <h2>{title}</h2>
      {children ? <p>{children}</p> : null}
    </div>
  )
}