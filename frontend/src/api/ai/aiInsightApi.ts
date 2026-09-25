import { apiRequest } from '../../services/api'

export async function getInsights() {
  return apiRequest('/ai/insights', { method: 'GET' })
}

export async function getPerformanceInsights() {
  return apiRequest('/ai/insights/performance', { method: 'GET' })
}

export async function getReportInsights() {
  return apiRequest('/ai/insights/reports', { method: 'GET' })
}
