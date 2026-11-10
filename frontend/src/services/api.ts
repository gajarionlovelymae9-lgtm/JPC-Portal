import type { ApiResponse } from '../types/api'

const API_BASE_URL: string = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? '/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly details: unknown
  /** Seconds the server asked us to wait before retrying (429 only). */
  readonly retryAfterSeconds: number | null

  constructor(message: string, status: number, details?: unknown, retryAfterSeconds: number | null = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.details = details
    this.retryAfterSeconds = retryAfterSeconds
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  headers?: Record<string, string>
  /** Forwarded to fetch so dashboards can force a fresh read. */
  cache?: RequestCache
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
  const { method = 'GET', body, headers: extraHeaders = {}, cache } = options

  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData

  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
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
    ...(cache ? { cache } : {}),
  }

  if (body !== undefined) {
    requestInit.body = isFormData ? body : JSON.stringify(body)
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

    const retryAfterRaw = response.headers?.get?.('Retry-After') ?? null
    const retryAfter = retryAfterRaw !== null && /^\d+$/.test(retryAfterRaw) ? Number(retryAfterRaw) : null

    throw new ApiError(message, response.status, payload?.errors, retryAfter)
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

/**
 * Download a streamed file response (CSV/XLSX/PDF exports) to the browser.
 *
 * The export endpoints stream a file rather than returning the JSON envelope,
 * so they cannot go through `apiRequest`. CSRF is not needed for GET, but the
 * session cookie is — hence `credentials: 'include'`.
 */
export async function downloadFile(
  path: string,
  fallbackFilename: string,
): Promise<void> {
  let response: Response
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method: 'GET',
      credentials: 'include',
    })
  } catch {
    throw new ApiError('Unable to reach the JPC Portal API', 0)
  }

  if (!response.ok) {
    // Failures still come back as the standard JSON envelope.
    const payload = await response.json().catch(() => null)
    const message =
      payload && typeof payload.message === 'string'
        ? payload.message
        : `Export failed with status ${response.status}`

    throw new ApiError(message, response.status, payload?.errors)
  }

  const blob = await response.blob()

  // Honour the server's filename when the disposition header exposes it.
  const disposition = response.headers.get('Content-Disposition') ?? ''
  const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)
  const filename = match ? decodeURIComponent(match[1]) : fallbackFilename

  const href = URL.createObjectURL(blob)
  try {
    const anchor = document.createElement('a')
    anchor.href = href
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
  } finally {
    URL.revokeObjectURL(href)
  }
}

export { API_BASE_URL }