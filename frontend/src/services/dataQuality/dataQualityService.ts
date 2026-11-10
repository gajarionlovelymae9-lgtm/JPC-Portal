import * as dataQualityApi from '../../api/dataQuality/dataQualityApi'
import * as dataQualityIssueApi from '../../api/dataQuality/dataQualityIssueApi'
import * as dataQualityScanApi from '../../api/dataQuality/dataQualityScanApi'
import { API_BASE_URL, ApiError } from '../api'
import type { DataQualityFilterParams } from '../../types/dataQuality'

export const dataQualityService = {
  getDashboard: dataQualityApi.getDashboard,
  getStatistics: dataQualityApi.getStatistics,
  getTrends: dataQualityApi.getTrends,
  getExportUrl: dataQualityApi.getExportUrl,

  /**
   * Stream the CSV export through an authenticated fetch: a plain
   * `window.location` navigation bypasses API_BASE_URL and the session
   * cookie headers are not applied the same way, producing 401/404.
   */
  async downloadExport(filters: Record<string, unknown> = {}): Promise<void> {
    const params = new URLSearchParams()
    for (const [key, value] of Object.entries(filters)) {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value))
      }
    }
    const query = params.toString()

    const response = await fetch(`${API_BASE_URL}/data-quality/export${query ? `?${query}` : ''}`, {
      method: 'GET',
      headers: {
        Accept: 'text/csv,application/octet-stream',
      },
      credentials: 'include',
    })

    if (!response.ok) {
      const payload = await response.json().catch(() => null)
      throw new ApiError(
        typeof payload?.message === 'string' ? payload.message : `Export failed with status ${response.status}`,
        response.status,
        payload?.errors,
      )
    }

    let filename = 'data-quality-issues.csv'
    const disposition = response.headers.get('Content-Disposition')
    const match = disposition?.match(/filename="?([^";]+)"?/)
    if (match) filename = match[1]

    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  },

  getIssues: (params?: DataQualityFilterParams) => dataQualityIssueApi.getIssues(params),
  getIssue: (id: number | string) => dataQualityIssueApi.getIssue(id),
  reviewIssue: (id: number | string, notes?: string) => dataQualityIssueApi.reviewIssue(id, notes),
  confirmIssue: (id: number | string, notes?: string) => dataQualityIssueApi.confirmIssue(id, notes),
  dismissIssue: (id: number | string, reason: string) => dataQualityIssueApi.dismissIssue(id, reason),
  resolveIssue: (id: number | string, notes: string) => dataQualityIssueApi.resolveIssue(id, notes),

  getScans: (page?: number, perPage?: number) => dataQualityScanApi.getScans(page, perPage),
  getScan: (id: number | string) => dataQualityScanApi.getScan(id),
  triggerScan: (data?: { scan_type?: string; department_id?: number; employee_id?: number }) => dataQualityScanApi.triggerScan(data),
}
