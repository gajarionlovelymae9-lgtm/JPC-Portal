import { useState, useCallback, useEffect } from 'react'
import { getInsights, getPerformanceInsights, getReportInsights } from '../../api/ai/aiInsightApi'
import { getApiErrorMessage } from '../../utils/apiError'

export function useAiInsights() {
  const [insights, setInsights] = useState<any>(null)
  const [performanceInsights, setPerformanceInsights] = useState<any[]>([])
  const [reportInsights, setReportInsights] = useState<any[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const loadInsights = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await getInsights()
      setInsights(response.data)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load general insights'))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadPerformanceInsights = useCallback(async () => {
    setLoading(true)
    try {
      const response = await getPerformanceInsights()
      setPerformanceInsights((response.data as any[]) || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load performance insights'))
    } finally {
      setLoading(false)
    }
  }, [])

  const loadReportInsights = useCallback(async () => {
    setLoading(true)
    try {
      const response = await getReportInsights()
      setReportInsights((response.data as any[]) || [])
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load report insights'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadInsights()
    void loadPerformanceInsights()
    void loadReportInsights()
  }, [loadInsights, loadPerformanceInsights, loadReportInsights])

  return {
    insights,
    performanceInsights,
    reportInsights,
    loading,
    error,
    loadInsights,
    loadPerformanceInsights,
    loadReportInsights
  }
}
