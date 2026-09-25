import * as dataQualityApi from '../../api/dataQuality/dataQualityApi'
import * as dataQualityIssueApi from '../../api/dataQuality/dataQualityIssueApi'
import * as dataQualityScanApi from '../../api/dataQuality/dataQualityScanApi'
import type { DataQualityFilterParams } from '../../types/dataQuality'

export const dataQualityService = {
  getDashboard: dataQualityApi.getDashboard,
  getStatistics: dataQualityApi.getStatistics,
  getTrends: dataQualityApi.getTrends,
  getExportUrl: dataQualityApi.getExportUrl,

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
