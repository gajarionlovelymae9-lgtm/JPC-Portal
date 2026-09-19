import type { ApiResponse } from '../types/api'

const API_BASE_URL: string = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? '/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly details: unknown

  constructor(message: string, status: number, details?: unknown) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.details = details
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  headers?: Record<string, string>
}

/** Reads the Laravel XSRF-TOKEN cookie sent by the backend. */
export function getXsrfToken(): string {
  if (typeof document === 'undefined') {
    return ''
  }
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? decodeURIComponent(match[1]) : ''
}

/**
 * Prime the CSRF cookie by hitting the Sanctum endpoint at the proxy origin.
 * Tolerant of absent cookies (e.g. tests) — CSRF is enforced by the backend.
 */
export async function ensureCsrfToken(): Promise<void> {
  if (getXsrfToken()) {
    return
  }
  try {
    await fetch('/sanctum/csrf-cookie', { method: 'GET', credentials: 'include' })
  } catch {
    // Network errors here surface on the actual request that follows.
  }
}

/**
 * Central HTTP client for every JPC Portal API call.
 *
 * Always reaches the Laravel REST API (`VITE_API_BASE_URL`); components must
 * never talk to the database or make ad-hoc requests outside this service.
 */
export async function apiRequest<T = unknown>(
  path: string,
  options: RequestOptions = {},
): Promise<ApiResponse<T>> {
  const { method = 'GET', body, headers: extraHeaders = {} } = options

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...extraHeaders,
  }

  // Stateful (Sanctum cookie) requests require the CSRF token for any
  // mutating call. Only attach when the browser has one.
  if (method !== 'GET') {
    const token = getXsrfToken()
    if (token && !headers['X-XSRF-TOKEN']) {
      headers['X-XSRF-TOKEN'] = token
    }
  }

  const requestInit: RequestInit = {
    method,
    headers,
    credentials: 'include',
  }

  if (body !== undefined) {
    requestInit.body = JSON.stringify(body)
  }

  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}${path}`, requestInit)
  } catch {
    throw new ApiError('Unable to reach the JPC Portal API', 0)
  }

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    const message =
      payload && typeof payload.message === 'string'
        ? payload.message
        : `Request failed with status ${response.status}`

    throw new ApiError(message, response.status, payload?.errors)
  }

  return payload as ApiResponse<T>
}

/** @deprecated use apiRequest — kept for module-specific services */
export function get<T>(path: string): Promise<ApiResponse<T>> {
  return apiRequest<T>(path)
}

/**
 * Serialize a flat object into a query string, dropping `undefined`, `null`
 * and empty-string values. Used by module services for filtered/paginated GETs.
 */
export function toQueryString(params: Record<string, unknown>): string {
  const search = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    search.set(key, String(value))
  })

  const query = search.toString()
  return query ? `?${query}` : ''
}

export { API_BASE_URL }