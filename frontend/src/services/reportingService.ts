import { ApiError, apiRequest, API_BASE_URL, toQueryString } from './api'
import type {
  AchievementReport,
  ComparisonResult,
  ComparisonType,
  CompletionTrendPoint,
  DataQualityReport,
  DepartmentComparisonRow,
  DepartmentReport,
  EmployeeComparisonRow,
  EmployeeReport,
  InstitutionReport,
  LateReport,
  ManagementSummary,
  MonthlyReport,
  PendingReport,
  PerformanceDistribution,
  PerformanceTrendPoint,
  PeriodComparison,
  RankingRow,
  ReportingFilters,
  ReportingResponse,
  ReportExport,
  ReportExportTypeValue,
  SubmissionTrendPoint,
  TargetReport,
} from '../types/reporting'

const REPORTS_BASE = '/reports'
const ANALYTICS_BASE = '/analytics'
const EXPORTS_BASE = '/reports/exports'

const reportingRequest = <T>(path: string) =>
  apiRequest<T>(path) as Promise<ReportingResponse<T>>

/** Phase 9 — ReportingService endpoints (spec items 7-15). */
export function getEmployeeReport(filters: ReportingFilters = {}): Promise<ReportingResponse<EmployeeReport>> {
  return reportingRequest<EmployeeReport>(`${REPORTS_BASE}/employee${toQueryString({ ...filters })}`)
}

export function getDepartmentReport(filters: ReportingFilters = {}): Promise<ReportingResponse<DepartmentReport>> {
  return reportingRequest<DepartmentReport>(`${REPORTS_BASE}/department${toQueryString({ ...filters })}`)
}

export function getInstitutionReport(filters: ReportingFilters = {}): Promise<ReportingResponse<InstitutionReport>> {
  return reportingRequest<InstitutionReport>(`${REPORTS_BASE}/institution${toQueryString({ ...filters })}`)
}

export function getMonthlyReport(year: number, month: number): Promise<ReportingResponse<MonthlyReport>> {
  return reportingRequest<MonthlyReport>(`${REPORTS_BASE}/monthly${toQueryString({ year, month })}`)
}

export function getAchievementReport(filters: ReportingFilters = {}): Promise<ReportingResponse<AchievementReport>> {
  return reportingRequest<AchievementReport>(`${REPORTS_BASE}/achievements${toQueryString({ ...filters })}`)
}

export function getLateReport(filters: ReportingFilters = {}): Promise<ReportingResponse<LateReport>> {
  return reportingRequest<LateReport>(`${REPORTS_BASE}/late${toQueryString({ ...filters })}`)
}

export function getPendingReport(filters: ReportingFilters = {}): Promise<ReportingResponse<PendingReport>> {
  return reportingRequest<PendingReport>(`${REPORTS_BASE}/pending${toQueryString({ ...filters })}`)
}

export function getTargetReport(filters: ReportingFilters = {}): Promise<ReportingResponse<TargetReport>> {
  return reportingRequest<TargetReport>(`${REPORTS_BASE}/targets${toQueryString({ ...filters })}`)
}

export function getDataQualityReport(filters: ReportingFilters = {}): Promise<ReportingResponse<DataQualityReport>> {
  return reportingRequest<DataQualityReport>(`${REPORTS_BASE}/data-quality${toQueryString({ ...filters })}`)
}

export function getManagementSummary(year: number, month: number): Promise<ReportingResponse<ManagementSummary>> {
  return reportingRequest<ManagementSummary>(`${REPORTS_BASE}/management-summary${toQueryString({ year, month })}`)
}

/** Phase 9 — AnalyticsService endpoints (spec items 14, 16-18). */
export function getPerformanceTrend(
  months = 6,
  filters: ReportingFilters = {},
): Promise<ReportingResponse<PerformanceTrendPoint[]>> {
  return reportingRequest<PerformanceTrendPoint[]>(`${ANALYTICS_BASE}/performance-trend${toQueryString({ months, ...filters })}`)
}

export function getCompletionTrend(
  months = 6,
  filters: ReportingFilters = {},
): Promise<ReportingResponse<CompletionTrendPoint[]>> {
  return reportingRequest<CompletionTrendPoint[]>(`${ANALYTICS_BASE}/completion-trend${toQueryString({ months, ...filters })}`)
}

export function getSubmissionTrend(
  months = 6,
  filters: ReportingFilters = {},
): Promise<ReportingResponse<SubmissionTrendPoint[]>> {
  return reportingRequest<SubmissionTrendPoint[]>(`${ANALYTICS_BASE}/submission-trend${toQueryString({ months, ...filters })}`)
}

export async function departmentComparison(
  ids: number[],
  filters: ReportingFilters = {},
): Promise<ReportingResponse<DepartmentComparisonRow[]>> {
  return apiRequest<DepartmentComparisonRow[]>(`${ANALYTICS_BASE}/department-comparison`, {
    method: 'POST',
    body: { comparison_type: 'department', ids, ...filters },
  })
}

export async function employeeComparison(
  ids: number[],
  filters: ReportingFilters = {},
): Promise<ReportingResponse<EmployeeComparisonRow[]>> {
  return apiRequest<EmployeeComparisonRow[]>(`${ANALYTICS_BASE}/employee-comparison`, {
    method: 'POST',
    body: { comparison_type: 'employee', ids, ...filters },
  })
}

export function periodComparison(
  periodA: string,
  periodB: string,
  filters: ReportingFilters = {},
): Promise<ReportingResponse<PeriodComparison>> {
  return apiRequest<PeriodComparison>(`${ANALYTICS_BASE}/period-comparison`, {
    method: 'POST',
    body: { comparison_type: 'period', period_a: periodA, period_b: periodB, ...filters },
  })
}

export function getRankings(
  filters: ReportingFilters = {},
  metric = 'productivity',
): Promise<ReportingResponse<RankingRow[]>> {
  return reportingRequest<RankingRow[]>(`${ANALYTICS_BASE}/rankings${toQueryString({ metric, ...filters })}`)
}

export function getPerformanceDistribution(
  filters: ReportingFilters = {},
): Promise<ReportingResponse<PerformanceDistribution>> {
  return reportingRequest<PerformanceDistribution>(`${ANALYTICS_BASE}/performance-distribution${toQueryString({ ...filters })}`)
}

/** Unified comparison helper for the POST /reports/comparison endpoint. */
export async function runComparison(
  type: ComparisonType,
  ids: number[] | null,
  filters: ReportingFilters & { period_a?: string; period_b?: string } = {},
): Promise<ReportingResponse<{ comparison_type: ComparisonType; result: ComparisonResult }>> {
  const body: Record<string, unknown> = {
    comparison_type: type,
    ...(ids && ids.length > 0 ? { ids } : {}),
    ...(type === 'period' ? { period_a: filters.period_a, period_b: filters.period_b } : {}),
    ...filters,
  }

  return apiRequest(`${REPORTS_BASE}/comparison`, { method: 'POST', body })
}

/** Phase 9 — Report export pipeline (spec items 32-33, 53-55). */
export function listReportExports(): Promise<ReportingResponse<ReportExport[]>> {
  return reportingRequest<ReportExport[]>(EXPORTS_BASE)
}

export interface QueueExportPayload {
  report_type: ReportExportTypeValue
  format: 'csv' | 'xlsx' | 'pdf'
  filters: ReportingFilters
}

export function queueReportExport(payload: QueueExportPayload): Promise<ReportingResponse<ReportExport>> {
  return apiRequest<ReportExport>(EXPORTS_BASE, { method: 'POST', body: payload })
}

export function showReportExport(id: number): Promise<ReportingResponse<ReportExport>> {
  return reportingRequest<ReportExport>(`${EXPORTS_BASE}/${id}`)
}

export async function downloadReportExportFile(id: number): Promise<void> {
  const response = await fetch(`${API_BASE_URL}${EXPORTS_BASE}/${id}/download`, {
    method: 'GET',
    credentials: 'include',
  })

  if (!response.ok) {
    const payload = await response.json().catch(() => null)
    const message =
      payload && typeof payload.message === 'string'
        ? payload.message
        : `Download failed with status ${response.status}`
    throw new ApiError(message, response.status, payload?.errors)
  }

  let filename = 'report_export'
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
}