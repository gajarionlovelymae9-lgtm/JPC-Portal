import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAllReportPages, toReportFilters } from './hubShared'
import type { ReportingFilters } from '../../../types/reporting'

const base: ReportingFilters = { period_type: 'monthly', year: 2026, month: 10 }

describe('toReportFilters', () => {
  it('matches the server per_page cap of 100 (ReportRequest max:100)', () => {
    // Sending per_page above 100 made every hub tab 422 with
    // "The per page field must not be greater than 100."
    expect(toReportFilters(base).per_page).toBeLessThanOrEqual(100)
  })

  it('maps hub period filters onto the wire fields', () => {
    const filters = toReportFilters(base)
    expect(filters.year).toBe(2026)
    expect(filters.month).toBe(10)
    expect(filters.page).toBe(1)
  })
})

describe('fetchAllReportPages', () => {
  beforeEach(vi.restoreAllMocks)

  it('merges every page below last_page into a single response', async () => {
    const rows = [...Array.from({ length: 250 }, (_, i) => ({ id: i + 1, name: `E${i + 1}` }))]
    const query = vi.fn()
      .mockResolvedValueOnce({ data: rows.slice(0, 100), meta: { pagination: { last_page: 3 } } })
      .mockResolvedValueOnce({ data: rows.slice(100, 200), meta: { pagination: { last_page: 3 } } })
      .mockResolvedValueOnce({ data: rows.slice(200), meta: { pagination: { last_page: 3 } } })

    const merged = await fetchAllReportPages(query, base)

    expect(query).toHaveBeenCalledTimes(3)
    expect(query.mock.calls[0][0].page).toBe(1)
    expect(query.mock.calls[1][0].page).toBe(2)
    expect(query.mock.calls[2][0].page).toBe(3)
    expect(query.mock.calls.every(([f]) => f.per_page === 100)).toBe(true)
    expect(merged.data).toHaveLength(250)
  })

  it('makes one request when the dataset fits on one page', async () => {
    const query = vi.fn()
      .mockResolvedValue({ data: [{ id: 1 }], meta: { pagination: { last_page: 1 } } })

    const merged = await fetchAllReportPages(query, base)

    expect(query).toHaveBeenCalledTimes(1)
    expect(merged.data).toHaveLength(1)
  })

  it('survives a missing pagination meta by returning page one', async () => {
    const query = vi.fn().mockResolvedValue({ data: [{ id: 1 }] })

    const merged = await fetchAllReportPages(query, base)

    expect(query).toHaveBeenCalledTimes(1)
    expect(merged.data).toHaveLength(1)
  })
})
