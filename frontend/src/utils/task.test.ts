import { describe, expect, it } from 'vitest'
import { clampPercent, deadlineLabel, decisionLabel, daysUntil, priorityLabel, statusLabel } from './task'

function inDays(offset: number): string {
  const date = new Date()
  date.setHours(12, 0, 0, 0)
  date.setDate(date.getDate() + offset)
  return date.toISOString()
}

describe('deadlineLabel', () => {
  it('labels future deadlines', () => {
    expect(deadlineLabel(inDays(5))).toBe('Due in 5 days')
  })

  it('handles tomorrow and today', () => {
    expect(deadlineLabel(inDays(1))).toBe('Due tomorrow')
    expect(deadlineLabel(inDays(0))).toBe('Due today')
  })

  it('labels overdue deadlines', () => {
    expect(deadlineLabel(inDays(-3))).toBe('Overdue by 3 days')
    expect(deadlineLabel(inDays(-1))).toBe('Overdue by 1 day')
  })

  it('handles missing dates', () => {
    expect(deadlineLabel(null)).toBe('No due date')
  })
})

describe('daysUntil', () => {
  it('returns null for invalid input', () => {
    expect(daysUntil('not-a-date')).toBeNull()
  })
})

describe('presentation helpers', () => {
  it('maps known labels and falls back to title case', () => {
    expect(statusLabel('under_review')).toBe('Under Review')
    expect(statusLabel('something_new')).toBe('Something New')
    expect(priorityLabel('urgent')).toBe('Urgent')
    expect(decisionLabel('needs_revision')).toBe('Needs Revision')
  })

  it('clamps percentages to 0-100', () => {
    expect(clampPercent(150)).toBe(100)
    expect(clampPercent(-5)).toBe(0)
    expect(clampPercent('65')).toBe(65)
    expect(clampPercent(null)).toBe(0)
  })
})
