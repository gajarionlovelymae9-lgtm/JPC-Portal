export type DataQualitySeverity = 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL'

export type DataQualityStatus = 'OPEN' | 'UNDER_REVIEW' | 'CONFIRMED' | 'DISMISSED' | 'RESOLVED' | 'IGNORED'

export type DataQualityScanStatus = 'QUEUED' | 'RUNNING' | 'COMPLETED' | 'FAILED' | 'CANCELLED'

export interface DataQualityIssueHistory {
  id: number
  previous_status: string | null
  new_status: string
  action: string
  performed_by: {
    id: number
    name: string
  } | null
  notes: string | null
  created_at: string
}

export interface DataQualityIssue {
  id: number
  scan_id: number | null
  issue_type: string
  category: string
  severity: DataQualitySeverity
  status: DataQualityStatus
  entity_type: string
  entity_id: number
  department: {
    id: number
    name: string
  } | null
  employee: {
    id: number
    name: string
    employee_number: string
  } | null
  title: string
  description: string
  detected_value: Record<string, any> | null
  expected_value: Record<string, any> | null
  detected_by: string
  reviewed_by: {
    id: number
    name: string
  } | null
  resolved_by: {
    id: number
    name: string
  } | null
  reviewed_at: string | null
  resolved_at: string | null
  resolution_notes: string | null
  histories?: DataQualityIssueHistory[]
  created_at: string
  updated_at: string
}

export interface DataQualityScan {
  id: number
  scan_type: string
  scope: Record<string, any> | null
  status: DataQualityScanStatus
  started_at: string | null
  completed_at: string | null
  duration_seconds: number | null
  records_checked: number
  issues_found: number
  critical_count: number
  high_count: number
  medium_count: number
  low_count: number
  initiated_by: {
    id: number
    name: string
  } | null
  error_message: string | null
  created_at: string
}

export interface DataQualityTrendItem {
  period: string
  year: number
  month: number
  issues_detected: number
  issues_resolved: number
}

export interface DataQualityScanSummary {
  evaluated: boolean
  records_checked: number
  issues_found: number
  latest_scan_at: string | null
  total_records: number
  records_with_issues: number
  complete_records: number
}

export interface DataQualityDashboardData {
  score: number
  total_issues: number
  open_issues: number
  critical: number
  high: number
  medium: number
  low: number
  resolved: number
  dismissed: number
  scan_summary: DataQualityScanSummary | null
  by_category: Record<string, number>
  by_department: Array<{
    department_id: number
    department_name: string
    count: number
  }>
  recent_scans: DataQualityScan[]
  trends: DataQualityTrendItem[]
}

export interface DataQualityFilterParams {
  status?: string
  severity?: string
  issue_type?: string
  entity_type?: string
  department_id?: number | string
  employee_id?: number | string
  search?: string
  page?: number
  per_page?: number
}
