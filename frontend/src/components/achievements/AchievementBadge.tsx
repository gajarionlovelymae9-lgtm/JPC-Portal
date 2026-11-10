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

/**
 * Shared, reusable badge visuals for the employee "My Achievements" page.
 *
 * Icons come from the single installed outline-icon library (lucide-react) so
 * every badge keeps a consistent minimalist line style; nothing here touches
 * the achievement data or its backend integration.
 */

/** Accent tones taken from the JPC recognition palette. */
export type AchievementTone = 'gold' | 'blue' | 'green' | 'purple' | 'pink' | 'sky'

/** Earned / in-progress / locked presentation. */
export type AchievementBadgeState = 'earned' | 'in-progress' | 'locked'

/** Icon metrics kept as constants so every badge renders identically. */
export const ACHIEVEMENT_ICON_SIZE = 30
export const ACHIEVEMENT_ICON_STROKE = 1.75

export interface AchievementBadgeView {
  code?: string | null
  name: string
  description?: string | null
  icon?: string | null
  tier?: string | null
  category?: string | null
  criteria?: string | null
  earned: boolean
  progress?: number | null
  current_value?: number
  target_value?: number
}

export interface AchievementVisual {
  Icon: LucideIcon
  tone: AchievementTone
}

interface AchievementToneStyles {
  /** Pastel rounded-square container used for an earned badge. */
  pastel: string
  /** Accent colour applied to the icon glyph. */
  accent: string
  /** Earned card border + subtle tint. */
  card: string
  /** Earned status chip. */
  chip: string
  /** Progress-bar gradient. */
  bar: string
}

export const ACHIEVEMENT_TONE_STYLES: Record<AchievementTone, AchievementToneStyles> = {
  // Gold — outstanding achievements.
  gold: {
    pastel: 'bg-amber-50 border-amber-100',
    accent: 'text-amber-600',
    card: 'border-amber-200 bg-amber-50/40 hover:shadow-amber-100/70',
    chip: 'bg-amber-100/70 text-amber-800 border-amber-200',
    bar: 'from-amber-400 to-amber-500',
  },
  // Blue — time and punctuality.
  blue: {
    pastel: 'bg-blue-50 border-blue-100',
    accent: 'text-blue-600',
    card: 'border-blue-200 bg-blue-50/40 hover:shadow-blue-100/70',
    chip: 'bg-blue-100/70 text-blue-800 border-blue-200',
    bar: 'from-blue-500 to-blue-600',
  },
  // Green — completion and success.
  green: {
    pastel: 'bg-emerald-50 border-emerald-100',
    accent: 'text-emerald-600',
    card: 'border-emerald-200 bg-emerald-50/40 hover:shadow-emerald-100/70',
    chip: 'bg-emerald-100/70 text-emerald-800 border-emerald-200',
    bar: 'from-emerald-400 to-emerald-500',
  },
  // Purple — performance and improvement.
  purple: {
    pastel: 'bg-purple-50 border-purple-100',
    accent: 'text-purple-600',
    card: 'border-purple-200 bg-purple-50/40 hover:shadow-purple-100/70',
    chip: 'bg-purple-100/70 text-purple-800 border-purple-200',
    bar: 'from-purple-400 to-violet-500',
  },
  // Pink — report quality.
  pink: {
    pastel: 'bg-pink-50 border-pink-100',
    accent: 'text-pink-600',
    card: 'border-pink-200 bg-pink-50/40 hover:shadow-pink-100/70',
    chip: 'bg-pink-100/70 text-pink-800 border-pink-200',
    bar: 'from-pink-400 to-rose-500',
  },
  // Sky blue — goals and productivity.
  sky: {
    pastel: 'bg-sky-50 border-sky-100',
    accent: 'text-sky-600',
    card: 'border-sky-200 bg-sky-50/40 hover:shadow-sky-100/70',
    chip: 'bg-sky-100/70 text-sky-800 border-sky-200',
    bar: 'from-sky-400 to-cyan-500',
  },
}

/**
 * Badge identity is resolved from the badge name/code first (so the intended
 * icon survives an admin renaming translations) and falls back to the icon
 * slug the backend stores, then a neutral default.
 */
const VISUALS: Array<{ match: RegExp; visual: AchievementVisual }> = [
  { match: /employee of the month|employee_of_month/i, visual: { Icon: Trophy, tone: 'gold' } },
  { match: /outstanding/i, visual: { Icon: Star, tone: 'gold' } },
  { match: /top performer|top_performer/i, visual: { Icon: Award, tone: 'purple' } },
  { match: /on[-\s]?time hero|on_time_hero/i, visual: { Icon: Clock, tone: 'blue' } },
  { match: /perfect submission|perfect_submission/i, visual: { Icon: CircleCheck, tone: 'green' } },
  { match: /report excellence|report_excellence/i, visual: { Icon: FileCheck, tone: 'pink' } },
  { match: /task champion|task_champion/i, visual: { Icon: Target, tone: 'purple' } },
  { match: /consistent|consistency/i, visual: { Icon: CalendarCheck, tone: 'purple' } },
  { match: /fast finisher|fast_finisher/i, visual: { Icon: Zap, tone: 'blue' } },
  { match: /improvement/i, visual: { Icon: TrendingUp, tone: 'purple' } },
  { match: /quality master|quality_master/i, visual: { Icon: ShieldCheck, tone: 'pink' } },
  { match: /team player|team_player/i, visual: { Icon: Users, tone: 'sky' } },
  { match: /goal achiever|goal_achiever/i, visual: { Icon: Goal, tone: 'sky' } },
  { match: /100% completion|completion|completion_100/i, visual: { Icon: BadgeCheck, tone: 'green' } },
]

const SLUG_VISUALS: Record<string, AchievementVisual> = {
  award: { Icon: Award, tone: 'purple' },
  trophy: { Icon: Trophy, tone: 'gold' },
  star: { Icon: Star, tone: 'gold' },
  clock: { Icon: Clock, tone: 'blue' },
  target: { Icon: Target, tone: 'purple' },
  'trending-up': { Icon: TrendingUp, tone: 'purple' },
  'check-circle': { Icon: CircleCheck, tone: 'green' },
  'check-square': { Icon: CircleCheck, tone: 'green' },
  'file-text': { Icon: FileCheck, tone: 'pink' },
  repeat: { Icon: CalendarCheck, tone: 'purple' },
  'user-check': { Icon: Users, tone: 'sky' },
  'shield-check': { Icon: ShieldCheck, tone: 'pink' },
  zap: { Icon: Zap, tone: 'blue' },
  goal: { Icon: Goal, tone: 'sky' },
  'badge-check': { Icon: BadgeCheck, tone: 'green' },
}

export const DEFAULT_ACHIEVEMENT_VISUAL: AchievementVisual = { Icon: Award, tone: 'sky' }

export function resolveAchievementVisual(badge: {
  name?: string | null
  code?: string | null
  icon?: string | null
}): AchievementVisual {
  const name = (badge.name ?? '').trim()
  const code = (badge.code ?? '').trim()

  for (const { match, visual } of VISUALS) {
    if ((name && match.test(name)) || (code && match.test(code))) return visual
  }

  const slug = (badge.icon ?? '').trim().toLowerCase()
  return SLUG_VISUALS[slug] ?? DEFAULT_ACHIEVEMENT_VISUAL
}

export function achievementBadgeState(
  badge: { earned: boolean; progress?: number | null },
  earnedAt?: string | null,
): AchievementBadgeState {
  if (earnedAt || badge.earned) return 'earned'
  if (typeof badge.progress === 'number') return 'in-progress'
  return 'locked'
}

function formatAwardDate(value?: string | null): string | null {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

/** Rounded-square pastel icon container shared by the card and the detail view. */
export function AchievementBadgeIcon({
  visual,
  state,
  className = '',
}: {
  visual: AchievementVisual
  state: AchievementBadgeState
  className?: string
}) {
  const { Icon, tone } = visual
  const styles = ACHIEVEMENT_TONE_STYLES[tone]
  const container =
    state === 'earned'
      ? styles.pastel
      : state === 'in-progress'
        ? 'bg-slate-50 border-slate-200'
        : 'bg-slate-100 border-slate-200'
  const iconColor = state === 'locked' ? 'text-slate-400' : styles.accent

  return (
    <span
      aria-hidden="true"
      className={`grid place-items-center w-14 h-14 rounded-2xl border transition-transform duration-200 ${container} ${className}`}
    >
      <Icon size={ACHIEVEMENT_ICON_SIZE} strokeWidth={ACHIEVEMENT_ICON_STROKE} className={iconColor} />
    </span>
  )
}

export interface AchievementBadgeCardProps {
  badge: AchievementBadgeView
  earnedAt?: string | null
  onOpen?: (badge: AchievementBadgeView, state: AchievementBadgeState) => void
}

/** Premium, clickable badge card with earned / in-progress / locked states. */
export function AchievementBadgeCard({ badge, earnedAt = null, onOpen }: AchievementBadgeCardProps) {
  const visual = resolveAchievementVisual(badge)
  const state = achievementBadgeState(badge, earnedAt)
  const styles = ACHIEVEMENT_TONE_STYLES[visual.tone]
  const awarded = formatAwardDate(earnedAt)
  const progress = typeof badge.progress === 'number' ? Math.max(0, Math.min(100, badge.progress)) : null

  const cardClass =
    state === 'earned'
      ? styles.card
      : state === 'in-progress'
        ? 'border-slate-200 bg-white hover:shadow-slate-200/60'
        : 'border-slate-200 bg-slate-50/60 hover:shadow-slate-200/60'

  return (
    <button
      type="button"
      onClick={() => onOpen?.(badge, state)}
      aria-label={`${badge.name} — view details`}
      className={`group flex flex-col items-center text-center bg-white border rounded-2xl p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2 ${cardClass}`}
    >
      <AchievementBadgeIcon visual={visual} state={state} className="group-hover:scale-105" />

      <h3 className="font-bold text-gray-900 mt-3.5 break-words">{badge.name}</h3>
      {badge.description && (
        <p className="text-sm text-gray-600 mt-1 break-words">{badge.description}</p>
      )}
      {badge.tier && (
        <p className={`text-[11px] font-bold uppercase tracking-wider mt-2 ${styles.accent}`}>{badge.tier}</p>
      )}

      {state === 'earned' && (
        <div className="mt-3 flex flex-col items-center gap-1.5">
          <span className={`inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full border ${styles.chip}`}>
            <span className="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true" />
            Earned
          </span>
          {awarded && <p className="text-xs text-gray-500">Awarded {awarded}</p>}
        </div>
      )}

      {state === 'in-progress' && progress !== null && (
        <div className="w-full mt-3 space-y-1.5">
          <div className="flex items-center justify-between text-[11px] font-semibold">
            <span className="text-slate-500">In progress</span>
            <span className={styles.accent}>{Math.round(progress)}%</span>
          </div>
          <div
            className="h-2 rounded-full bg-slate-100 overflow-hidden"
            role="progressbar"
            aria-valuenow={Math.round(progress)}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label={`Progress toward ${badge.name}`}
          >
            <div
              className={`h-full rounded-full bg-gradient-to-r ${styles.bar} transition-[width] duration-500`}
              style={{ width: `${Math.max(2, progress)}%` }}
            />
          </div>
          {(badge.current_value !== undefined || badge.target_value !== undefined) && (
            <p className="text-xs text-gray-500">
              {badge.current_value ?? 0} / {badge.target_value ?? 100}
            </p>
          )}
        </div>
      )}

      {state === 'locked' && (
        <div className="mt-3 flex flex-col items-center gap-1.5">
          <span className="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full border bg-slate-100 text-slate-500 border-slate-200">
            <span className="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true" />
            Locked
          </span>
          {badge.criteria && <p className="text-xs text-gray-400 break-words">{badge.criteria}</p>}
        </div>
      )}
    </button>
  )
}

/** Detail body rendered inside the shared Modal when a badge is clicked. */
export function AchievementBadgeDetail({
  badge,
  earnedAt = null,
}: {
  badge: AchievementBadgeView
  earnedAt?: string | null
}) {
  const visual = resolveAchievementVisual(badge)
  const state = achievementBadgeState(badge, earnedAt)
  const styles = ACHIEVEMENT_TONE_STYLES[visual.tone]
  const awarded = formatAwardDate(earnedAt)
  const progress = typeof badge.progress === 'number' ? Math.max(0, Math.min(100, badge.progress)) : null

  const stateLabel = state === 'earned' ? 'Earned' : state === 'in-progress' ? 'In progress' : 'Locked'

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-4">
        <AchievementBadgeIcon visual={visual} state={state} />
        <div className="min-w-0">
          <h3 className="text-lg font-bold text-gray-900 break-words">{badge.name}</h3>
          {badge.category && (
            <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{badge.category}</p>
          )}
          <span className={`inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full border mt-1.5 ${
            state === 'locked' ? 'bg-slate-100 text-slate-500 border-slate-200' : state === 'earned' ? styles.chip : `${styles.accent} bg-slate-50 border-slate-200`
          }`}>
            {stateLabel}
          </span>
        </div>
      </div>

      {badge.description && <p className="text-sm text-gray-700 break-words">{badge.description}</p>}

      <dl className="border border-slate-200 rounded-xl divide-y divide-slate-100 overflow-hidden">
        {badge.tier && (
          <div className="grid grid-cols-[auto_1fr] gap-x-4 px-3 py-2">
            <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 self-center">Tier</dt>
            <dd className="text-slate-700 break-words">{badge.tier}</dd>
          </div>
        )}
        {awarded && (
          <div className="grid grid-cols-[auto_1fr] gap-x-4 px-3 py-2">
            <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 self-center">Awarded</dt>
            <dd className="text-slate-700">{awarded}</dd>
          </div>
        )}
        {badge.criteria && (
          <div className="grid grid-cols-[auto_1fr] gap-x-4 px-3 py-2">
            <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 self-center">Criteria</dt>
            <dd className="text-slate-700 break-words">{badge.criteria}</dd>
          </div>
        )}
        {state === 'in-progress' && progress !== null && (
          <div className="grid grid-cols-[auto_1fr] gap-x-4 px-3 py-2">
            <dt className="text-[11px] font-bold uppercase tracking-wider text-slate-500 self-center">Progress</dt>
            <dd className="space-y-1.5">
              <div className="flex items-center justify-between text-xs font-semibold">
                <span className="text-slate-600">
                  {badge.current_value ?? 0} / {badge.target_value ?? 100}
                </span>
                <span className={styles.accent}>{Math.round(progress)}%</span>
              </div>
              <div className="h-2 rounded-full bg-slate-100 overflow-hidden" role="progressbar" aria-valuenow={Math.round(progress)} aria-valuemin={0} aria-valuemax={100}>
                <div className={`h-full rounded-full bg-gradient-to-r ${styles.bar}`} style={{ width: `${Math.max(2, progress)}%` }} />
              </div>
            </dd>
          </div>
        )}
      </dl>

      {state === 'locked' && !badge.criteria && (
        <p className="text-sm text-gray-500">Keep going — the requirements for this badge will appear here once the tracker has enough data.</p>
      )}
    </div>
  )
}
