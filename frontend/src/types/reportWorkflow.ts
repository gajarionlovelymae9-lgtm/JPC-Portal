export type ReportWorkflowStatus =
  | 'draft'
  | 'submitted'
  | 'processing'
  | 'under_review'
  | 'revision_required'
  | 'resubmitted'
  | 'approved'
  | 'rejected'
  | 'cancelled'
  | 'archived'

export interface ReportFileItem {
  id: number
  report_id: number
  file_name: string
  file_path: string
  file_size: number
  file_type?: string
  created_at?: string
}

export interface ReportTimelineEvent {
  id: number
  report_id: number
  report_version_id?: number | null
  actor_id?: number | null
  actor?: {
    id: number
    name: string
    email: string
  } | null
  event_type: string
  title: string
  description: string
  metadata?: Record<string, any> | null
  created_at: string
}

export interface ReportReviewCommentItem {
  id: number
  report_id: number
  report_version_id?: number | null
  reviewer_id: number
  reviewer?: {
    id: number
    name: string
    email: string
  } | null
  section_key: string
  comment: string
  status: 'open' | 'resolved'
  resolved_at?: string | null
  resolved_by?: number | null
  resolver?: {
    id: number
    name: string
    email: string
  } | null
  created_at: string
}

export interface ReportVersionSnapshot {
  id: number
  report_id: number
  version_number: number
  title?: string | null
  content?: string | null
  references?: string | null
  status: string
  submitted_by?: number | null
  submitter?: {
    id: number
    name: string
    email: string
  } | null
  file_path?: string | null
  notes?: string | null
  change_summary?: string | null
  metadata?: Record<string, any> | null
  submitted_at?: string | null
  created_at: string
  files?: ReportFileItem[]
  comments?: ReportReviewCommentItem[]
  timeline?: ReportTimelineEvent[]
}

export interface VersionComparisonData {
  report_id: number
  from_version: {
    version_number: number
    title: string
    status: string
    submitted_at: string | null
    submitted_by: string | null
    notes: string | null
    content_length: number
    line_count: number
  }
  to_version: {
    version_number: number
    title: string
    status: string
    submitted_at: string | null
    submitted_by: string | null
    change_summary: string | null
    notes: string | null
    content_length: number
    line_count: number
  }
  changes: {
    title_changed: boolean
    content_changed: boolean
    references_changed: boolean
    length_difference: number
    line_difference: number
  }
  side_by_side: {
    from_content: string
    to_content: string
    from_references: string | null
    to_references: string | null
  }
}

export interface ReportWorkflowDetail {
  id: number
  task_id: number
  employee_id: number
  title: string
  content: string | null
  references: string | null
  remarks?: string | null
  version: number
  status: ReportWorkflowStatus
  ai_status?: string | null
  is_late: boolean
  submitted_at: string | null
  approved_at: string | null
  rejected_at: string | null
  created_at: string
  updated_at: string
  task?: {
    id: number
    title: string
    task_code?: string
    deadline?: string | null
    description?: string | null
    requirements?: string | null
  }
  employee?: {
    id: number
    first_name: string
    last_name: string
    employee_number: string
    department?: {
      id: number
      name: string
    }
    position?: {
      id: number
      name: string
    }
  }
  files?: ReportFileItem[]
  versions?: ReportVersionSnapshot[]
}
