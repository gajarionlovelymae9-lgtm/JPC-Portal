/**
 * Pagination metadata returned alongside paginated collections.
 * Emitted by `App\Support\ApiResponse::collection()` as a sibling of `data`.
 */
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

/**
 * Standard JPC Portal API envelope.
 *
 * Success:    { success: true,  message: string, data: T }
 * Collection: { success: true,  message: string, data: T[], meta?: PaginationMeta }
 * Error:      { success: false, message: string, errors?: unknown }
 */
export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  meta?: PaginationMeta
  errors?: unknown
}

/** Convenience alias for endpoints that return a paginated collection. */
export type PaginatedResponse<T> = ApiResponse<T[]> & {
  meta: PaginationMeta
}

export interface HealthData {
  application: 'ok' | 'error'
  database: 'ok' | 'error'
  api: 'ok' | 'error'
  environment: string
  api_version: string
  timestamp: string
}

export type HealthResponse = ApiResponse<HealthData>