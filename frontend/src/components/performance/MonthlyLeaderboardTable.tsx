import React from 'react'
import type { MonthlyRankItem } from '../../types/performance'

interface MonthlyLeaderboardTableProps {
  rankings: MonthlyRankItem[]
  onSelectEmployee?: (employeeId: number) => void
}

export const MonthlyLeaderboardTable: React.FC<MonthlyLeaderboardTableProps> = ({
  rankings,
  onSelectEmployee,
}) => {
  if (!rankings || rankings.length === 0) {
    return (
      <div className="p-8 bg-white rounded-2xl border border-gray-200 text-center text-xs text-gray-500">
        No ranking data calculated for the selected period.
      </div>
    )
  }

  const getRankBadge = (rank: number, isEom: boolean) => {
    if (isEom || rank === 1) {
      return (
        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
          🏆 #1 EOM
        </span>
      )
    }
    if (rank === 2) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-slate-200 text-slate-800">
          🥈 #2
        </span>
      )
    }
    if (rank === 3) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-800/10 text-amber-900 border border-amber-200">
          🥉 #3
        </span>
      )
    }
    return <span className="text-xs font-bold text-gray-400 font-mono">#{rank}</span>
  }

  return (
    <div className="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
      <div className="p-4 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
            <span>🏆</span>
            <span>Monthly Performance Leaderboard</span>
          </h3>
          <p className="text-xs text-gray-500 mt-0.5">Authoritative ranking based on official productivity scores</p>
        </div>
        <span className="text-xs font-semibold text-gray-500 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-200">
          {rankings.length} Evaluated
        </span>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-gray-50/80 border-b border-gray-200 text-[11px] font-bold text-gray-600 uppercase tracking-wider">
              <th className="py-3 px-4">Rank</th>
              <th className="py-3 px-4">Employee</th>
              <th className="py-3 px-4">Department</th>
              <th className="py-3 px-4 text-center">Tasks</th>
              <th className="py-3 px-4 text-center">On-Time</th>
              <th className="py-3 px-4 text-center">Approval</th>
              <th className="py-3 px-4 text-right">Productivity Score</th>
              <th className="py-3 px-4 text-center">Rating</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100 text-xs text-gray-700">
            {rankings.map((row) => (
              <tr
                key={row.employee_id}
                onClick={() => onSelectEmployee?.(row.employee_id)}
                className={`hover:bg-indigo-50/40 transition-colors ${
                  onSelectEmployee ? 'cursor-pointer' : ''
                } ${row.is_employee_of_month ? 'bg-amber-50/30' : ''}`}
              >
                <td className="py-3.5 px-4 whitespace-nowrap">{getRankBadge(row.rank, row.is_employee_of_month)}</td>
                <td className="py-3.5 px-4 font-bold text-gray-900 whitespace-nowrap">{row.employee_name}</td>
                <td className="py-3.5 px-4 text-gray-600 whitespace-nowrap">{row.department_name}</td>
                <td className="py-3.5 px-4 text-center font-mono font-medium">{row.completion_rate.toFixed(0)}%</td>
                <td className="py-3.5 px-4 text-center font-mono font-medium">{row.on_time_rate.toFixed(0)}%</td>
                <td className="py-3.5 px-4 text-center font-mono font-medium">{row.approval_rate.toFixed(0)}%</td>
                <td className="py-3.5 px-4 text-right whitespace-nowrap">
                  <span className="text-sm font-extrabold text-indigo-700 font-mono">
                    {row.productivity_score.toFixed(1)}%
                  </span>
                </td>
                <td className="py-3.5 px-4 text-center whitespace-nowrap">
                  <span
                    className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                      row.productivity_score >= 90
                        ? 'bg-emerald-100 text-emerald-800'
                        : row.productivity_score >= 80
                        ? 'bg-blue-100 text-blue-800'
                        : row.productivity_score >= 70
                        ? 'bg-amber-100 text-amber-800'
                        : 'bg-rose-100 text-rose-800'
                    }`}
                  >
                    {row.rating}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
