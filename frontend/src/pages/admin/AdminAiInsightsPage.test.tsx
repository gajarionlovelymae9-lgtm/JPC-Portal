import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminAiInsightsPage from './AdminAiInsightsPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const SAMPLE_MESSAGE =
  'Development sample records are available for testing but are excluded from live analytics and employee performance metrics.'

const SAMPLE_VALIDATION = {
  total: 10,
  avg_score: 91.5,
  avg_confidence: 90,
  passed: 9,
  warning: 1,
  failed: 0,
  present: true,
}

const LIVE_VALIDATION = {
  has_live_data: false,
  total: 0,
  avg_score: null,
  avg_confidence: null,
  passed: 0,
  warning: 0,
  failed: 0,
  score_scale: '0..1 fraction, exposed as 0..100 here',
  is_sample_data: true,
  provenance_note:
    'Development/unverified validation rows (for example provider "jpc.sample") are stored but excluded from the live KPIs above.',
  sample: SAMPLE_VALIDATION,
}

const PROVIDER = {
  configured_provider: 'fake',
  model: 'development-sample',
  endpoint_configured: false,
  api_key_configured: false,
  configured: false,
  connected: false,
  live: false,
  data_source: 'no_live_data',
  data_source_label: 'No Data',
  last_successful_ai_request: null,
  last_successful_analysis: null,
  is_sample_data: true,
  sample_data_present: true,
  note: 'The configured AI provider is not a live engine.',
}

const SAMPLE_RUN = {
  id: 90,
  report_id: 10,
  report: 'Development sample report',
  analysis_type: 'report_analysis',
  provider: 'jpc.sample',
  model: 'development-sample',
  status: 'completed',
  error_message: null,
  started_at: '2026-10-01T08:00:00Z',
  completed_at: '2026-10-01T08:00:05Z',
  is_sample_data: true,
}

const LIVE_RUN = {
  ...SAMPLE_RUN,
  id: 42,
  report: 'Live analyzed report',
  provider: 'groq',
  model: 'openai/gpt-oss-120b',
  is_sample_data: false,
}

const INSIGHT = {
  id: 7,
  type: 'performance',
  type_label: 'Performance',
  content: 'Live performance insight from authorized records.',
  metadata: {},
  employee_id: null,
  employee: null,
  department_id: null,
  department: null,
  generated_at: '2026-10-02T09:00:00Z',
  scope: 'institution',
  is_sample_data: false,
  provenance: null,
}

function buildInsights(overrides: Record<string, unknown> = {}) {
  return {
    insights: [],
    available: false,
    message: 'No AI insight available',
    generated: false,
    counts: {
      total: 0,
      by_type: [{ type: 'performance', label: 'Performance', total: 0 }],
    },
    reports_analyzed: 0,
    validation: LIVE_VALIDATION,
    recent_runs: [],
    sample_runs: [SAMPLE_RUN],
    sample_data: {
      present: true,
      validation: SAMPLE_VALIDATION,
      analysis_runs: [SAMPLE_RUN],
    },
    provider_status: PROVIDER,
    filters_applied: {},
    ...overrides,
  }
}

const OPTIONS = {
  types: [{ value: 'performance', label: 'Performance' }],
  departments: [],
  employees: [{ value: 1, label: 'Jane Dela Cruz' }],
  reports: [{ value: 10, label: 'Development sample report' }],
}

function stubApi(data: Record<string, unknown> = buildInsights()) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input)
    const method = init?.method ?? 'GET'

    if (url.includes('/admin/ai-insight-options')) {
      return json({ success: true, message: 'ok', data: OPTIONS })
    }

    if (url.includes('/admin/ai-insights/generate') && method === 'POST') {
      return json({
        success: true,
        message: 'Insight generated',
        data: {
          insight: INSIGHT,
          human_verification_required: true,
          advisory_only: true,
        },
      })
    }

    if (url.includes('/admin/ai-insights')) {
      return json({ success: true, message: 'AI insights loaded', data })
    }

    return json({ success: true, message: 'ok', data: null })
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

/** Reads the value rendered inside the labelled KPI card. */
function statValue(label: string): string | null {
  const card = screen.getByText(label).closest('div.bg-white')
  if (!card) {
    throw new Error(`KPI card for "${label}" not found`)
  }
  const values = within(card).getAllByText((_, element) => element?.tagName === 'P')
  const match = values
    .map((element) => element.textContent?.trim() ?? '')
    .find((text) => text !== label && text !== '')

  return match ?? null
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('AdminAiInsightsPage sample data separation', () => {
  it('renders one compact Development Sample Data Notice with the exact required copy', async () => {
    stubApi()
    render(<AdminAiInsightsPage />)

    expect(await screen.findByText('Development Sample Data Notice')).toBeInTheDocument()
    expect(screen.getByText(SAMPLE_MESSAGE)).toBeInTheDocument()

    // Only one main notice is rendered (the old duplicate is gone).
    expect(screen.getAllByText('Development Sample Data Notice')).toHaveLength(1)
    expect(screen.queryByText(/Development sample data is stored but excluded/i)).not.toBeInTheDocument()

    // The compact amber banner uses the shared design-system classes rather
    // than a large alert block.
    const notice = screen.getByText('Development Sample Data Notice').closest('div.bg-amber-50')
    expect(notice).not.toBeNull()
    expect(notice?.className).toContain('border-amber-200')
  })

  it('excludes sample validation rows from every live KPI', async () => {
    stubApi(buildInsights())
    render(<AdminAiInsightsPage />)

    await screen.findByText('Development Sample Data Notice')

    expect(statValue('Insights stored')).toBe('0')
    expect(statValue('Reports analyzed')).toBe('0')
    expect(statValue('Validations')).toBe('0')
    expect(statValue('Passed')).toBe('0')
    expect(statValue('Warnings')).toBe('0')
    expect(statValue('Failed')).toBe('0')
    // No live score exists, so the KPI must not show the 91.5% sample score.
    expect(statValue('Average score')).not.toContain('%')
    expect(screen.queryByText('91.50%')).not.toBeInTheDocument()
  })

  it('counts live validation rows without letting sample rows affect the totals', async () => {
    stubApi(
      buildInsights({
        validation: {
          ...LIVE_VALIDATION,
          has_live_data: true,
          total: 2,
          avg_score: 80,
          avg_confidence: 85,
          passed: 1,
          warning: 1,
          failed: 0,
        },
        reports_analyzed: 1,
      }),
    )
    render(<AdminAiInsightsPage />)

    await screen.findByText('Development Sample Data Notice')

    expect(statValue('Reports analyzed')).toBe('1')
    expect(statValue('Validations')).toBe('2')
    expect(statValue('Passed')).toBe('1')
    expect(statValue('Warnings')).toBe('1')
    expect(statValue('Failed')).toBe('0')
    expect(statValue('Average score')).toBe('80.00%')
    // The sample average is never presented as a live KPI.
    expect(screen.queryByText('91.50%')).not.toBeInTheDocument()
  })

  it('handles an empty live dataset without inventing insights or scores', async () => {
    stubApi(
      buildInsights({
        insights: [],
        available: false,
        message: 'No AI insight available',
        generated: false,
      }),
    )
    render(<AdminAiInsightsPage />)

    expect(await screen.findByText('No AI insight available')).toBeInTheDocument()
    expect(
      screen.getByText(
        'The ai_insights table has no verified live insight rows matching these filters. Nothing is substituted or estimated.',
      ),
    ).toBeInTheDocument()
    expect(screen.getByText('No live analysis runs recorded')).toBeInTheDocument()
    expect(statValue('Insights stored')).toBe('0')
    expect(statValue('Average score')).not.toContain('%')
  })

  it('labels sample runs explicitly while live runs stay unlabelled', async () => {
    stubApi(
      buildInsights({
        available: true,
        generated: true,
        insights: [INSIGHT],
        counts: {
          total: 1,
          by_type: [{ type: 'performance', label: 'Performance', total: 1 }],
        },
        recent_runs: [LIVE_RUN],
      }),
    )
    render(<AdminAiInsightsPage />)

    const samplePanel = (await screen.findByText('Development sample runs')).closest('section')
    expect(samplePanel).not.toBeNull()
    expect(within(samplePanel as HTMLElement).getAllByText('Sample').length).toBeGreaterThan(0)
    expect(within(samplePanel as HTMLElement).getByText(/jpc\.sample/)).toBeInTheDocument()
    expect(
      within(samplePanel as HTMLElement).getByText('Development sample report'),
    ).toBeInTheDocument()

    const livePanel = screen.getByText('Recent live analysis runs').closest('section')
    expect(livePanel).not.toBeNull()
    expect(within(livePanel as HTMLElement).queryByText('Sample')).not.toBeInTheDocument()
    expect(within(livePanel as HTMLElement).getByText('Live analyzed report')).toBeInTheDocument()
    expect(within(livePanel as HTMLElement).getByText(/groq/)).toBeInTheDocument()

    // Live insight content is shown, not sample content.
    expect(screen.getByText('Live performance insight from authorized records.')).toBeInTheDocument()
  })

  it('reports provider connection from recorded verification state, never hardcoded', async () => {
    stubApi(
      buildInsights({
        sample_runs: [],
        sample_data: { present: false, validation: { ...SAMPLE_VALIDATION, present: false }, analysis_runs: [] },
        validation: {
          ...LIVE_VALIDATION,
          is_sample_data: false,
          sample: { ...SAMPLE_VALIDATION, present: false, total: 0 },
        },
        provider_status: {
          ...PROVIDER,
          is_sample_data: false,
          sample_data_present: false,
        },
      }),
    )
    render(<AdminAiInsightsPage />)

    expect(await screen.findByText('Not Connected')).toBeInTheDocument()
    expect(screen.getByText('No Data')).toBeInTheDocument()
    expect(screen.getByText('None')).toBeInTheDocument()
    expect(screen.getByText(PROVIDER.note)).toBeInTheDocument()
    // No dedicated sample notice when nothing sample is stored.
    expect(screen.queryByText('Development Sample Data Notice')).not.toBeInTheDocument()
  })

  it('shows Connected only when the payload records a verified live request', async () => {
    const lastSuccess = '2026-11-01T10:15:00Z'
    stubApi(
      buildInsights({
        sample_runs: [],
        sample_data: { present: false, validation: { ...SAMPLE_VALIDATION, present: false }, analysis_runs: [] },
        validation: {
          ...LIVE_VALIDATION,
          is_sample_data: false,
          sample: { ...SAMPLE_VALIDATION, present: false, total: 0 },
        },
        provider_status: {
          ...PROVIDER,
          configured_provider: 'groq',
          model: 'openai/gpt-oss-120b',
          endpoint_configured: true,
          api_key_configured: true,
          configured: true,
          connected: true,
          live: true,
          data_source: 'live_database',
          data_source_label: 'Live Database',
          last_successful_ai_request: lastSuccess,
          last_successful_analysis: lastSuccess,
          is_sample_data: false,
          sample_data_present: false,
          note: null,
        },
      }),
    )
    render(<AdminAiInsightsPage />)

    expect(await screen.findByText('Connected')).toBeInTheDocument()
    expect(screen.getByText('Live Database')).toBeInTheDocument()
    expect(screen.queryByText('Not Connected')).not.toBeInTheDocument()
    // Secrets are never rendered.
    expect(screen.queryByText(/api key/i)).not.toBeInTheDocument()
  })

  it('keeps filters and insight generation operational', async () => {
    const fetchMock = stubApi()
    const user = userEvent.setup()
    render(<AdminAiInsightsPage />)

    await screen.findByText('Development Sample Data Notice')

    // Changing a filter reloads the endpoint with the selected scope.
    await user.selectOptions(screen.getByLabelText('Scope'), 'institution')
    await waitFor(() => {
      expect(fetchMock.mock.calls.some(([input]) => String(input).includes('scope=institution'))).toBe(true)
    })

    // Generating an insight posts to the real endpoint and reports success.
    await user.selectOptions(screen.getByLabelText('Employee'), '1')
    await user.click(screen.getByRole('button', { name: 'Generate insight' }))

    expect(await screen.findByText(/Insight #7 generated and stored/i)).toBeInTheDocument()

    const generateCall = fetchMock.mock.calls.find(
      ([input, init]) =>
        String(input).includes('/admin/ai-insights/generate') && (init?.method ?? 'GET') === 'POST',
    )
    expect(generateCall).toBeTruthy()
  })
})
