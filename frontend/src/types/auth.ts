import type { ApiResponse } from './api'

/**
 * Authenticated user profile returned by /auth/me and /auth/login.
 * Never contains the password hash or server-side secrets.
 */
export interface UserProfile {
  id: number
  name: string
  email: string
  status: string
  role: string
  /**
   * Linked employee record id (`null` for accounts without an employee
   * profile, e.g. administrators). Optional so lightweight test fixtures
   * remain valid.
   */
  employee_id?: number | null
  permissions: string[]
  last_login_at: string | null
}

export type AuthResponse = ApiResponse<UserProfile>