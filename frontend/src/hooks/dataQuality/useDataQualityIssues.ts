import { useState, useEffect, useCallback } from 'react'
import { dataQualityService } from '../../services/dataQuality/dataQualityService'
import type { DataQualityFilterParams, DataQualityIssue } from '../../types/dataQuality'
import { getApiErrorMessage } from '../../utils/apiError'

export function useDataQualityIssues(initialFilters: DataQualityFilterParams = {}) {
  const [issues, setIssues] = useState<DataQualityIssue[]>([])
  const [filters, setFilters] = useState<DataQualityFilterParams>(initialFilters)
  const [meta, setMeta] = useState<any>(null)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const fetchIssues = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await dataQualityService.getIssues(filters)
      setIssues(res.data)
      setMeta(res.meta)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load data quality issues.'))
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    fetchIssues()
  }, [fetchIssues])

  const review = async (id: number, notes?: string) => {
    const res = await dataQualityService.reviewIssue(id, notes)
    setIssues((prev) => prev.map((i) => (i.id === id ? res.data : i)))
    return res.data
  }

  const confirm = async (id: number, notes?: string) => {
    const res = await dataQualityService.confirmIssue(id, notes)
    setIssues((prev) => prev.map((i) => (i.id === id ? res.data : i)))
    return res.data
  }

  const dismiss = async (id: number, reason: string) => {
    const res = await dataQualityService.dismissIssue(id, reason)
    setIssues((prev) => prev.map((i) => (i.id === id ? res.data : i)))
    return res.data
  }

  const resolve = async (id: number, notes: string) => {
    const res = await dataQualityService.resolveIssue(id, notes)
    setIssues((prev) => prev.map((i) => (i.id === id ? res.data : i)))
    return res.data
  }

  return {
    issues,
    filters,
    setFilters,
    meta,
    loading,
    error,
    refresh: fetchIssues,
    review,
    confirm,
    dismiss,
    resolve,
  }
}
