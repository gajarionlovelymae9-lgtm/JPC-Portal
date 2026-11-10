import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AdminAchievementsPage from './AdminAchievementsPage'

function json(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  }
}

const META = { current_page: 1, last_page: 1, per_page: 12, total: 1 }

const OPTIONS = {
  years: [{ value: 2026, label: '2026' }],
  months: [
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
  ],
  departments: [{ id: 1, name: 'Information Technology' }],
  academic_years: [{ id: 1, name: 'AY 2025-2026' }],
  categories: [{ value: 'Performance', label: 'Performance' }],
  statuses: [
    { value: 'pending', label: 'Pending review' },
    { value: 'verified', label: 'Verified' },
    { value: 'rejected', label: 'Rejected' },
  ],
  badges: [{ id: 7, name: 'Top Performer', code: 'TOP_PERFORMER', icon: 'award', is_active: true }],
}

const SUMMARIES = {
  cards: {
    total_active_badges: 10,
    total_achievements_awarded: 3,
    pending_reviews: 1,
    employees_recognized: 2,
  },
  top_employees: [
    { rank: 1, employee_id: 1, name: 'Ana Santos', employee_number: 'EMP-001', department: 'IT', total: 2 },
    { rank: 1, employee_id: 2, name: 'Ben Cruz', employee_number: 'EMP-002', department: 'IT', total: 2 },
    { rank: 2, employee_id: 3, name: 'Carla Reyes', employee_number: 'EMP-003', department: 'HR', total: 1 },
  ],
  monthly_counts: [
    { year: 2026, month: 4, total: 1 },
    { year: 2026, month: 5, total: 2 },
  ],
  department_distribution: [{ department: 'IT', total: 4 }],
  badge_frequency: [{ badge_id: 7, name: 'Top Performer', icon: 'award', category: 'Performance', total: 3 }],
  academic_year_summaries: [{ academic_year_id: 1, name: 'AY 2025-2026', total: 3, employees: 2 }],
}

const AWARDS = {
  items: [
    {
      id: 11,
      employee_id: 1,
      name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'Information Technology',
      badge: 'Top Performer',
      badge_code: 'TOP_PERFORMER',
      badge_icon: 'award',
      badge_description: 'Score of at least 95.',
      badge_category: 'Performance',
      academic_year: 'AY 2025-2026',
      period_start: '2026-05-01',
      period_end: '2026-05-31',
      awarded_at: '2026-06-01T00:00:00+08:00',
      awarded_by: 'System',
      verification_status: 'pending',
      verified_by: null,
      verified_at: null,
      rejection_reason: null,
      evidence: { overall_score: 96, month: 5, year: 2026 },
    },
  ],
  meta: META,
}

const ROLLUPS = {
  summary: {
    rollup_rows: 1,
    employees_recognised: 1,
    employee_of_the_month_count: 1,
    badge_awards: 3,
    distinct_badges_awarded: 2,
    avg_overall_score: 96,
    top_overall_score: 96,
  },
  leaders: [],
  items: [
    {
      id: 1,
      employee_id: 1,
      name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'Information Technology',
      period: '2026-05',
      month: 5,
      year: 2026,
      completion_rate: 100,
      on_time_rate: 100,
      approved_reports: 2,
      completed_tasks: 12,
      overall_score: 96,
      rank: 1,
      employee_of_the_month: true,
    },
  ],
  meta: META,
}

const DEFINITIONS = {
  items: [
    {
      id: 7,
      name: 'Top Performer',
      code: 'TOP_PERFORMER',
      description: 'Achieved an overall performance score of at least 95.',
      icon: 'award',
      category: 'Performance',
      criteria_type: 'overall_score',
      criteria_value: { threshold: 95, period: 'monthly' },
      is_active: true,
      allow_repeats: true,
      earned_count: 3,
      created_at: null,
      updated_at: null,
    },
  ],
  meta: META,
}

function stubApi(handlers: Record<string, unknown> = {}) {
  const defaults: Record<string, unknown> = {
    '/admin/achievements/options': OPTIONS,
    '/admin/achievements/summaries': SUMMARIES,
    '/admin/achievements/badges': AWARDS,
    '/admin/achievements/badge-definitions': DEFINITIONS,
  }
  const table: Record<string, unknown> = { ...defaults, ...handlers }

  vi.stubGlobal(
    'fetch',
    vi.fn((input: RequestInfo | URL, _init?: RequestInit) => {
      const url = String(input)
      if (url.includes('/sanctum/csrf-cookie')) {
        return Promise.resolve(json({ success: true }))
      }
      // Longest matching path wins so /badge-definitions beats /badges.
      const keys = Object.keys(table).filter((key) => url.includes(key)).sort((a, b) => b.length - a.length)
      const key = keys[0]
      if (key) {
        return Promise.resolve(json({ success: true, message: 'ok', data: table[key] }))
      }
      if (url.includes('/admin/achievements')) {
        return Promise.resolve(json({ success: true, message: 'ok', data: ROLLUPS }))
      }
      return Promise.resolve(json({ success: true, message: 'ok', data: null }))
    }),
  )
}

describe('AdminAchievementsPage', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows the four management cards from the summaries API, never invented', async () => {
    stubApi()
    render(<AdminAchievementsPage />)

    expect(await screen.findByText('Active badges')).toBeInTheDocument()
    // Numbers also appear in the tables below, so assert each value inside its card.
    const card = (label: string) => screen.getByText(label).closest('div.bg-white') as HTMLElement
    expect(card('Active badges')).toHaveTextContent('10')
    expect(card('Achievements awarded')).toHaveTextContent('3')
    expect(card('Pending reviews')).toHaveTextContent('1')
    expect(card('Employees recognized')).toHaveTextContent('2')
  })

  it('lists badge awards with their real verification status', async () => {
    stubApi()
    render(<AdminAchievementsPage />)

    // Scope to the badge awards panel — the rollups table below repeats the name.
    const awardsPanel = (await screen.findByText('Badge awards')).closest('section') as HTMLElement
    expect(await within(awardsPanel).findByText('Ana Santos')).toBeInTheDocument()
    expect(within(awardsPanel).getByText('Top Performer')).toBeInTheDocument()
    expect(within(awardsPanel).getByText('Pending')).toBeInTheDocument()
    expect(within(awardsPanel).getByText('EMP-001')).toBeInTheDocument()
  })

  it('shows the monthly rollup row with its recorded score and recognition', async () => {
    stubApi()
    render(<AdminAchievementsPage />)

    const rollupsPanel = (await screen.findByText('Monthly achievement rollups')).closest('section') as HTMLElement
    expect(await within(rollupsPanel).findByText('2026-05')).toBeInTheDocument()
    expect(within(rollupsPanel).getByText('Employee of the month')).toBeInTheDocument()
    expect(within(rollupsPanel).getByText('96')).toBeInTheDocument()
    // Completion and on-time are both 100%.
    expect(within(rollupsPanel).getAllByText('100.0%')).toHaveLength(2)
  })

  it('switches to the badge definitions tab and lists the catalogue', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))

    // Scope to the catalogue — the Status filter also has an "Active" option.
    const catalogue = (await screen.findByText('Badge catalogue')).closest('section') as HTMLElement
    expect(within(catalogue).getByText('Overall Score ≥ 95 per monthly')).toBeInTheDocument()
    expect(within(catalogue).getByText('Active')).toBeInTheDocument()
  })

  it('validates required fields in the create-badge modal before any network call', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))
    await user.click(await screen.findByRole('button', { name: 'New badge' }))
    await user.click(await screen.findByRole('button', { name: 'Create badge' }))

    expect(await screen.findByText('Name and code are required.')).toBeInTheDocument()
    const calls = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls
    expect(calls.some(([, init]) => (init as RequestInit | undefined)?.method === 'POST')).toBe(false)
  })

  it('rejects an unparseable criteria JSON instead of saving a broken badge', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))
    await user.click(await screen.findByRole('button', { name: 'New badge' }))

    await user.type(screen.getByPlaceholderText('e.g. Safety Star'), 'Safety Star')
    await user.type(screen.getByPlaceholderText('e.g. SAFETY_STAR'), 'SAFETY_STAR')

    // Guided form is the default; switch to the raw JSON editor to test the
    // malformed-input guard.
    await user.click(screen.getByRole('button', { name: 'Edit as JSON' }))
    const textareas = screen.getAllByRole('textbox').filter((node) => node.tagName === 'TEXTAREA')
    await user.clear(textareas[0])
    await user.type(textareas[0], '{{ not json')

    await user.click(screen.getByRole('button', { name: 'Create badge' }))
    expect(await screen.findByText('Criteria value must be valid JSON.')).toBeInTheDocument()
  })

  it('opens the award detail for a pending award and offers verification', async () => {
    stubApi({
      '/admin/achievements/awards/11': {
        id: 11,
        employee_id: 1,
        employee_name: 'Ana Santos',
        employee_number: 'EMP-001',
        department: 'Information Technology',
        badge_id: 7,
        badge_name: 'Top Performer',
        badge_code: 'TOP_PERFORMER',
        badge_icon: 'award',
        academic_year: 'AY 2025-2026',
        period_start: '2026-05-01',
        period_end: '2026-05-31',
        awarded_at: '2026-06-01T00:00:00+08:00',
        awarded_by: 'System',
        verification_status: 'pending',
        verified_by: null,
        verified_at: null,
        rejection_reason: null,
        evidence: { overall_score: 96, month: 5, year: 2026 },
      },
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click((await screen.findAllByRole('button', { name: 'View' }))[0])

    expect(await screen.findByText('Award detail')).toBeInTheDocument()
    await waitFor(() => {
      expect(screen.getByText('Overall performance score')).toBeInTheDocument()
    })
    expect(screen.getByText('96%')).toBeInTheDocument()
    // Raw JSON keys never reach the modal.
    expect(screen.queryByText(/"overall_score"/)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Verify' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reject…' })).toBeInTheDocument()
  })

  it('requires a reason before a rejection can be confirmed', async () => {
    stubApi({
      '/admin/achievements/awards/11': {
        id: 11,
        employee_id: 1,
        employee_name: 'Ana Santos',
        employee_number: 'EMP-001',
        department: 'Information Technology',
        badge_id: 7,
        badge_name: 'Top Performer',
        badge_code: 'TOP_PERFORMER',
        badge_icon: 'award',
        academic_year: 'AY 2025-2026',
        period_start: '2026-05-01',
        period_end: '2026-05-31',
        awarded_at: '2026-06-01T00:00:00+08:00',
        awarded_by: 'System',
        verification_status: 'pending',
        verified_by: null,
        verified_at: null,
        rejection_reason: null,
        evidence: null,
      },
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click((await screen.findAllByRole('button', { name: 'View' }))[0])
    await user.click(await screen.findByRole('button', { name: 'Reject…' }))

    const confirm = screen.getByRole('button', { name: 'Confirm rejection' })
    expect(confirm).toBeDisabled()

    await user.type(screen.getByPlaceholderText('Explain why this award is rejected'), 'Evidence did not match the criteria.')
    expect(confirm).toBeEnabled()
  })

  it('summarises only verified awards and keeps tie ranks equal', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Summaries' }))

    // Scope each assertion to its panel — numbers repeat across the summaries.
    const leaderboard = (await screen.findByText('Top employees')).closest('section') as HTMLElement
    const names = within(leaderboard).getAllByText(/Ana Santos|Ben Cruz/)
    expect(names).toHaveLength(2)
    // Both share rank 1; Carla is rank 2 (dense ranking).
    const ranks = within(leaderboard)
      .getAllByRole('row')
      .slice(1)
      .map((row) => within(row).getAllByRole('cell')[0]?.textContent)
    expect(ranks).toEqual(['1', '1', '2'])

    const years = (await screen.findByText('Academic year summaries')).closest('section') as HTMLElement
    expect(within(years).getByText('AY 2025-2026')).toBeInTheDocument()
    expect(within(years).getAllByText('2')).toHaveLength(1)
  })

  it('runs the month evaluation from the header action', async () => {
    stubApi({
      '/admin/achievements/evaluate': {
        period: '2026-05',
        year: 2026,
        month: 5,
        total_evaluated: 4,
        employee_of_the_month: 'Ana Santos',
      },
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Evaluate month' }))
    await user.click(await screen.findByRole('button', { name: 'Run evaluation' }))

    // Scope to the modal — the rollups table also shows "2026-05".
    const dialog = screen.getByRole('dialog')
    expect(await within(dialog).findByText('2026-05')).toBeInTheDocument()
    expect(within(dialog).getByText('4 employee(s) evaluated.')).toBeInTheDocument()
    expect(within(dialog).getByText('Employee of the month: Ana Santos')).toBeInTheDocument()

    const call = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.find(([url]) =>
      String(url).includes('/admin/achievements/evaluate'),
    )
    expect(call).toBeDefined()
    expect(call?.[1]).toMatchObject({ method: 'POST' })
  })

  it('renders an empty awards state instead of fake rows', async () => {
    stubApi({
      '/admin/achievements/badges': { items: [], meta: { ...META, total: 0 } },
    })
    render(<AdminAchievementsPage />)

    expect(await screen.findByText('No badge awards')).toBeInTheDocument()
    expect(screen.getByText('No badge grants match these filters.')).toBeInTheDocument()
  })

  /* ------------------------------------------------------------------ */
  /* Evidence snapshot + criteria value presentation / editing            */
  /* ------------------------------------------------------------------ */

  function awardDetailPayload(evidence: unknown) {
    return {
      id: 11,
      employee_id: 1,
      employee_name: 'Ana Santos',
      employee_number: 'EMP-001',
      department: 'Information Technology',
      badge_id: 7,
      badge_name: 'Top Performer',
      badge_code: 'TOP_PERFORMER',
      badge_icon: 'award',
      academic_year: 'AY 2025-2026',
      period_start: '2026-05-01',
      period_end: '2026-05-31',
      awarded_at: '2026-06-01T00:00:00+08:00',
      awarded_by: 'System',
      verification_status: 'pending',
      verified_by: null,
      verified_at: null,
      rejection_reason: null,
      evidence,
    }
  }

  it('renders nested evidence as readable metrics and hides seeder metadata', async () => {
    stubApi({
      '/admin/achievements/awards/11': awardDetailPayload({
        snapshot: {
          note: 'DEVELOPMENT SAMPLE DATA — determined by DevelopmentAchievementSeeder.',
          source: 'development.seeder',
          overall_score: null,
        },
        measured: {
          overall_score: '99.10',
          completion_rate: '100.00',
          on_time_rate: '98.50',
          measured_period: '2026-07-01 – 2026-07-31',
        },
        badge_criteria: { metric: 'completed_tasks', threshold: 10, period: 'monthly' },
      }),
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click((await screen.findAllByRole('button', { name: 'View' }))[0])

    expect(await screen.findByText('Overall performance score')).toBeInTheDocument()
    expect(screen.getByText('99.10%')).toBeInTheDocument()
    expect(screen.getByText('100.00%')).toBeInTheDocument()
    expect(screen.getByText('98.50%')).toBeInTheDocument()
    expect(screen.getByText('Measured period')).toBeInTheDocument()
    expect(screen.getByText('2026-07-01 – 2026-07-31')).toBeInTheDocument()
    expect(screen.getByText('Required value')).toBeInTheDocument()
    expect(screen.getAllByText('10').length).toBeGreaterThan(0)
    // Section keys and seeder metadata never leak as raw JSON.
    expect(screen.queryByText(/"measured"/)).not.toBeInTheDocument()
    expect(screen.queryByText(/DevelopmentAchievementSeeder/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/development\.seeder/i)).not.toBeInTheDocument()
  })

  it('shows the empty evidence state when only seeder metadata is stored', async () => {
    stubApi({
      '/admin/achievements/awards/11': awardDetailPayload({
        note: 'DEVELOPMENT SAMPLE DATA — determined by DevelopmentAchievementSeeder.',
        source: 'development.seeder',
        overall_score: null,
      }),
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click((await screen.findAllByRole('button', { name: 'View' }))[0])

    expect(
      await screen.findByText('No evidence snapshot recorded for this award.'),
    ).toBeInTheDocument()
    expect(screen.queryByText(/DEVELOPMENT SAMPLE DATA/i)).not.toBeInTheDocument()
  })

  it('edits a recognised criteria value with the guided form and saves the object', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))
    await user.click(await screen.findByRole('button', { name: 'Edit' }))

    const threshold = screen.getByLabelText(/^Threshold/)
    expect(threshold).toHaveValue(95)
    expect(screen.getByLabelText(/^Period/)).toHaveValue('monthly')
    expect(screen.queryByRole('textbox', { name: /Criteria value/ })).not.toBeInTheDocument()

    await user.clear(threshold)
    await user.type(threshold, '90')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    await waitFor(() => {
      const put = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.find(
        ([url, init]) =>
          String(url).includes('/admin/achievements/badge-definitions/7') &&
          (init as RequestInit | undefined)?.method === 'PUT',
      )
      expect(put).toBeDefined()
      const body = JSON.parse(String((put?.[1] as RequestInit).body))
      expect(body.criteria_value).toEqual({ threshold: 90, period: 'monthly' })
    })
  })

  it('rejects a negative threshold before saving', async () => {
    stubApi()
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))
    await user.click(await screen.findByRole('button', { name: 'Edit' }))

    const threshold = screen.getByLabelText(/^Threshold/)
    await user.clear(threshold)
    await user.type(threshold, '-5')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Threshold must be a non-negative number.')).toBeInTheDocument()
    const puts = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.filter(
      ([, init]) => (init as RequestInit | undefined)?.method === 'PUT',
    )
    expect(puts).toHaveLength(0)
  })

  it('falls back to JSON for custom criteria and never discards unknown properties', async () => {
    stubApi({
      '/admin/achievements/badge-definitions': {
        items: [
          {
            ...DEFINITIONS.items[0],
            criteria_value: { threshold: 95, period: 'monthly', lookback_months: 3 },
          },
        ],
        meta: META,
      },
    })
    const user = userEvent.setup()
    render(<AdminAchievementsPage />)

    await user.click(await screen.findByRole('button', { name: 'Badge definitions' }))
    await user.click(await screen.findByRole('button', { name: 'Edit' }))

    const editor = screen.getByRole('textbox', { name: /Criteria value/ })
    expect(editor).toHaveValue(JSON.stringify({ threshold: 95, period: 'monthly', lookback_months: 3 }, null, 2))

    // Guided form cannot represent the custom key, so it offers no silent loss.
    await user.click(screen.getByRole('button', { name: 'Use guided form' }))
    expect(
      await screen.findByText(/custom properties that the guided form cannot represent/i),
    ).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Save changes' }))
    await waitFor(() => {
      const put = (globalThis.fetch as ReturnType<typeof vi.fn>).mock.calls.find(
        ([url, init]) =>
          String(url).includes('/admin/achievements/badge-definitions/7') &&
          (init as RequestInit | undefined)?.method === 'PUT',
      )
      expect(put).toBeDefined()
      const body = JSON.parse(String((put?.[1] as RequestInit).body))
      expect(body.criteria_value).toEqual({ threshold: 95, period: 'monthly', lookback_months: 3 })
    })
  })
})
