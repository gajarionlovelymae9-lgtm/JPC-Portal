import React, { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { dataQualityService } from '../../services/dataQuality/dataQualityService'
import type { DataQualityIssue } from '../../types/dataQuality'
import { DataQualityIssueDetail } from '../../components/DataQuality/DataQualityIssueDetail'
import { DataQualityReviewModal } from '../../components/DataQuality/DataQualityReviewModal'
import { DataQualityResolutionModal } from '../../components/DataQuality/DataQualityResolutionModal'
import { getApiErrorMessage } from '../../utils/apiError'

export const DataQualityIssueDetailsPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()

  const [issue, setIssue] = useState<DataQualityIssue | null>(null)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const [modalType, setModalType] = useState<'review' | 'confirm' | 'dismiss' | 'resolve' | null>(null)

  const fetchIssue = async () => {
    if (!id) return
    setLoading(true)
    setError(null)
    try {
      const res = await dataQualityService.getIssue(id)
      setIssue(res.data)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load issue details.'))
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchIssue()
  }, [id])

  const handleReviewSubmit = async (notes: string) => {
    if (!id) return
    if (modalType === 'review') {
      const res = await dataQualityService.reviewIssue(id, notes)
      setIssue(res.data)
    } else if (modalType === 'confirm') {
      const res = await dataQualityService.confirmIssue(id, notes)
      setIssue(res.data)
    } else if (modalType === 'dismiss') {
      const res = await dataQualityService.dismissIssue(id, notes)
      setIssue(res.data)
    }
  }

  const handleResolveSubmit = async (notes: string) => {
    if (!id) return
    const res = await dataQualityService.resolveIssue(id, notes)
    setIssue(res.data)
  }

  if (loading && !issue) {
    return (
      <div className="p-8 text-center">
        <div className="inline-block animate-spin rounded-full h-8 w-8 border-4 border-indigo-600 border-t-transparent" />
        <p className="text-sm text-slate-500 mt-2">Loading issue findings...</p>
      </div>
    )
  }

  if (error || !issue) {
    return (
      <div className="p-8 space-y-4">
        <div className="p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl">
          {error || 'Issue not found.'}
        </div>
        <Link to="/data-quality/issues" className="text-indigo-600 text-sm font-semibold hover:underline">
          ← Back to Issues
        </Link>
      </div>
    )
  }

  return (
    <div className="p-6 space-y-6 max-w-5xl mx-auto">
      <div className="flex items-center gap-2">
        <Link to="/data-quality/issues" className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
          ← Back to Issues List
        </Link>
      </div>

      <DataQualityIssueDetail
        issue={issue}
        onReview={() => setModalType('review')}
        onConfirm={() => setModalType('confirm')}
        onDismiss={() => setModalType('dismiss')}
        onResolve={() => setModalType('resolve')}
      />

      <DataQualityReviewModal
        isOpen={modalType === 'review' || modalType === 'confirm' || modalType === 'dismiss'}
        title={
          modalType === 'dismiss'
            ? 'Dismiss Finding as False Positive'
            : modalType === 'confirm'
            ? 'Confirm Defect'
            : 'Mark Finding Under Review'
        }
        actionLabel={
          modalType === 'dismiss'
            ? 'Dismiss Issue'
            : modalType === 'confirm'
            ? 'Confirm'
            : 'Start Review'
        }
        isDismiss={modalType === 'dismiss'}
        onClose={() => setModalType(null)}
        onSubmit={handleReviewSubmit}
      />

      <DataQualityResolutionModal
        isOpen={modalType === 'resolve'}
        onClose={() => setModalType(null)}
        onResolve={handleResolveSubmit}
      />
    </div>
  )
}
