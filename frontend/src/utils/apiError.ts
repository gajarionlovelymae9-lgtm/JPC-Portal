import { ApiError } from '../services/api'

export type FieldErrors = Record<string, string[]>

const STATUS_MESSAGES: Record<number, string> = {
  0: 'Unable to reach the JPC Portal API. Check your connection and try again.',
  401: 'Your session has expired. Please sign in again.',
  403: 'You do not have permission to perform this action.',
  404: 'The requested record could not be found.',
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
 *
 * Keeps Laravel's 422 (with the first offending field appended so the answer
 * is actionable instead of the bare "Validation failed"), tells the user how
 * long to wait on 429 (retry-after aware), and maps common HTTP statuses.
 */
export function getApiErrorMessage(
  error: unknown,
  fallback = 'Something went wrong. Please try again.',
): string {
  if (!(error instanceof ApiError)) {
    return fallback
  }

  if (error.status === 422) {
    const fields = getApiFieldErrors(error)
    const firstError = Object.values(fields)
      .flatMap((messages) => messages)
      .find((message) => message.trim() !== '')

    if (firstError) {
      return `${error.message || 'Please correct the highlighted fields.'} (${firstError})`
    }

    return error.message || 'Please correct the highlighted fields.'
  }

  if (error.status === 429) {
    const wait = error.retryAfterSeconds
    return wait && wait > 0
      ? `Too many requests — please wait ${wait} second${wait === 1 ? '' : 's'} before trying again.`
      : 'Too many requests. Please wait a moment before trying again.'
  }

  return STATUS_MESSAGES[error.status] ?? (error.message || fallback)
}
