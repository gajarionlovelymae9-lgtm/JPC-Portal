import { apiRequest } from '../../services/api'
import type { DataQualityDashboardData } from '../../types/dataQuality'

export async function getDashboard(): Promise<{ data: DataQualityDashboardData }> {
  return apiRequest('/api/v1/data-quality/dashboard')
}

export async function getStatistics(): Promise<any> {
  return apiRequest('/api/v1/data-quality/statistics')
}

export async function getTrends(months = 6): Promise<{ data: { trends: any[] } }> {
  return apiRequest(`/api/v1/data-quality/trends?months=${months}`)
}

export function getExportUrl(filters: Record<string, any> = {}): string {
  const query = new URLSearchParams(filters as any).toString()
  return `/api/v1/data-quality/export?${query}`
}
