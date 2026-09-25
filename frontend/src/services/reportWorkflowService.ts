import { apiRequest } from './api'
import type { ApiResponse } from '../types/api'
import type {
  ReportWorkflowDetail,
  ReportVersionSnapshot,
  ReportTimelineEvent,
  ReportReviewCommentItem,
  VersionComparisonData,
} from '../types/reportWorkflow'

export const reportWorkflowService = {
  /**
   * Create a new draft report.
   */
  async createDraft(formData: FormData): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>('/reports', {
      method: 'POST',
      body: formData,
    })
  },

  /**
   * Update an existing draft report.
   */
  async updateDraft(reportId: number, formData: FormData): Promise<ApiResponse<ReportWorkflowDetail>> {
    formData.append('_method', 'PUT')
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}`, {
      method: 'POST',
      body: formData,
    })
  },

  /**
   * Submit draft report for review.
   */
  async submitReport(reportId: number, formData: FormData): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/submit`, {
      method: 'POST',
      body: formData,
    })
  },

  /**
   * Submit revision (creates Version N+1).
   */
  async submitRevision(reportId: number, formData: FormData): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/revision`, {
      method: 'POST',
      body: formData,
    })
  },

  /**
   * Reviewer: Request revisions from employee.
   */
  async requestRevision(
    reportId: number,
    payload: { reason: string; comments?: Array<{ section_key?: string; comment: string }> }
  ): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/request-revision`, {
      method: 'POST',
      body: payload,
    })
  },

  /**
   * Reviewer: Approve report.
   */
  async approveReport(reportId: number, comments?: string): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/approve`, {
      method: 'POST',
      body: { comments },
    })
  },

  /**
   * Reviewer: Reject report.
   */
  async rejectReport(reportId: number, reason: string): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/reject`, {
      method: 'POST',
      body: { reason },
    })
  },

  /**
   * Cancel report.
   */
  async cancelReport(reportId: number, reason?: string): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/cancel`, {
      method: 'POST',
      body: { reason },
    })
  },

  /**
   * Archive terminal report (Admin only).
   */
  async archiveReport(reportId: number): Promise<ApiResponse<ReportWorkflowDetail>> {
    return apiRequest<ReportWorkflowDetail>(`/reports/${reportId}/archive`, {
      method: 'POST',
    })
  },

  /**
   * Get full audit timeline for report.
   */
  async getTimeline(reportId: number): Promise<ApiResponse<ReportTimelineEvent[]>> {
    return apiRequest<ReportTimelineEvent[]>(`/reports/${reportId}/timeline`)
  },

  /**
   * Get all version snapshots for report.
   */
  async getVersions(reportId: number): Promise<ApiResponse<ReportVersionSnapshot[]>> {
    return apiRequest<ReportVersionSnapshot[]>(`/reports/${reportId}/versions`)
  },

  /**
   * Get single version snapshot detail.
   */
  async getVersionDetail(reportId: number, versionNumber: number): Promise<ApiResponse<ReportVersionSnapshot>> {
    return apiRequest<ReportVersionSnapshot>(`/reports/${reportId}/versions/${versionNumber}`)
  },

  /**
   * Compare two versions of a report.
   */
  async compareVersions(reportId: number, from: number, to: number): Promise<ApiResponse<VersionComparisonData>> {
    return apiRequest<VersionComparisonData>(`/reports/${reportId}/versions-compare?from=${from}&to=${to}`)
  },

  /**
   * Get inline review comments.
   */
  async getComments(reportId: number, versionNumber?: number): Promise<ApiResponse<ReportReviewCommentItem[]>> {
    const params = versionNumber ? `?version_number=${versionNumber}` : ''
    return apiRequest<ReportReviewCommentItem[]>(`/reports/${reportId}/comments${params}`)
  },

  /**
   * Add inline review comment.
   */
  async addComment(
    reportId: number,
    payload: { section_key?: string; comment: string; version_number?: number }
  ): Promise<ApiResponse<ReportReviewCommentItem>> {
    return apiRequest<ReportReviewCommentItem>(`/reports/${reportId}/comments`, {
      method: 'POST',
      body: payload,
    })
  },

  /**
   * Resolve an inline review comment.
   */
  async resolveComment(reportId: number, commentId: number): Promise<ApiResponse<ReportReviewCommentItem>> {
    return apiRequest<ReportReviewCommentItem>(`/reports/${reportId}/comments/${commentId}/resolve`, {
      method: 'POST',
    })
  },
}
