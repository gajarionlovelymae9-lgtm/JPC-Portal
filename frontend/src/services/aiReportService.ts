import { apiRequest, toQueryString } from './api'

// ─── Interfaces ──────────────────────────────────────────────────

export interface AiStatusData {
  report_id: number
  title?: string
  status: string
  version: number
  ocr?: {
    status: string
    confidence_score: number | null
    extracted_characters: number
    error?: string | null
    created_at?: string
  } | null
  ai_analyses?: Array<{
    id: number
    type: string
    version: string
    date: string
    provider: string
    model: string
    status: string
    error?: string | null
  }>
}

export interface AiSummaryData {
  overview: string
  key_activities: string[]
  key_accomplishments: string[]
  results: string[]
  issues: string[]
  recommendations: string[]
  action_items?: string[]
  advisory_label: string
  disclaimer: string
}

export interface AiValidationItemData {
  id: number
  category: string
  severity: 'low' | 'medium' | 'high' | 'critical'
  finding: string
  evidence: string
  recommendation: string
}

export interface AiValidationData {
  overall_score: number
  confidence_score: number
  requirement_match: 'met' | 'partially_met' | 'not_evident' | string
  task_alignment_score: number
  reference_anchor_score: number
  evidence_score: number
  explanation: string
  result: string
  requirements: AiValidationItemData[]
  advisory_label: string
  disclaimer: string
}

export interface ReferenceItemData {
  title: string
  type: string
  url?: string
  status: 'Referenced' | 'Partially Referenced' | 'Not Evident' | 'Unable to Determine'
  confidence: number
  note: string
}

export interface ReferenceMatrixData {
  anchor_score: number
  references: ReferenceItemData[]
  scanned_urls: string[]
}

export interface SimilarityComparisonItem {
  compared_report_id: number
  author: string
  task_title: string
  similarity_score: number
  status: string
  explanation: string
}

export interface SimilarityMatrixData {
  overall_similarity_score: number
  risk_level: 'potential_duplicate' | 'high_similarity' | 'moderate_similarity' | 'unique'
  advisory_label: string
  disclaimer: string
  comparisons: SimilarityComparisonItem[]
}

export interface AiFindingData {
  id: number
  report_id: number
  report_version_id: number
  ai_analysis_id?: number | null
  finding_type: 'missing_requirement' | 'weak_evidence' | 'reference_concern' | 'potential_duplicate' | 'ocr_concern' | 'incomplete_report' | 'data_inconsistency'
  severity: 'low' | 'medium' | 'high' | 'critical'
  title: string
  description: string
  evidence?: string | null
  confidence_score?: number | null
  status: 'open' | 'reviewed' | 'confirmed' | 'dismissed'
  reviewer?: { id: number; name: string; email: string } | null
  reviewer_action?: 'accept' | 'dismiss' | 'modify' | string | null
  reviewer_notes?: string | null
  reviewed_at?: string | null
  created_at: string
}

export interface VersionComparisonData {
  v1: number
  v2: number
  resolved_findings_count: number
  resolved_findings: AiFindingData[]
  current_findings_count: number
  current_findings: AiFindingData[]
}

// ─── API Methods ────────────────────────────────────────────────

export const getAiStatus = (reportId: number | string) =>
  apiRequest<AiStatusData>(`/ai-processing/${reportId}`)

export const triggerAiAnalysis = (reportId: number | string) =>
  apiRequest<AiStatusData>(`/ai-processing/${reportId}/analyze`, { method: 'POST' })

export const triggerOcrProcessing = (reportId: number | string) =>
  apiRequest<AiStatusData>(`/ai-processing/${reportId}/ocr`, { method: 'POST' })

export const getAiSummary = (reportId: number | string) =>
  apiRequest<AiSummaryData>(`/ai-processing/${reportId}/ai-summary`)

export const getAiValidation = (reportId: number | string) =>
  apiRequest<AiValidationData>(`/ai-processing/${reportId}/ai-validation`)

export const getAiReferences = (reportId: number | string) =>
  apiRequest<ReferenceMatrixData>(`/ai-processing/${reportId}/references`)

export const getAiSimilarity = (reportId: number | string) =>
  apiRequest<SimilarityMatrixData>(`/ai-processing/${reportId}/similarity`)

export const getAiFindings = (reportId: number | string) =>
  apiRequest<AiFindingData[]>(`/ai-processing/${reportId}/findings`)

export const reviewAiFinding = (
  reportId: number | string,
  findingId: number | string,
  data: { action: 'accept' | 'dismiss' | 'modify'; notes?: string }
) =>
  apiRequest<AiFindingData>(`/ai-processing/${reportId}/findings/${findingId}/review`, {
    method: 'POST',
    body: data,
  })

export const submitHumanReview = (
  reportId: number | string,
  data: { decision: 'validated' | 'approved' | 'revision_required' | 'rejected'; notes?: string }
) =>
  apiRequest<AiStatusData>(`/ai-processing/${reportId}/review`, {
    method: 'POST',
    body: data,
  })

export const retryAiProcessing = (reportId: number | string) =>
  apiRequest<AiStatusData>(`/ai-processing/${reportId}/retry`, { method: 'POST' })

export const compareReportVersions = (
  reportId: number | string,
  params?: { v1?: number; v2?: number }
) =>
  apiRequest<VersionComparisonData>(`/ai-processing/${reportId}/versions/compare${toQueryString(params ?? {})}`)
