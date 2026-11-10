import { apiRequest } from '../../services/api'
import type { DataQualityScan } from '../../types/dataQuality'

export async function getScans(page = 1, perPage = 15): Promise<{ data: DataQualityScan[]; meta?: any }> {
  return apiRequest(`/data-quality/scans?page=${page}&per_page=${perPage}`)
}

export async function getScan(id: number | string): Promise<{ data: DataQualityScan }> {
  return apiRequest(`/data-quality/scans/${id}`)
}

export async function triggerScan(data: { scan_type?: string; department_id?: number; employee_id?: number } = {}): Promise<{ data: DataQualityScan }> {
  return apiRequest('/data-quality/scans', {
    method: 'POST',
    body: JSON.stringify(data),
  })
}
