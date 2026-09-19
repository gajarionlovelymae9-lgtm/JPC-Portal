import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchHealth } from './health'
import { ApiError } from './api'

describe('fetchHealth', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('resolves with the health payload on success', async () => {
    const payload = {
      success: true,
      message: 'JPC Portal API is running',
      data: { application: 'ok', database: 'ok' },
    }

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => payload,
      }),
    )

    await expect(fetchHealth()).resolves.toMatchObject({ success: true })
  })

  it('throws ApiError when the backend is unreachable (network failure)', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    await expect(fetchHealth()).rejects.toThrow(ApiError)
  })

  it('throws ApiError with the server message on a non-2xx response', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => ({ success: false, message: 'An unexpected error occurred' }),
      }),
    )

    await expect(fetchHealth()).rejects.toMatchObject({
      status: 500,
      message: 'An unexpected error occurred',
    })
  })
})