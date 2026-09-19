import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TaskTimeline } from './TaskTimeline'
import type { TaskTimelineItem } from '../../types/task'

const items: TaskTimelineItem[] = [
  {
    id: 'progress-1',
    title: '50% progress',
    subtitle: 'Progress update',
    description: 'Drafted the report',
    at: '2026-09-10T08:00:00+00:00',
    tone: 'info',
  },
  {
    id: 'review-1',
    title: 'Approved',
    subtitle: 'Review decision',
    at: '2026-09-12T08:00:00+00:00',
    tone: 'ok',
  },
]

describe('TaskTimeline', () => {
  it('renders each timeline entry with its details', () => {
    render(<TaskTimeline items={items} />)

    expect(screen.getByText('50% progress')).toBeInTheDocument()
    expect(screen.getByText('Drafted the report')).toBeInTheDocument()
    expect(screen.getByText('Approved')).toBeInTheDocument()
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
  })

  it('shows an empty message when there is no activity', () => {
    render(<TaskTimeline items={[]} emptyMessage="Nothing yet" />)
    expect(screen.getByText('Nothing yet')).toBeInTheDocument()
  })
})
