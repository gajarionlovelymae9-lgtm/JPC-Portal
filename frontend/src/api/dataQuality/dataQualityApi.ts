import { apiRequest } from '../../services/api'
import type { DataQualityDashboardData } from '../../types/dataQuality'

export async function getDashboard(): Promise<{ data: DataQualityDashboardData }> {
  return apiRequest('/data-quality/dashboard')
}

export async function getStatistics(): Promise<any> {
  return apiRequest('/data-quality/statistics')
}

export async function getTrends(months = 6): Promise<{ data: { trends: any[] } }> {
  return apiRequest(`/data-quality/trends?months=${months}`)
}

export function getExportUrl(filters: Record<string, any> = {}): string {
  const query = new URLSearchParams(filters as any).toString()
  return `/data-quality/export?${query}`
}
