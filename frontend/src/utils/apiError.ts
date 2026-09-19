import { ApiError } from '../services/api'

export type FieldErrors = Record<string, string[]>

const STATUS_MESSAGES: Record<number, string> = {
  0: 'Unable to reach the JPC Portal API. Check your connection and try again.',
  401: 'Your session has expired. Please sign in again.',
  403: 'You do not have permission to perform this action.',
  404: 'Task not found.',
  429: 'Too many requests. Please wait a moment before trying again.',
  500: 'The server ran into a problem. Please try again later.',
}

/** Extract Laravel `errors` payloads (`{ field: [message, ...] }`) safely. */
export function getApiFieldErrors(error: unknown): FieldErrors {
  if (!(error instanceof ApiError) || !error.details || typeof error.details !== 'object') {
    return {}
  }

  const result: FieldErrors = {}
  Object.entries(error.details as Record<string, unknown>).forEach(([field, value]) => {
    if (Array.isArray(value)) {
      result[field] = value.map(String)
    } else if (typeof value === 'string') {
      result[field] = [value]
    }
  })

  return result
}

/**
 * Turn an unknown thrown value into a user-friendly message.
 * Keeps Laravel's 422 validation message and maps common HTTP statuses.
 */
export function getApiErrorMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.',
): string {
  if (!(error instanceof ApiError)) {
    return fallback
  }

  if (error.status === 422) {
    return error.message || 'Please correct the highlighted fields.'
  }

  return STATUS_MESSAGES[error.status] ?? (error.message || fallback)
}
