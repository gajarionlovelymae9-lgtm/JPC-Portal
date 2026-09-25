import React from 'react'
import type { BadgeItem } from '../../types/performance'

interface BadgeShowcaseProps {
  badges: BadgeItem[]
}

const BADGE_ICONS: Record<string, string> = {
  EMPLOYEE_OF_MONTH: '🏆',
  TOP_PERFORMER: '🌟',
  ON_TIME_HERO: '⏱️',
  PERFECT_SUBMISSION: '🎯',
  TASK_CHAMPION: '📋',
  REPORT_EXCELLENCE: '📑',
  OUTSTANDING: '⭐',
  CONSISTENCY: '📊',
  MOST_IMPROVED: '🚀',
}

export const BadgeShowcase: React.FC<BadgeShowcaseProps> = ({ badges }) => {
  if (!badges || badges.length === 0) {
    return (
      <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm text-center">
        <span className="text-3xl block mb-2">🏅</span>
        <h4 className="text-sm font-bold text-gray-800">No Badges Earned Yet</h4>
        <p className="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
          Complete assigned tasks, submit on-time reports, and maintain high performance scores to unlock achievements.
        </p>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
      <div className="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
        <div>
          <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
            <span>🎖️</span>
            <span>Earned Achievements & Badges</span>
          </h3>
          <p className="text-xs text-gray-500 mt-0.5">Recognized milestones awarded for performance excellence</p>
        </div>
        <span className="text-xs font-bold px-2.5 py-1 bg-amber-50 text-amber-800 rounded-full border border-amber-200">
          {badges.length} Badges
        </span>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
        {badges.map((badge) => {
          const emoji = BADGE_ICONS[badge.code] || '🏅'

          return (
            <div
              key={badge.id}
              className="p-3.5 bg-gradient-to-br from-amber-50/40 via-white to-orange-50/20 border border-amber-200/80 rounded-xl flex items-start gap-3 shadow-xs hover:border-amber-300 transition-colors"
            >
              <div className="w-10 h-10 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center text-xl flex-shrink-0">
                {emoji}
              </div>
              <div className="overflow-hidden">
                <h4 className="text-xs font-bold text-gray-900 truncate">{badge.name}</h4>
                <p className="text-[11px] text-gray-500 line-clamp-2 mt-0.5 leading-snug">
                  {badge.description || 'Awarded for exceptional contributions.'}
                </p>
                {badge.awarded_at && (
                  <span className="text-[10px] text-gray-400 font-medium block mt-1">
                    Awarded: {new Date(badge.awarded_at).toLocaleDateString()}
                  </span>
                )}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
