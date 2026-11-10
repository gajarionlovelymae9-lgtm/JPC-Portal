import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  getAiStatus,
  getAiSummary,
  getAiValidation,
  getAiReferences,
  getAiSimilarity,
  getAiFindings,
  reviewAiFinding,
  submitHumanReview,
  triggerAiAnalysis,
  compareReportVersions,
  type AiStatusData,
  type AiSummaryData,
  type AiValidationData,
  type ReferenceMatrixData,
  type SimilarityMatrixData,
  type AiFindingData,
  type VersionComparisonData,
} from '../../services/aiReportService'
import { useAuth } from '../../auth/useAuth'
import AiSummaryCard from '../../components/ai/AiSummaryCard'
import RequirementValidationCard from '../../components/ai/RequirementValidationCard'
import ReferenceAnalysisCard from '../../components/ai/ReferenceAnalysisCard'
import SimilarityCard from '../../components/ai/SimilarityCard'
import AiFindingsList from '../../components/ai/AiFindingsList'
import HumanReviewPanel from '../../components/ai/HumanReviewPanel'
import ReportVersionComparison from '../../components/ai/ReportVersionComparison'
import ExtractedTextViewer from '../../components/ai/ExtractedTextViewer'
import OcrStatusBadge from '../../components/ai/OcrStatusBadge'

type TabKey = 'summary' | 'validation' | 'references' | 'similarity' | 'findings' | 'ocr' | 'comparison'

export default function ReportAiAnalysisPage() {
  const { reportId } = useParams<{ reportId: string }>()
  const { user } = useAuth()
  const navigate = useNavigate()

  const [activeTab, setActiveTab] = useState<TabKey>('summary')
  const [loading, setLoading] = useState(true)
  const [actionLoading, setActionLoading] = useState(false)
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  // Data states
  const [statusData, setStatusData] = useState<AiStatusData | null>(null)
  const [summary, setSummary] = useState<AiSummaryData | null>(null)
  const [validation, setValidation] = useState<AiValidationData | null>(null)
  const [references, setReferences] = useState<ReferenceMatrixData | null>(null)
  const [similarity, setSimilarity] = useState<SimilarityMatrixData | null>(null)
  const [findings, setFindings] = useState<AiFindingData[]>([])
  const [comparison, setComparison] = useState<VersionComparisonData | null>(null)

  const canReview = user?.role === 'admin' || user?.role === 'department_head' || user?.role === 'hr'

  useEffect(() => {
    if (reportId) {
      loadAllData(reportId)
    }
  }, [reportId])

  const loadAllData = async (id: string) => {
    setLoading(true)
    try {
      const [sRes, sumRes, valRes, refRes, simRes, findRes] = await Promise.allSettled([
        getAiStatus(id),
        getAiSummary(id),
        getAiValidation(id),
        getAiReferences(id),
        getAiSimilarity(id),
        getAiFindings(id),
      ])

      if (sRes.status === 'fulfilled') setStatusData(sRes.value.data ?? sRes.value)
      if (sumRes.status === 'fulfilled') setSummary(sumRes.value.data ?? sumRes.value)
      if (valRes.status === 'fulfilled') setValidation(valRes.value.data ?? valRes.value)
      if (refRes.status === 'fulfilled') setReferences(refRes.value.data ?? refRes.value)
      if (simRes.status === 'fulfilled') setSimilarity(simRes.value.data ?? simRes.value)
      if (findRes.status === 'fulfilled') {
        const raw = findRes.value.data ?? findRes.value
        setFindings(Array.isArray(raw) ? raw : [])
      }

      // Load comparison if version > 1
      try {
        const compRes = await compareReportVersions(id)
        setComparison(compRes.data ?? compRes)
      } catch {
        setComparison(null)
      }
    } catch {
      setToast({ type: 'error', message: 'Failed to load report AI analysis data.' })
    } finally {
      setLoading(false)
    }
  }

  const handleTriggerAnalysis = async () => {
    if (!reportId) return
    setActionLoading(true)
    try {
      await triggerAiAnalysis(reportId)
      setToast({ type: 'success', message: 'AI Analysis pipeline executed successfully!' })
      await loadAllData(reportId)
    } catch {
      setToast({ type: 'error', message: 'Failed to execute AI analysis.' })
    } finally {
      setActionLoading(false)
    }
  }

  const handleReviewFinding = async (
    findingId: number,
    action: 'accept' | 'dismiss' | 'modify',
    notes?: string
  ) => {
    if (!reportId) return
    try {
      await reviewAiFinding(reportId, findingId, { action, notes })
      setToast({ type: 'success', message: 'Finding updated successfully.' })
      const res = await getAiFindings(reportId)
      setFindings(Array.isArray(res.data ?? res) ? (res.data ?? res) : [])
    } catch {
      setToast({ type: 'error', message: 'Failed to update finding.' })
    }
  }

  const handleSubmitDecision = async (
    decision: 'validated' | 'approved' | 'revision_required' | 'rejected',
    notes: string
  ) => {
    if (!reportId) return
    try {
      await submitHumanReview(reportId, { decision, notes })
      setToast({ type: 'success', message: 'Official human review decision recorded!' })
      await loadAllData(reportId)
    } catch {
      setToast({ type: 'error', message: 'Failed to submit official decision.' })
    }
  }

  if (loading) {
    return (
      <div className="p-8 max-w-7xl mx-auto">
        <div className="animate-pulse space-y-6">
          <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          <div className="h-4 bg-gray-100 rounded w-1/2"></div>
          <div className="h-96 bg-gray-100 rounded-3xl"></div>
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 md:p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <button
              onClick={() => navigate(-1)}
              className="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1"
            >
              ← Back
            </button>
            <span className="text-gray-300">/</span>
            <span className="text-xs font-bold text-gray-400">Report #{reportId}</span>
          </div>

          <h1 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-3">
            <span>🤖</span> Document AI Analysis & Validation
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Grounded NLP summarization, task requirement checks, reference anchoring, and similarity index.
          </p>
        </div>

        <div className="flex items-center gap-3">
          {statusData?.ocr && (
            <OcrStatusBadge
              status={statusData.ocr.status}
              confidence={statusData.ocr.confidence_score}
              characters={statusData.ocr.extracted_characters}
            />
          )}

          <button
            onClick={handleTriggerAnalysis}
            disabled={actionLoading}
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold text-xs rounded-2xl shadow-sm transition-all"
          >
            {actionLoading ? 'Analyzing...' : '⚡ Run AI Pipeline'}
          </button>
        </div>
      </div>

      {toast && (
        <div
          className={`p-4 rounded-2xl border text-sm font-semibold flex items-center justify-between ${
            toast.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-red-50 border-red-200 text-red-800'
          }`}
        >
          <span>{toast.message}</span>
          <button onClick={() => setToast(null)} className="text-xs opacity-70 hover:opacity-100">
            ✕
          </button>
        </div>
      )}

      {/* Tabs Navigation */}
      <div className="flex items-center gap-2 border-b border-gray-100 overflow-x-auto pb-px">
        {[
          { key: 'summary', label: 'JPC AIDE Summary', icon: '🤖' },
          { key: 'validation', label: 'Requirements', icon: '✓' },
          { key: 'findings', label: 'AI Findings', icon: '⚠️', badge: findings.length },
          { key: 'references', label: 'References', icon: '🔗' },
          { key: 'similarity', label: 'Similarity Index', icon: '👥' },
          { key: 'ocr', label: 'Extracted Text', icon: '📄' },
          { key: 'comparison', label: 'Version Diff', icon: '🔀' },
        ].map((tab) => {
          const isActive = activeTab === tab.key

          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as TabKey)}
              className={`flex items-center gap-2 px-4 py-3 text-xs font-bold border-b-2 transition-all whitespace-nowrap ${
                isActive
                  ? 'border-blue-600 text-blue-600 bg-blue-50/30 rounded-t-xl'
                  : 'border-transparent text-gray-500 hover:text-gray-900'
              }`}
            >
              <span>{tab.icon}</span>
              <span>{tab.label}</span>
              {tab.badge !== undefined && tab.badge > 0 && (
                <span className="bg-amber-100 text-amber-800 text-[10px] font-extrabold px-1.5 py-0.5 rounded-full">
                  {tab.badge}
                </span>
              )}
            </button>
          )
        })}
      </div>

      {/* Active Tab Content */}
      <div>
        {activeTab === 'summary' && (
          <AiSummaryCard summary={summary} onReanalyze={handleTriggerAnalysis} />
        )}

        {activeTab === 'validation' && (
          <RequirementValidationCard validation={validation} />
        )}

        {activeTab === 'findings' && (
          <AiFindingsList
            findings={findings}
            canReview={canReview}
            onReviewFinding={handleReviewFinding}
          />
        )}

        {activeTab === 'references' && (
          <ReferenceAnalysisCard references={references} />
        )}

        {activeTab === 'similarity' && (
          <SimilarityCard similarity={similarity} />
        )}

        {activeTab === 'ocr' && (
          <ExtractedTextViewer
            text={summary?.overview ? `${summary.overview}\n\nKey Activities:\n${summary.key_activities.join('\n')}\n\nResults:\n${summary.results.join('\n')}` : 'No text content available.'}
            confidence={statusData?.ocr?.confidence_score ?? 0.95}
          />
        )}

        {activeTab === 'comparison' && (
          <ReportVersionComparison comparison={comparison} />
        )}
      </div>

      {/* Final Human Decision Panel for Authorized Reviewers */}
      {canReview && (
        <HumanReviewPanel
          currentStatus={statusData?.status ?? 'ready_for_review'}
          onSubmitDecision={handleSubmitDecision}
        />
      )}
    </div>
  )
}
