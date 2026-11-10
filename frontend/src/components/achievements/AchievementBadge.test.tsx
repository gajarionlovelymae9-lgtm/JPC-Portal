import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { LucideIcon } from 'lucide-react'
import {
  Award,
  BadgeCheck,
  CalendarCheck,
  CircleCheck,
  Clock,
  FileCheck,
  Goal,
  ShieldCheck,
  Star,
  Target,
  TrendingUp,
  Trophy,
  Users,
  Zap,
} from 'lucide-react'
import { AchievementBadgeCard, resolveAchievementVisual } from './AchievementBadge'

/** The exact badge-to-icon contract requested for the achievements page. */
const ICON_CASES: Array<{ name: string; Icon: LucideIcon }> = [
  { name: 'Employee of the Month', Icon: Trophy },
  { name: 'On-Time Hero', Icon: Clock },
  { name: 'Outstanding Employee', Icon: Star },
  { name: 'Perfect Submission', Icon: CircleCheck },
  { name: 'Report Excellence', Icon: FileCheck },
  { name: 'Top Performer', Icon: Award },
  { name: 'Task Champion', Icon: Target },
  { name: 'Consistent Performer', Icon: CalendarCheck },
  { name: 'Fast Finisher', Icon: Zap },
  { name: 'Improvement Star', Icon: TrendingUp },
  { name: 'Quality Master', Icon: ShieldCheck },
  { name: 'Team Player', Icon: Users },
  { name: 'Goal Achiever', Icon: Goal },
  { name: '100% Completion', Icon: BadgeCheck },
]

describe('achievement badge icons', () => {
  it.each(ICON_CASES)('renders the $name icon as an outline svg', ({ name, Icon }) => {
    expect(resolveAchievementVisual({ name }).Icon).toBe(Icon)

    const { container, unmount } = render(
      <AchievementBadgeCard badge={{ name, earned: false, progress: null }} />,
    )
    expect(container.querySelector('svg')).not.toBeNull()
    unmount()
  })

  it('never renders the raw backend icon slug as text', () => {
    render(
      <AchievementBadgeCard
        badge={{ name: 'Top Performer', icon: 'award', earned: true }}
        earnedAt="2026-05-14T00:00:00Z"
      />,
    )

    expect(screen.queryByText('award')).toBeNull()
    expect(screen.getByText('Earned')).toBeTruthy()
    expect(screen.getByText(/Awarded/)).toBeTruthy()
  })

  it('shows a percentage and progress bar while in progress', () => {
    render(
      <AchievementBadgeCard
        badge={{ name: 'On-Time Hero', earned: false, progress: 80, current_value: 8, target_value: 10 }}
      />,
    )

    expect(screen.getByText('80%')).toBeTruthy()
    expect(screen.getByRole('progressbar')).toBeTruthy()
  })

  it('shows the requirement while locked', () => {
    render(
      <AchievementBadgeCard
        badge={{ name: 'Quality Master', earned: false, progress: null, criteria: 'Approve 6 reports' }}
      />,
    )

    expect(screen.getByText('Locked')).toBeTruthy()
    expect(screen.getByText('Approve 6 reports')).toBeTruthy()
  })
})
