import { apiRequest } from '../../services/api'
import type { DataQualityFilterParams, DataQualityIssue } from '../../types/dataQuality'

/**
 * Data-quality issue actions.
 *
 * Bodies are passed as plain objects, not pre-stringified JSON: `apiRequest`
 * serialises them itself, so calling `JSON.stringify` here would send a quoted,
 * double-encoded string that the API cannot read.
 */

export async function getIssues(params: DataQualityFilterParams = {}): Promise<{ data: DataQualityIssue[]; meta?: any }> {
  const query = new URLSearchParams(params as any).toString()
  return apiRequest(`/data-quality/issues?${query}`)
}

export async function getIssue(id: number | string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/data-quality/issues/${id}`)
}

export async function reviewIssue(id: number | string, notes?: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/data-quality/issues/${id}/review`, {
    method: 'POST',
    body: { notes },
  })
}

export async function confirmIssue(id: number | string, notes?: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/data-quality/issues/${id}/confirm`, {
    method: 'POST',
    body: { notes },
  })
}

export async function dismissIssue(id: number | string, reason: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/data-quality/issues/${id}/dismiss`, {
    method: 'POST',
    body: { reason },
  })
}

export async function resolveIssue(id: number | string, notes: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/data-quality/issues/${id}/resolve`, {
    method: 'POST',
    body: { notes },
  })
}
