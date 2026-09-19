import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TaskPriorityBadge } from './TaskPriorityBadge'
import { TaskProgressBar } from './TaskProgressBar'
import { TaskStatusBadge } from './TaskStatusBadge'

describe('TaskStatusBadge', () => {
  it('renders a human readable label for the status', () => {
    render(<TaskStatusBadge status="under_review" />)
    expect(screen.getByText('Under Review')).toBeInTheDocument()
  })

  it('applies a status modifier class', () => {
    render(<TaskStatusBadge status="in_progress" />)
    expect(screen.getByText('In Progress')).toHaveClass('task-badge--in-progress')
  })
})

describe('TaskPriorityBadge', () => {
  it('renders the priority label', () => {
    render(<TaskPriorityBadge priority="urgent" />)
    expect(screen.getByText('Urgent')).toHaveClass('task-badge--priority', 'task-badge--urgent')
  })
})

describe('TaskProgressBar', () => {
  it('exposes the percentage to assistive technology', () => {
    render(<TaskProgressBar value={42} label="Progress" />)

    const bar = screen.getByRole('progressbar')
    expect(bar).toHaveAttribute('aria-valuenow', '42')
    expect(screen.getByText('42%')).toBeInTheDocument()
  })

  it('clamps out-of-range values', () => {
    render(<TaskProgressBar value={150} />)
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '100')
  })

  it('treats missing values as zero', () => {
    render(<TaskProgressBar value={null} />)
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '0')
  })
})
