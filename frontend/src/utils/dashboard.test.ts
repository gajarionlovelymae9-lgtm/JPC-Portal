import { describe, expect, it } from 'vitest'
import {
  calendarMonthLabel,
  friendlyMonthLabel,
  summaryCards,
} from './dashboard'

describe('friendlyMonthLabel', () => {
  it('turns a YYYY-MM label into a friendly month', () => {
    const label = friendlyMonthLabel('2026-04')
    expect(label.toLowerCase()).toContain('april')
    expect(label).toContain('2026')
  })

  it('returns the input unchanged when it is not parseable', () => {
    expect(friendlyMonthLabel('nope')).toBe('nope')
  })
})

describe('calendarMonthLabel', () => {
  it('labels a year/month pair', () => {
    const label = calendarMonthLabel(2026, 2).toLowerCase()
    expect(label).toContain('february')
    expect(label).toContain('2026')
  })
})

describe('summaryCards', () => {
  it('maps the summary payload into KPI card items', () => {
    const cards = summaryCards({
      total_employees: 3,
      total_tasks: 10,
      completed_tasks: 8,
      pending_tasks: 1,
      overdue_tasks: 1,
      submitted_tasks: 0,
      under_review_tasks: 0,
      rejected_tasks: 0,
      cancelled_tasks: 0,
      on_time: 7,
      late: 1,
      completion_rate: 80,
      on_time_rate: 87.5,
    })

    expect(cards).toHaveLength(9)
    expect(cards[0]).toEqual({ label: 'Total tasks', value: 10, tone: 'default' })
    expect(cards[1]).toEqual({ label: 'Completion rate', value: '80%', tone: 'ok' })
    expect(cards[3]).toMatchObject({ label: 'Overdue', value: 1, tone: 'warning' })
  })
})