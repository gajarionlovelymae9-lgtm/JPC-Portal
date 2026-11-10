import { useCallback, useEffect, useState } from 'react'
import { ApiError } from '../../services/api'
import { performanceService } from '../../services/performanceService'
import type { EmployeePerformanceData } from '../../types/performance'
import { ProductivityScoreCard } from '../../components/performance/ProductivityScoreCard'
import { KpiGrid } from '../../components/performance/KpiGrid'
import { ScoreBreakdownView } from '../../components/performance/ScoreBreakdownView'
import { PerformanceTrendChart } from '../../components/performance/PerformanceTrendChart'
import { BadgeShowcase } from '../../components/performance/BadgeShowcase'

export default function EmployeePerformancePage() {
  const [data, setData] = useState<EmployeePerformanceData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedMonth, setSelectedMonth] = useState<number>(new Date().getMonth() + 1)
  const [selectedYear, setSelectedYear] = useState<number>(new Date().getFullYear())

  const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
  ]

  const currentYear = new Date().getFullYear()
  const years = [currentYear - 1, currentYear, currentYear + 1]

  const loadPerformance = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const res = await performanceService.getMyPerformance(selectedYear, selectedMonth)
      if (res.success && res.data) {
        setData(res.data)
      } else {
        setData(null)
        setError('Failed to retrieve performance calculations.')
      }
    } catch (err) {
      console.error(err)
      setData(null)
      // Surface the real API failure instead of masking every cause behind the
      // same "cannot connect" message.
      if (err instanceof ApiError) {
        setError(
          err.status === 0
            ? 'Unable to connect to the performance service. Check your network connection and try again.'
            : err.message,
        )
      } else {
        setError('Unable to load performance data. Please try again.')
      }
    } finally {
      setLoading(false)
    }
  }, [selectedMonth, selectedYear])

  useEffect(() => {
    void loadPerformance()
  }, [loadPerformance])

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header & Period Filter */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
            <span>📊</span>
            <span>My Performance & KPI Dashboard</span>
          </h1>
          <p className="text-xs text-gray-500 mt-1">
            Authoritative performance monitoring, productivity scoring, and verified achievements
          </p>
        </div>

        <div className="flex items-center gap-2">
          <select
            value={selectedMonth}
            onChange={(e) => setSelectedMonth(Number(e.target.value))}
            className="text-xs font-semibold bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-700 focus:ring-2 focus:ring-indigo-500"
          >
            {months.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </select>

          <select
            value={selectedYear}
            onChange={(e) => setSelectedYear(Number(e.target.value))}
            className="text-xs font-semibold bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-700 focus:ring-2 focus:ring-indigo-500"
          >
            {years.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>

          <button
            onClick={() => void loadPerformance()}
            disabled={loading}
            className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold transition-colors"
          >
            🔄 Refresh
          </button>
        </div>
      </div>

      {loading ? (
        <div className="p-16 text-center text-xs text-gray-500 flex flex-col items-center gap-3">
          <span className="text-3xl animate-spin">⚙️</span>
          <span>Computing verified performance data...</span>
        </div>
      ) : error || !data ? (
        <div className="p-6 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-medium">
          ⚠️ {error || 'No performance records found for this period.'}
        </div>
      ) : data.has_activity === false ? (
        <div className="p-10 bg-white border border-gray-200 rounded-2xl text-center shadow-sm">
          <span className="text-3xl block mb-2">🗓️</span>
          <h2 className="text-sm font-bold text-gray-800">No performance activity for this period</h2>
          <p className="text-xs text-gray-500 mt-1 max-w-md mx-auto">
            No tasks, reports, or targets are recorded for {data.period.name}. Once work is assigned and
            completed, refresh to see verified KPIs and your productivity score.
          </p>
        </div>
      ) : (
        <>
          {/* Main Score & Top KPIs */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-1">
              <ProductivityScoreCard
                score={data.productivity_score}
                rating={data.rating}
                ratingColor={data.rating_color}
                calculationVersion={data.calculation_version}
                periodName={data.period.name}
              />
            </div>

            <div className="lg:col-span-2">
              <ScoreBreakdownView
                breakdowns={data.breakdowns}
                finalScore={data.productivity_score}
              />
            </div>
          </div>

          {/* 5 Core KPIs Grid */}
          <KpiGrid kpis={data.kpis} metadata={data.metadata} />

          {/* Multi-Month Trend Chart */}
          <PerformanceTrendChart history={data.history} />

          {/* Badges Showcase */}
          <BadgeShowcase badges={data.badges} />
        </>
      )}
    </div>
  )
}
