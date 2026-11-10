import { describe, expect, it, vi } from 'vitest'

import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

import HrAchievementsPage from './HrAchievementsPage'

vi.mock('../../services/hrAchievementService', () => ({
  fetchHrAchievements: vi.fn().mockResolvedValue({
    data: {
      items: [
        {
          id: 1,
          employee_name: 'Test Employee',
          employee_number: 'EMP-001',
          department: 'IT',
          achievement_title: 'Star Award',
          badge_code: 'STAR',
          badge_category: 'Performance',
          badge_tier: 'Gold',
          achievement_date: '2026-10-01',
          verification_status: 'pending',
        },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
      cards: { total_recognitions: 1, verified: 0, pending_verification: 1, this_month: 1 },
    },
  }),
  fetchHrAchievementOptions: vi.fn().mockResolvedValue({
    data: {
      departments: [],
      academic_years: [],
      badges: [],
      categories: [],
      tiers: [],
      years: [{ value: 2026, label: '2026' }],
      months: [],
      statuses: [
        { value: 'pending', label: 'Pending' },
        { value: 'verified', label: 'Verified' },
        { value: 'rejected', label: 'Rejected' },
      ],
    },
  }),
  fetchHrAchievementDetail: vi.fn(),
  verifyHrAchievement: vi.fn(),
  rejectHrAchievement: vi.fn(),
  fetchHrAchievementReports: vi.fn(),
  exportHrAchievements: vi.fn(),
}))

describe('HrAchievementsPage', () => {
  it('renders HR achievements page with cards and records', async () => {
    render(
      <MemoryRouter>
        <HrAchievementsPage />
      </MemoryRouter>,
    )

    await waitFor(() => {
      expect(screen.getByText('HR Achievements & Badges')).toBeInTheDocument()
    })
    expect(screen.getByText('Total recognitions')).toBeInTheDocument()
    expect(screen.getByText('Test Employee')).toBeInTheDocument()
    expect(screen.getByText('Star Award')).toBeInTheDocument()
  })
})

/* ------------------------------------------------------------------ */
/* Recognition detail modal — evidence / criteria snapshot rendering    */
/* ------------------------------------------------------------------ */

async function openDetailModal(detailPayload: Record<string, unknown>) {
  const { fetchHrAchievementDetail } = await import('../../services/hrAchievementService')
  vi.mocked(fetchHrAchievementDetail).mockResolvedValueOnce({
    data: detailPayload,
  } as never)

  render(
    <MemoryRouter>
      <HrAchievementsPage />
    </MemoryRouter>,
  )
  await screen.findByText('Test Employee')
  fireEvent.click(await screen.findByRole('button', { name: 'View' }))
}

describe('recognition detail modal — evidence rendering', () => {
  it('shows "No evidence details available" and hides development metadata entirely', async () => {
    // Mirrors the real seeder-sourced records: evidence is null and the
    // criteria snapshot carries only development source metadata.
    await openDetailModal({
      id: 1,
      employee_name: 'Test Employee',
      employee_number: 'EMP-001',
      achievement_title: 'Star Award',
      badge_code: 'TOP_PERFORMER',
      badge_category: 'PERFORMANCE',
      badge_tier: 'gold',
      verification_status: 'pending',
      evidence: null,
      criteria_snapshot: {
        note: 'DEVELOPMENT SAMPLE DATA — determined by DevelopmentAchievementSeeder.',
        source: 'development.seeder',
        overall_score: null,
      },
    })

    expect(await screen.findByText('No evidence details available')).toBeInTheDocument()
    expect(screen.queryByText(/DevelopmentAchievementSeeder/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/development\.seeder/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/DEVELOPMENT SAMPLE DATA/i)).not.toBeInTheDocument()
  })

  it('renders meaningful evidence with readable labels and no raw JSON', async () => {
    await openDetailModal({
      id: 2,
      employee_name: 'Test Employee',
      employee_number: 'EMP-001',
      achievement_title: 'Star Award',
      badge_code: 'TOP_PERFORMER',
      verification_status: 'verified',
      evidence: {
        overall_score: 92.55,
        note: 'Outstanding Q3 delivery.',
        source: 'development.seeder',
        criteria: ['Completed 12 deliverables on time', 'Peer rating 4.8'],
        extra_blank: '',
        extra_null: null,
      },
    })

    expect(await screen.findByText('Overall performance score')).toBeInTheDocument()
    expect(screen.getByText('92.55%')).toBeInTheDocument()
    expect(screen.getByText('Outstanding Q3 delivery.')).toBeInTheDocument()
    expect(screen.getByText('Completed 12 deliverables on time')).toBeInTheDocument()
    expect(screen.getByText('Peer rating 4.8')).toBeInTheDocument()
    // Internal metadata stays hidden even inside a meaningful snapshot.
    expect(screen.queryByText(/development\.seeder/i)).not.toBeInTheDocument()
    // Raw JSON never leaks into the modal.
    expect(screen.queryByText(/overall_score/)).not.toBeInTheDocument()
  })

  it('renders the enriched measured evidence and badge criteria payload', async () => {
    // Mirrors the new backend contract: measured values from
    // employee_performance_records + the badge's awarding rule.
    await openDetailModal({
      id: 3,
      employee_name: 'Test Employee',
      employee_number: 'EMP-001',
      achievement_title: 'Task Champion',
      badge_code: 'TASK_CHAMPION',
      verification_status: 'verified',
      evidence: {
        snapshot: {
          note: 'DEVELOPMENT SAMPLE DATA — determined by DevelopmentAchievementSeeder.',
          source: 'development.seeder',
          overall_score: null,
        },
        measured: {
          overall_score: '99.10',
          completion_rate: '100.00',
          on_time_rate: '99.00',
          completed_tasks: 12,
          measured_period: '2026-07-01 – 2026-07-31',
        },
        badge_criteria: {
          metric: 'completed_tasks',
          threshold: 10,
          period: 'monthly',
        },
      },
    })

    expect(await screen.findByText('Overall performance score')).toBeInTheDocument()
    expect(screen.getByText('99.10%')).toBeInTheDocument()
    expect(screen.getByText('100.00%')).toBeInTheDocument()
    expect(screen.getByText('99.00%')).toBeInTheDocument()
    expect(screen.getAllByText('Completed Tasks').length).toBeGreaterThan(0)
    expect(screen.getByText('12')).toBeInTheDocument()
    expect(screen.getByText('Measured period')).toBeInTheDocument()
    expect(screen.getByText('Required value')).toBeInTheDocument()
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getAllByText('Monthly').length).toBeGreaterThan(0)
    // Dev seeder metadata stays hidden in the new shape too.
    expect(screen.queryByText(/DEVELOPMENT SAMPLE DATA/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/development\.seeder/i)).not.toBeInTheDocument()
  })
})
