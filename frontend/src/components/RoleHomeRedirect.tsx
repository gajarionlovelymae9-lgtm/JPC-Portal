import { Navigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { homePathForRole, PATHS } from '../routes/paths'
import { AccessDenied } from './AccessDenied'
import DashboardPage from '../pages/DashboardPage'

/**
 * Sends an authenticated user to the home page for their role.
 *
 * Employees go to /employee/dashboard and each management role to its own
 * dashboard, so no role lands on the generic foundation page. This also backs
 * the legacy /dashboard path, which is why an unrecognised role renders that
 * page instead of redirecting to itself.
 */
export function RoleHomeRedirect() {
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

  const target = homePathForRole(user.role)

  if (target === PATHS.dashboard) {
    // Role this build does not know: keep the previous behaviour of the
    // permission-gated platform page rather than redirecting /dashboard to itself.
    return user.permissions.includes('dashboard.view') ? <DashboardPage /> : <AccessDenied />
  }

  return <Navigate to={target} replace />
}

export default RoleHomeRedirect
