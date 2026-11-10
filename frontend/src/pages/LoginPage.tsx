import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { homePathForRole, PATHS } from '../routes/paths'
import { LOGIN_ROLES } from '../types/auth'
import type { UserProfile } from '../types/auth'

/**
 * An intended destination only wins when it is a real page. The site root and
 * the legacy /dashboard path are routing entry points, so the role home is used
 * instead to keep employees out of the generic foundation page.
 */
function landingPath(profile: UserProfile | null, from?: string): string {
  if (from && from !== '/' && from !== PATHS.dashboard) return from
  return homePathForRole(profile?.role ?? '')
}

function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

export default function LoginPage() {
  const { user, loading, error, login, clearError } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [role, setRole] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<{
    role?: string
    email?: string
    password?: string
  }>({})

  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname

  if (!loading && user) {
    return <Navigate to={landingPath(user, from)} replace />
  }

  function validate(): boolean {
    const errors: { role?: string; email?: string; password?: string } = {}

    if (!role) {
      errors.role = 'Please select your role.'
    }
    if (!email.trim()) {
      errors.email = 'The email field is required.'
    } else if (!isValidEmail(email)) {
      errors.email = 'The email must be a valid email address.'
    }
    if (!password) {
      errors.password = 'The password field is required.'
    }

    setFieldErrors(errors)
    return Object.keys(errors).length === 0
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) {
      return
    }
    if (error) clearError()
    if (!validate()) {
      return
    }
    setSubmitting(true)
    try {
      const profile = await login(email.trim(), password, role)
      navigate(landingPath(profile, from), { replace: true })
    } catch {
      // Error message is exposed through the auth context.
    } finally {
      setSubmitting(false)
    }
  }

  function clearFieldError(field: keyof typeof fieldErrors) {
    if (fieldErrors[field]) {
      setFieldErrors((prev) => ({ ...prev, [field]: undefined }))
    }
  }

  return (
    <div className="auth-page">
      <form className="auth-card" onSubmit={handleSubmit} noValidate autoComplete="on">
        <div className="auth-card__brand">
          <img 
            src="/logo.png" 
            alt="JPC Logo" 
            className="auth-card__logo" 
          />
          <div className="auth-card__brand-text">
            <span className="auth-card__brand-mark">JPC</span>
            <span className="auth-card__brand-name">Portal</span>
          </div>
        </div>

        <div className="auth-card__header">
          <h1 className="auth-card__title">Sign in</h1>
          <p className="auth-card__subtitle">Employee Progress Monitoring System</p>
        </div>

        {error && (
          <div className="auth-card__error" role="alert">
            {error}
          </div>
        )}

        <div className="auth-card__field">
          <label htmlFor="login-role">Role</label>
          <div className="auth-card__input-wrap auth-card__input-wrap--select">
            <svg
              className="auth-card__input-icon"
              viewBox="0 0 16 16"
              width="16"
              height="16"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <circle cx="8" cy="5.2" r="2.6" />
              <path d="M3.2 13.4a4.8 4.8 0 0 1 9.6 0" />
            </svg>
            <select
              id="login-role"
              name="role"
              className={fieldErrors.role ? 'has-error' : ''}
              required
              value={role}
              onChange={(e) => {
                setRole(e.target.value)
                clearFieldError('role')
                if (error) clearError()
              }}
              aria-invalid={fieldErrors.role ? 'true' : 'false'}
              aria-describedby={fieldErrors.role ? 'login-role-error' : undefined}
            >
              <option value="" disabled>
                Select your role
              </option>
              {LOGIN_ROLES.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
          {fieldErrors.role && (
            <span className="auth-card__field-error" id="login-role-error" role="alert">
              {fieldErrors.role}
            </span>
          )}
        </div>

        <div className="auth-card__field">
          <label htmlFor="login-email">Email</label>
          <div className="auth-card__input-wrap">
            <svg
              className="auth-card__input-icon"
              viewBox="0 0 16 16"
              width="16"
              height="16"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <rect x="2.2" y="3.8" width="11.6" height="8.4" rx="1.6" />
              <path d="m2.6 4.4 5.4 4.2 5.4-4.2" />
            </svg>
            <input
              id="login-email"
              type="email"
              name="email"
              autoComplete="email"
              inputMode="email"
              required
              placeholder="you@example.com"
              value={email}
              className={fieldErrors.email ? 'has-error' : ''}
              onChange={(e) => {
                setEmail(e.target.value)
                clearFieldError('email')
                if (error) clearError()
              }}
              aria-invalid={fieldErrors.email ? 'true' : 'false'}
              aria-describedby={fieldErrors.email ? 'login-email-error' : undefined}
            />
          </div>
          {fieldErrors.email && (
            <span className="auth-card__field-error" id="login-email-error" role="alert">
              {fieldErrors.email}
            </span>
          )}
        </div>

        <div className="auth-card__field">
          <label htmlFor="login-password">Password</label>
          <div className="auth-card__input-wrap">
            <svg
              className="auth-card__input-icon"
              viewBox="0 0 16 16"
              width="16"
              height="16"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
              focusable="false"
            >
              <rect x="3" y="7" width="10" height="6.2" rx="1.6" />
              <path d="M5 7V5a3 3 0 0 1 6 0v2" />
            </svg>
            <input
              id="login-password"
              type={showPassword ? 'text' : 'password'}
              name="password"
              autoComplete="current-password"
              required
              value={password}
              className={fieldErrors.password ? 'has-error' : ''}
              onChange={(e) => {
                setPassword(e.target.value)
                clearFieldError('password')
                if (error) clearError()
              }}
              aria-invalid={fieldErrors.password ? 'true' : 'false'}
              aria-describedby={fieldErrors.password ? 'login-password-error' : undefined}
            />
            <button
              type="button"
              className="auth-card__toggle"
              onClick={() => setShowPassword((v) => !v)}
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              aria-pressed={showPassword}
            >
              {showPassword ? 'Hide' : 'Show'}
            </button>
          </div>
          {fieldErrors.password && (
            <span className="auth-card__field-error" id="login-password-error" role="alert">
              {fieldErrors.password}
            </span>
          )}
        </div>

        <button
          type="submit"
          className="auth-card__submit"
          disabled={submitting}
          aria-busy={submitting}
        >
          {submitting && <span className="auth-card__spinner" aria-hidden="true" />}
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </div>
  )
}
