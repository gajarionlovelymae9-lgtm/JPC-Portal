import { useLocation, useNavigate } from 'react-router-dom'
import { NAVIGATION_ITEMS } from '../constants/navigation'
import { NotificationBell } from './NotificationBell'
import { useAuth } from '../auth/useAuth'
import { PATHS } from '../routes/paths'

interface HeaderProps {
  onMenuClick: () => void
}

function pageTitleFor(pathname: string): string {
  const match = NAVIGATION_ITEMS.find((item) => item.path === pathname)
  if (match) {
    return match.label
  }
  if (
    pathname.startsWith('/employee/tasks/') ||
    (pathname.startsWith('/tasks/') && pathname !== PATHS.tasks)
  ) {
    return 'Task Details'
  }
  if (pathname.startsWith('/employee/')) {
    return 'My Work'
  }
  if (pathname === PATHS.settings) {
    return 'Settings'
  }
  if (pathname === '/') {
    return 'Dashboard'
  }
  return 'JPC Portal'
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

export function Header({ onMenuClick }: HeaderProps) {
  const { pathname } = useLocation()
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function handleLogout() {
    await logout()
    navigate(PATHS.login, { replace: true })
  }

  return (
    <header className="header">
      <button
        type="button"
        className="header__menu-toggle"
        onClick={onMenuClick}
        aria-label="Toggle navigation"
      >
        <span aria-hidden="true">☰</span>
      </button>

      <div className="header__brand">
        <span className="header__brand-mark" aria-hidden="true">
          JPC
        </span>
        <span className="header__brand-name">JPC Portal</span>
      </div>

      <h1 className="header__title">{pageTitleFor(pathname)}</h1>

      <div className="header__actions">
        <NotificationBell />

        <div className="header__profile-wrap">
          <span className="header__avatar" aria-hidden="true">
            {user ? initials(user.name) : 'JP'}
          </span>
          <span className="header__profile-name">{user?.name ?? 'Profile'}</span>

          <button type="button" className="header__logout" onClick={handleLogout}>
            Sign out
          </button>
        </div>
      </div>
    </header>
  )
}