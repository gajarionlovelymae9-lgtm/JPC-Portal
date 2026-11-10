import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { ApiError } from '../services/api'
import * as authService from '../services/authService'
import type { UserProfile } from '../types/auth'
import { AuthContext, type AuthContextValue } from './useAuth'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserProfile | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    authService
      .me()
      .then((profile) => {
        if (active) {
          setUser(profile)
        }
      })
      .catch(() => {
        // Not authenticated (401) or backend unreachable — stay logged out.
        if (active) {
          setUser(null)
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const login = useCallback(async (email: string, password: string, role: string) => {
    setError(null)
    try {
      const profile = await authService.login(email, password, role)
      setUser(profile)
      return profile
    } catch (e) {
      const message =
        e instanceof ApiError && e.message ? e.message : 'Unable to sign in right now.'
      setError(message)
      throw e
    }
  }, [])

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      setUser(null)
    }
  }, [])

  const clearError = useCallback(() => setError(null), [])

  const value: AuthContextValue = useMemo(
    () => ({ user, loading, error, login, logout, clearError }),
    [user, loading, error, login, logout, clearError],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}