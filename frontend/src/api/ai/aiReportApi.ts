import { apiRequest } from '../../services/api'

export async function summarizeReport(reportId: string | number) {
  return apiRequest(`/ai/reports/${reportId}/summarize`, { method: 'POST' })
}
