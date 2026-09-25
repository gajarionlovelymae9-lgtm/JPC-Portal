import React from 'react'
import type { PerformanceRatingLabel } from '../../types/performance'

interface ProductivityScoreCardProps {
  score: number
  rating: PerformanceRatingLabel | string
  ratingColor?: string
  calculationVersion?: string
  periodName?: string
}

export const ProductivityScoreCard: React.FC<ProductivityScoreCardProps> = ({
  score,
  rating,
  ratingColor = 'emerald',
  calculationVersion = 'v1.0',
  periodName,
}) => {
  const colorStyles: Record<string, { bg: string; text: string; border: string; bar: string }> = {
    emerald: { bg: 'bg-emerald-50', text: 'text-emerald-700', border: 'border-emerald-200', bar: 'bg-emerald-600' },
    blue: { bg: 'bg-blue-50', text: 'text-blue-700', border: 'border-blue-200', bar: 'bg-blue-600' },
    amber: { bg: 'bg-amber-50', text: 'text-amber-700', border: 'border-amber-200', bar: 'bg-amber-500' },
    orange: { bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200', bar: 'bg-orange-500' },
    rose: { bg: 'bg-rose-50', text: 'text-rose-700', border: 'border-rose-200', bar: 'bg-rose-600' },
  }

  const theme = colorStyles[ratingColor] || colorStyles.emerald

  return (
    <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm relative overflow-hidden">
      <div className="flex items-center justify-between mb-4">
        <div>
          <span className="text-[11px] font-bold tracking-wider text-gray-600 uppercase">
            Official Productivity Score
          </span>
          {periodName && <p className="text-xs text-gray-500 font-medium">{periodName}</p>}
        </div>
        <span className="px-2.5 py-1 text-[11px] font-semibold bg-gray-100 text-gray-600 rounded-full border border-gray-200">
          ⚙️ {calculationVersion}
        </span>
      </div>

      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 my-3">
        <div className="flex items-baseline gap-2">
          <span className="text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight font-mono">
            {score.toFixed(1)}%
          </span>
          <span className="text-xs font-semibold text-gray-600">/ 100%</span>
        </div>

        <div className={`px-4 py-2 rounded-xl border font-bold text-xs flex items-center gap-2 ${theme.bg} ${theme.text} ${theme.border}`}>
          <span>★</span>
          <span>{rating}</span>
        </div>
      </div>

      {/* Visual Progress Bar */}
      <div className="w-full bg-gray-100 rounded-full h-3 overflow-hidden mt-3 mb-2">
        <div
          className={`h-full rounded-full transition-all duration-700 ease-out ${theme.bar}`}
          style={{ width: `${Math.min(100, Math.max(0, score))}%` }}
        />
      </div>

      <p className="text-[11px] text-gray-600 mt-2 flex items-center gap-1.5">
        <span>🔒</span>
        <span>Authoritative mathematical score calculated strictly from verified database records.</span>
      </p>
    </div>
  )
}
