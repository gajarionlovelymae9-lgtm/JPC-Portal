import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { KpiGrid } from './KpiGrid'
import { MonthlyProgressCard } from './MonthlyProgressCard'
import { MonthlyAchievementPanel } from './MonthlyAchievementPanel'
import { TargetVsActualCard } from './TargetVsActualCard'
import type { DashboardSummary, MonthlyProgress, TargetVsActual } from '../../types/dashboard'

const summary: DashboardSummary = {
  total_employees: 20,
  total_tasks: 50,
  completed_tasks: 40,
  pending_tasks: 5,
  overdue_tasks: 3,
  submitted_tasks: 1,
  under_review_tasks: 1,
  rejected_tasks: 2,
  cancelled_tasks: 1,
  on_time: 38,
  late: 2,
  completion_rate: 80,
  on_time_rate: 95,
}

const target: TargetVsActual = {
  target: 10,
  achieved: 6,
  remaining: 4,
  achievement_rate: 60,
  period: '2025-2026',
}

const monthly: MonthlyProgress = {
  year: 2026,
  month: 2,
  total: 20,
  completed: 12,
  pending: 4,
  overdue: 2,
  on_time: 10,
  late: 2,
  submitted: 1,
  under_review: 1,
  completion_rate: 60,
}

describe('KpiGrid', () => {
  it('renders cards from the summary payload', () => {
    render(<KpiGrid summary={summary} />)

    expect(screen.getByText('Total tasks')).toBeInTheDocument()
    expect(screen.getByText('Completion rate')).toBeInTheDocument()
    expect(screen.getByText('80%')).toBeInTheDocument()
    expect(screen.getByText('Overdue')).toBeInTheDocument()
    expect(screen.getByText('Rejected')).toBeInTheDocument()
  })

  it('renders nothing without a summary', () => {
    const { container } = render(<KpiGrid summary={null} />)
    expect(container).toBeEmptyDOMElement()
  })
})

describe('TargetVsActualCard', () => {
  it('renders the target snapshot with its period', () => {
    render(<TargetVsActualCard data={target} />)

    expect(screen.getByText('Target vs actual')).toBeInTheDocument()
    expect(screen.getByText('2025-2026')).toBeInTheDocument()
    expect(screen.getByText('6/10')).toBeInTheDocument()
    expect(screen.getByText('60%')).toBeInTheDocument()
  })
})

describe('MonthlyProgressCard', () => {
  it('renders monthly counts and a status breakdown', () => {
    render(<MonthlyProgressCard data={monthly} />)

    expect(screen.getByText('Monthly progress')).toBeInTheDocument()
    expect(screen.getByText('Tasks created')).toBeInTheDocument()
    expect(screen.getByText('Status breakdown')).toBeInTheDocument()
    expect(screen.getByText('Completed on time')).toBeInTheDocument()
    expect(screen.getAllByText('Overdue').length).toBeGreaterThan(0)
  })
})

describe('MonthlyAchievementPanel', () => {
  it('shows a loading message while loading', () => {
    render(<MonthlyAchievementPanel rows={[]} loading error={null} onRetry={vi.fn()} />)
    expect(screen.getByText('Loading achievement ranking…')).toBeInTheDocument()
  })

  it('shows an empty state when there is no completed work', () => {
    render(
      <MonthlyAchievementPanel rows={[]} loading={false} error={null} onRetry={vi.fn()} />,
    )
    expect(screen.getByText('No completed work yet for the selected period.')).toBeInTheDocument()
  })

  it('shows a retry button when an error occurs', () => {
    render(
      <MonthlyAchievementPanel rows={[]} loading={false} error="Boom" onRetry={vi.fn()} />,
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('renders the ranked leaderboard', () => {
    render(
      <MonthlyAchievementPanel
        rows={[
          {
            rank: 1,
            employee_id: 1,
            employee_name: 'Maria Santos',
            department_id: 2,
            department_name: 'IT',
            completed_tasks: 5,
            completion_rate: 100,
          },
          {
            rank: 2,
            employee_id: 2,
            employee_name: 'John Cruz',
            department_id: 3,
            department_name: 'HR',
            completed_tasks: 4,
            completion_rate: 50,
          },
        ]}
        loading={false}
        error={null}
        onRetry={vi.fn()}
      />,
    )

    expect(screen.getByText('#1')).toBeInTheDocument()
    expect(screen.getByText('Maria Santos')).toBeInTheDocument()
    expect(screen.getByText('John Cruz')).toBeInTheDocument()
    expect(screen.getByText('100%')).toBeInTheDocument()
  })
})