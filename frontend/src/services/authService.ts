import { apiRequest, ensureCsrfToken } from './api'
import type { UserProfile } from '../types/auth'

/**
 * Authentication API client for the JPC Portal Sanctum SPA flow.
 * All calls send the session cookie; mutating calls carry X-XSRF-TOKEN.
 */
export async function login(
  email: string,
  password: string,
  role: string,
): Promise<UserProfile> {
  await ensureCsrfToken()
  const response = await apiRequest<UserProfile>('/auth/login', {
    method: 'POST',
    body: { email, password, role },
  })
  return response.data
}

export async function logout(): Promise<void> {
  await apiRequest<null>('/auth/logout', { method: 'POST' })
}

export async function me(): Promise<UserProfile> {
  const response = await apiRequest<UserProfile>('/auth/me')
  return response.data
}
