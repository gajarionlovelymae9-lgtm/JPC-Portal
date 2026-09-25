import { useState, useEffect, useCallback } from 'react'
import { dataQualityService } from '../../services/dataQuality/dataQualityService'
import type { DataQualityScan } from '../../types/dataQuality'
import { getApiErrorMessage } from '../../utils/apiError'

export function useDataQualityScans() {
  const [scans, setScans] = useState<DataQualityScan[]>([])
  const [loading, setLoading] = useState<boolean>(true)
  const [scanning, setScanning] = useState<boolean>(false)
  const [error, setError] = useState<string | null>(null)

  const fetchScans = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await dataQualityService.getScans()
      setScans(res.data)
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to load scan history.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchScans()
  }, [fetchScans])

  const trigger = async (scanType = 'full', departmentId?: number, employeeId?: number) => {
    setScanning(true)
    setError(null)
    try {
      const res = await dataQualityService.triggerScan({
        scan_type: scanType,
        department_id: departmentId,
        employee_id: employeeId,
      })
      await fetchScans()
      return res.data
    } catch (err) {
      setError(getApiErrorMessage(err, 'Failed to trigger data quality scan.'))
      throw err
    } finally {
      setScanning(false)
    }
  }

  return {
    scans,
    loading,
    scanning,
    error,
    refresh: fetchScans,
    triggerScan: trigger,
  }
}
