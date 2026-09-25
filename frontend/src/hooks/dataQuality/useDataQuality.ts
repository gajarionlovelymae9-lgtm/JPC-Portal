import { useState, useEffect, useCallback } from 'react'
import { dataQualityService } from '../../services/dataQuality/dataQualityService'
import type { DataQualityDashboardData } from '../../types/dataQuality'
import { getApiErrorMessage } from '../../utils/apiError'

export function useDataQuality() {
  const [data, setData] = useState<DataQualityDashboardData | null>(null)
  const [loading, setLoading] = useState<boolean>(true)
  const [error, setError] = useState<string | null>(null)

  const fetchDashboard = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await dataQualityService.getDashboard()
      setData(response.data)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load data quality metrics.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchDashboard()
  }, [fetchDashboard])

  return {
    data,
    loading,
    error,
    refresh: fetchDashboard,
  }
}
