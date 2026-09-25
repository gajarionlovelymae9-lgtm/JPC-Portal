import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  getAiStatus,
  getAiSummary,
  getAiValidation,
  getAiFindings,
  reviewAiFinding,
  submitHumanReview,
  type AiStatusData,
  type AiSummaryData,
  type AiValidationData,
  type AiFindingData,
} from '../../services/aiReportService'
import AiFindingsList from '../../components/ai/AiFindingsList'
import HumanReviewPanel from '../../components/ai/HumanReviewPanel'
import RequirementValidationCard from '../../components/ai/RequirementValidationCard'
import AiSummaryCard from '../../components/ai/AiSummaryCard'

export default function ReportAiReviewPage() {
  const { reportId } = useParams<{ reportId: string }>()
  const navigate = useNavigate()

  const [loading, setLoading] = useState(true)
  const [toast, setToast] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [statusData, setStatusData] = useState<AiStatusData | null>(null)
  const [summary, setSummary] = useState<AiSummaryData | null>(null)
  const [validation, setValidation] = useState<AiValidationData | null>(null)
  const [findings, setFindings] = useState<AiFindingData[]>([])

  useEffect(() => {
    if (reportId) {
      loadData(reportId)
    }
  }, [reportId])

  const loadData = async (id: string) => {
    setLoading(true)
    try {
      const [sRes, sumRes, valRes, findRes] = await Promise.allSettled([
        getAiStatus(id),
        getAiSummary(id),
        getAiValidation(id),
        getAiFindings(id),
      ])

      if (sRes.status === 'fulfilled') setStatusData(sRes.value.data ?? sRes.value)
      if (sumRes.status === 'fulfilled') setSummary(sumRes.value.data ?? sumRes.value)
      if (valRes.status === 'fulfilled') setValidation(valRes.value.data ?? valRes.value)
      if (findRes.status === 'fulfilled') {
        const raw = findRes.value.data ?? findRes.value
        setFindings(Array.isArray(raw) ? raw : [])
      }
    } catch {
      setToast({ type: 'error', message: 'Failed to load report review workspace.' })
    } finally {
      setLoading(false)
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
      setToast({ type: 'success', message: 'Finding updated.' })
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
      setToast({ type: 'success', message: 'Official decision recorded successfully!' })
      await loadData(reportId)
    } catch {
      setToast({ type: 'error', message: 'Failed to record decision.' })
    }
  }

  if (loading) {
    return (
      <div className="p-8 max-w-7xl mx-auto">
        <div className="animate-pulse space-y-6">
          <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          <div className="h-96 bg-gray-100 rounded-3xl"></div>
        </div>
      </div>
    )
  }

  return (
    <div className="p-6 md:p-8 max-w-7xl mx-auto space-y-8">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-200/80 pb-6">
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
            <span>⚖️</span> Side-by-Side Review Workspace
          </h1>
          <p className="text-sm text-gray-500 mt-1">
            Evaluate AI-generated observations against submission requirements and make the final validation decision.
          </p>
        </div>

        <button
          onClick={() => navigate(`/reports/${reportId}/analysis`)}
          className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition-colors"
        >
          View Full AI Hub →
        </button>
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

      {/* Side-by-Side Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Summary & Requirements */}
        <div className="lg:col-span-7 space-y-6">
          <AiSummaryCard summary={summary} />
          <RequirementValidationCard validation={validation} />
        </div>

        {/* Right Column: Findings & Official Decision */}
        <div className="lg:col-span-5 space-y-6">
          <AiFindingsList
            findings={findings}
            canReview={true}
            onReviewFinding={handleReviewFinding}
          />

          <HumanReviewPanel
            currentStatus={statusData?.status ?? 'ready_for_review'}
            onSubmitDecision={handleSubmitDecision}
          />
        </div>
      </div>
    </div>
  )
}
