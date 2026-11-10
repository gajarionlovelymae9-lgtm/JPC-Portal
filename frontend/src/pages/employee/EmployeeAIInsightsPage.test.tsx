import { render, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import EmployeeAIInsightsPage from './EmployeeAIInsightsPage'
import type { EmployeeAiInsights } from '../../services/employeePortalService'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const TRANSPARENCY_METRICS = [
  {
    key: 'task_completion',
    label: 'Task Completion',
    available: true,
    configured_weight: 30,
    raw_value: 100,
    applied_weight: 60,
    contribution: 60,
    excluded_reason: null,
  },
  {
    key: 'report_completion',
    label: 'Report Completion',
    available: true,
    configured_weight: 20,
    raw_value: 0,
    applied_weight: 40,
    contribution: 0,
    excluded_reason: null,
  },
  {
    key: 'on_time_submission',
    label: 'On-Time Submission',
    available: false,
    configured_weight: 20,
    raw_value: null,
    applied_weight: null,
    contribution: null,
    excluded_reason: 'No records exist for this metric, so it was excluded and the remaining weights were renormalised.',
  },
  {
    key: 'report_approval',
    label: 'Report Approval',
    available: false,
    configured_weight: 15,
    raw_value: null,
    applied_weight: null,
    contribution: null,
    excluded_reason: 'No records exist for this metric, so it was excluded and the remaining weights were renormalised.',
  },
  {
    key: 'target_achievement',
    label: 'Target Achievement',
    available: false,
    configured_weight: 15,
    raw_value: null,
    applied_weight: null,
    contribution: null,
    excluded_reason: 'No records exist for this metric, so it was excluded and the remaining weights were renormalised.',
  },
]

function payload(overrides: Partial<EmployeeAiInsights> = {}): EmployeeAiInsights {
  return {
    employee: { name: 'Juan Dela Cruz', department: 'IT', position: 'Faculty' },
    period: { year: 2026, month: 9, label: 'September 2026', start: '2026-09-01', end: '2026-09-30' },
    generated_at: '2026-09-15T10:00:00+08:00',
    available: true,
    no_data_reason: null,
    score: { value: 60, label: 'Needs Improvement', color: 'orange', basis: 'v1.0', metrics_used: 2, metrics_excluded: 3 },
    metrics: [
      {
        key: 'task_completion',
        label: 'Task Completion',
        value: 100,
        unit: '%',
        weight: 30,
        available: true,
        basis: 'of assigned tasks completed',
        unavailable_reason: null,
      },
      {
        key: 'report_completion',
        label: 'Report Completion',
        value: 0,
        unit: '%',
        weight: 20,
        available: true,
        basis: 'of expected reports submitted',
        unavailable_reason: null,
      },
      {
        key: 'on_time_submission',
        label: 'On-Time Submission',
        value: null,
        unit: '%',
        weight: 20,
        available: false,
        basis: 'of submitted reports on time',
        unavailable_reason: 'No records exist to measure this metric for the selected period.',
      },
    ],
    performance_trend: [{ period_label: 'Sep 2026', year: 2026, month: 9, score: 60, rating: 'Needs Improvement' }],
    productivity_trend: [{ period_label: 'Sep 2026', year: 2026, month: 9, rate: 100 }],
    alerts: [
      {
        key: 'overdue_tasks',
        severity: 'medium',
        title: 'Overdue tasks require attention',
        detail: '1 assigned task(s) are past their effective due date and not yet complete.',
        evidence: { overdue_tasks: 1 },
      },
    ],
    summary: 'Your weighted AI Performance Score for this period is 60 out of 100.',
    recommendations: [
      {
        key: 'report_completion',
        title: 'Submit outstanding reports',
        detail: 'Reports are still expected for work already assigned in this period.',
        evidence: { metric: 'report_completion', value: 0 },
      },
    ],
    forecast: {
      value: null,
      confidence: 'insufficient_data',
      method: null,
      based_on_months: 1,
      required_months: 3,
      reason: 'A projection needs at least 3 months of measured performance; 1 month is available.',
      needed: 'Keep completing tasks and submitting reports so additional monthly periods become measurable.',
    },
    score_transparency: {
      formula: 'Weighted average of the measured metrics: Σ (metric rate × renormalised weight).',
      weights_source: 'config/performance.php',
      calculation_version: 'v1.0',
      basis: 'v1.0',
      metrics: TRANSPARENCY_METRICS,
      metrics_used: 2,
      metrics_excluded: 3,
      available: true,
    },
    disclaimer: 'AI insights are advisory and are calculated only from recorded system data.',
    ...overrides,
  }
}

function stubApi(data: EmployeeAiInsights | null, status = 200) {
  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL) => {
      const url = String(input)
      if (url.includes('/employee/ai-insights')) {
        return Promise.resolve(
          status === 200
            ? json({ success: true, message: 'ok', data })
            : json({ success: false, message: 'failed', errors: null }, status),
        )
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

describe('EmployeeAIInsightsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('requests the insights endpoint without ever sending an employee id', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    const call = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0] as string
    expect(call).toContain('/employee/ai-insights')
    expect(call).not.toContain('employee_id')
  })

  it('shows a loading state before data arrives', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    expect(screen.getByText('Analysing your recorded activity...')).toBeInTheDocument()
    expect(await screen.findByRole('heading', { name: 'My AI Insights' })).toBeInTheDocument()
  })

  it('renders the measured score, summary and alerts', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    const scoreCard = within(screen.getByRole('region', { name: 'AI Performance Score' }))
    expect(scoreCard.getByText('60.00')).toBeInTheDocument()
    expect(scoreCard.getByText('Needs Improvement')).toBeInTheDocument()
    expect(screen.getByText(/weighted AI Performance Score/)).toBeInTheDocument()
    expect(screen.getByTestId('alert-overdue_tasks')).toBeInTheDocument()
    expect(screen.getByTestId('recommendation-report_completion')).toBeInTheDocument()
  })

  it('shows a real measured 0% distinctly from an unmeasurable metric', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    // Report completion was measured and genuinely is 0%.
    const measured = screen.getByTestId('metric-report_completion')
    expect(measured).toHaveTextContent('0.0%')
    expect(measured).not.toHaveTextContent('No data')

    // On-time submission has no records, so it must read as no data, not 0%.
    const unmeasured = screen.getByTestId('metric-on_time_submission')
    expect(unmeasured).toHaveTextContent('No data')
    expect(unmeasured).toHaveTextContent('No records exist to measure this metric')
    expect(unmeasured).not.toHaveTextContent('0.0%')
  })

  it('discloses configured, renormalised weights and contributions', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    const taskRow = screen.getByTestId('transparency-task_completion')
    expect(taskRow).toHaveTextContent('100.00%')
    expect(taskRow).toHaveTextContent('30.00%')
    expect(taskRow).toHaveTextContent('60.00%')
    expect(taskRow).toHaveTextContent('60.00')

    const excludedRow = screen.getByTestId('transparency-target_achievement')
    expect(excludedRow).toHaveTextContent('No data')
    expect(excludedRow).toHaveTextContent('excluded')

    expect(screen.getByText(/config\/performance\.php/)).toBeInTheDocument()
    expect(screen.getByText(/2 of 5 metrics included/)).toBeInTheDocument()
  })

  it('refuses to show a projection and explains what is missing', async () => {
    stubApi(payload())
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    expect(screen.getByText('Projection unavailable')).toBeInTheDocument()
    expect(screen.getByText(/needs at least 3 months/)).toBeInTheDocument()
    expect(screen.getByText('Months measured: 1 of 3 required')).toBeInTheDocument()
    expect(screen.getByText(/What would help/)).toBeInTheDocument()
  })

  it('labels a projection with its method and confidence when one exists', async () => {
    stubApi(
      payload({
        forecast: {
          value: 64.5,
          confidence: 'low',
          method: 'linear_trend',
          based_on_months: 3,
          required_months: 3,
          direction: 'improving',
          average_score: 62,
          reason: 'Least-squares projection across 3 measured months.',
          needed: null,
        },
      }),
    )
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    expect(screen.getByText('64.50')).toBeInTheDocument()
    expect(screen.getByText(/low confidence/)).toBeInTheDocument()
    expect(screen.getByText('linear_trend')).toBeInTheDocument()
    expect(screen.getByText(/Least-squares projection/)).toBeInTheDocument()
  })

  it('renders an empty state instead of a score when nothing is measurable', async () => {
    const base = payload()

    stubApi(
      payload({
        available: false,
        no_data_reason: 'No task assignments, report submissions, reviewed reports, or targets are recorded.',
        score: { value: null, label: null, color: null, basis: null, metrics_used: 0, metrics_excluded: 5 },
        summary: null,
        alerts: [],
        recommendations: [],
        performance_trend: [],
        productivity_trend: [],
        metrics: base.metrics.map((m) => ({
          ...m,
          available: false,
          value: null,
          unavailable_reason: 'No records exist to measure this metric for the selected period.',
        })),
        score_transparency: {
          ...base.score_transparency,
          available: false,
          metrics_used: 0,
          metrics_excluded: 5,
          metrics: TRANSPARENCY_METRICS.map((m) => ({
            ...m,
            available: false,
            raw_value: null,
            applied_weight: null,
            contribution: null,
          })),
        },
      }),
    )
    render(<EmployeeAIInsightsPage />)

    await screen.findByRole('heading', { name: 'My AI Insights' })

    expect(screen.getByRole('status')).toHaveTextContent('Not enough data yet')
    expect(screen.getByText('Not enough data to score')).toBeInTheDocument()
    expect(screen.queryByText('Summary')).not.toBeInTheDocument()
    expect(screen.getByTestId('metric-task_completion')).toHaveTextContent('No data')
  })

  it('surfaces a request failure', async () => {
    stubApi(null, 500)
    render(<EmployeeAIInsightsPage />)

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument())
    expect(screen.getByRole('alert')).toHaveTextContent(/Unable to reach the AI insights service/)
  })
})
