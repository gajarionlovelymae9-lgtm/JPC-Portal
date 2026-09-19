import { apiRequest } from './api'
import type { HealthResponse } from '../types/api'

/**
 * System health service.
 *
 * GET /api/v1/health
 */
export async function fetchHealth(): Promise<HealthResponse> {
  return apiRequest<HealthResponse['data']>('/health')
}