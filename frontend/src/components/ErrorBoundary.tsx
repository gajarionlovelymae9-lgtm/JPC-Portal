import { Component, type ErrorInfo, type ReactNode } from 'react'

interface ErrorBoundaryProps {
  children: ReactNode
}

interface ErrorBoundaryState {
  hasError: boolean
  message: string
}

/**
 * ErrorBoundary - inihahanda bilang catch-all para sa buong React app.
 * Kailangan ito para ang isang runtime error sa isang page ay HINDI
 * mag-render ng blangko (white screen); sa halip ay ipapakita nito ang
 * babala sa screen na may aktwal na mensahe para ma-debug agad nang hindi
 * tinitingnan ang console.
 */
export default class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false, message: '' }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, message: error.message || 'Unknown error' }
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    console.error('[ErrorBoundary]', error, info.componentStack)
  }

  render(): ReactNode {
    if (this.state.hasError) {
      return (
        <main style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '2rem', background: '#fff' }}>
          <div style={{ maxWidth: '560px', width: '100%', fontFamily: 'Segoe UI, sans-serif' }}>
            <h1 style={{ fontSize: '1.5rem', margin: '0 0 0.5rem', color: '#b91c1c' }}>May nangyaring mali sa page</h1>
            <p style={{ color: '#374151', margin: '0 0 1rem' }}>
              Ang isang component ay nag-crash habang nag-render. Nasa ibaba ang teknikal na detalye para sa debugging — hindi ka nito pinipigilan sa ibang bahagi ng portal.
            </p>
            <pre style={{ background: '#f3f4f6', padding: '1rem', borderRadius: '8px', overflowX: 'auto', fontSize: '0.8rem', color: '#111827' }}>
              {this.state.message}
            </pre>
            <button
              onClick={() => { this.setState({ hasError: false, message: '' }); window.location.reload() }}
              style={{ marginTop: '1rem', padding: '0.5rem 1rem', background: '#2563eb', color: '#fff', border: 'none', borderRadius: '6px', cursor: 'pointer' }}
            >
              Subukang muli
            </button>
          </div>
        </main>
      )
    }
    return this.props.children
  }
}
