import { describe, expect, it } from 'vitest'
import { ApiError } from '../services/api'
import { getApiErrorMessage, getApiFieldErrors } from './apiError'

describe('getApiErrorMessage', () => {
  it('maps authorization errors to a friendly message', () => {
    expect(getApiErrorMessage(new ApiError('Forbidden', 403))).toBe(
      'You do not have permission to perform this action.',
    )
  })

  it('maps not-found errors', () => {
    expect(getApiErrorMessage(new ApiError('No query results', 404))).toBe('Task not found.')
  })

  it('maps network failures (status 0)', () => {
    expect(getApiErrorMessage(new ApiError('Unable to reach', 0))).toContain(
      'Unable to reach the JPC Portal API',
    )
  })

  it('maps backpressure (429) errors', () => {
    expect(getApiErrorMessage(new ApiError('Too many requests', 429))).toBe(
      'Too many requests. Please wait a moment before trying again.',
    )
  })

  it('keeps the backend validation message for 422', () => {
    expect(getApiErrorMessage(new ApiError('Validation failed', 422))).toBe('Validation failed')
  })

  it('falls back for unknown values', () => {
    expect(getApiErrorMessage(null, 'Unable to load your tasks.')).toBe(
      'Unable to load your tasks.',
    )
  })
})

describe('getApiFieldErrors', () => {
  it('normalizes Laravel error bags', () => {
    const error = new ApiError('Validation failed', 422, {
      percentage: ['Progress must be between 0 and 100.'],
      remarks: ['Review remarks are required.'],
    })

    expect(getApiFieldErrors(error)).toEqual({
      percentage: ['Progress must be between 0 and 100.'],
      remarks: ['Review remarks are required.'],
    })
  })

  it('returns an empty bag for non-ApiError values', () => {
    expect(getApiFieldErrors(new Error('boom'))).toEqual({})
  })
})
