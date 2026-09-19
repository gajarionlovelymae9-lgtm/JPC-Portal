import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { PATHS } from '../routes/paths'

export default function LoginPage() {
  const { user, loading, error, login, clearError } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname

  if (!loading && user) {
    return <Navigate to={from ?? PATHS.dashboard} replace />
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (submitting) {
      return
    }
    setSubmitting(true)
    try {
      await login(email.trim(), password)
      navigate(from ?? PATHS.dashboard, { replace: true })
    } catch {
      // Error message is exposed through the auth context.
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth-page">
      <form className="auth-card" onSubmit={handleSubmit} noValidate>
        <div className="auth-card__brand">
          <span className="sidebar__brand-mark" aria-hidden="true">
            JPC
          </span>
          <span className="sidebar__brand-name">JPC Portal</span>
        </div>

        <h1 className="auth-card__title">Sign in</h1>
        <p className="auth-card__subtitle">
          Employee Progress Monitoring System
        </p>

        {error && (
          <div className="auth-card__error" role="alert">
            {error}
          </div>
        )}

        <label className="auth-card__field">
          <span>Email</span>
          <input
            type="email"
            name="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => {
              setEmail(e.target.value)
              if (error) clearError()
            }}
          />
        </label>

        <label className="auth-card__field">
          <span>Password</span>
          <input
            type="password"
            name="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => {
              setPassword(e.target.value)
              if (error) clearError()
            }}
          />
        </label>

        <button type="submit" className="auth-card__submit" disabled={submitting}>
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
    </div>
  )
}