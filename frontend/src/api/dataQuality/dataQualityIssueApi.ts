import { apiRequest } from '../../services/api'
import type { DataQualityFilterParams, DataQualityIssue } from '../../types/dataQuality'

export async function getIssues(params: DataQualityFilterParams = {}): Promise<{ data: DataQualityIssue[]; meta?: any }> {
  const query = new URLSearchParams(params as any).toString()
  return apiRequest(`/api/v1/data-quality/issues?${query}`)
}

export async function getIssue(id: number | string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/api/v1/data-quality/issues/${id}`)
}

export async function reviewIssue(id: number | string, notes?: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/api/v1/data-quality/issues/${id}/review`, {
    method: 'POST',
    body: JSON.stringify({ notes }),
  })
}

export async function confirmIssue(id: number | string, notes?: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/api/v1/data-quality/issues/${id}/confirm`, {
    method: 'POST',
    body: JSON.stringify({ notes }),
  })
}

export async function dismissIssue(id: number | string, reason: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/api/v1/data-quality/issues/${id}/dismiss`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  })
}

export async function resolveIssue(id: number | string, notes: string): Promise<{ data: DataQualityIssue }> {
  return apiRequest(`/api/v1/data-quality/issues/${id}/resolve`, {
    method: 'POST',
    body: JSON.stringify({ notes }),
  })
}
