import type { ComponentType, ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { PATHS } from '../routes/paths'
import { AccessDenied } from '../components/AccessDenied'

interface ProtectedRouteProps {
  children: ReactNode
  /** Exact role (or array of roles) required to render the guarded content. */
  role?: string | string[]
  /** Permission required to render the guarded content. */
  permission?: string
  /** When present, the layout/pages inside still render for authenticated users. */
  fallback?: ComponentType
}

export function ProtectedRoute({
  children,
  role,
  permission,
  fallback: Fallback,
}: ProtectedRouteProps) {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="page-state" role="status">
        Loading…
      </div>
    )
  }

  if (!user) {
    return <Navigate to={PATHS.login} replace />
  }

  if (role) {
    const allowedRoles = Array.isArray(role) ? role : [role]
    if (!allowedRoles.includes(user.role)) {
      return Fallback ? <Fallback /> : <AccessDenied />
    }
  }

  if (permission && !user.permissions.includes(permission)) {
    return Fallback ? <Fallback /> : <AccessDenied />
  }

  return children
}