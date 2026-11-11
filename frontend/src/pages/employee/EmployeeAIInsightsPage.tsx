import { useCallback, useEffect, useState } from 'react'
import {
  getMyAiInsights,
  type EmployeeAiInsights,
} from '../../services/employeePortalService'
import { PerformanceTrendChart } from '../../components/performance/PerformanceTrendChart'
import { AiScoreCard } from '../../components/employee/AIInsights/AiScoreCard'
import { AiMetricGrid } from '../../components/employee/AIInsights/AiMetricGrid'
import { AiAlertList, AiRecommendationList } from '../../components/employee/AIInsights/AiAlertList'
import { AiForecastCard, AiTransparencyPanel } from '../../components/employee/AIInsights/AiTransparency'
import { JpcAideLogo } from '../../components/ai/JpcAideLogo'

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
]

export default function EmployeeAIInsightsPage() {
  const [data, setData] = useState<EmployeeAiInsights | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [year, setYear] = useState<number>(new Date().getFullYear())
  const [month, setMonth] = useState<number>(new Date().getMonth() + 1)

  const load = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const res = await getMyAiInsights({ year, month })
      if (res.success && res.data) {
        setData(res.data)
      } else {
        setError('AI insights could not be loaded.')
      }
    } catch {
      setError('Unable to reach the AI insights service. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [year, month])

  useEffect(() => {
    void load()
  }, [load])

  const years = [year - 2, year - 1, year, year + 1]

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
        <div className="flex items-start gap-3">
          <div className="rounded-2xl bg-gradient-to-br from-blue-50 to-purple-50 p-2 ring-1 ring-indigo-100">
            <JpcAideLogo size={30} />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900 tracking-tight">
              My AI Insights
            </h1>
            <p className="text-sm font-medium text-indigo-700 mt-0.5">
              Intelligent Performance Analysis and Productivity Recommendations
            </p>
            <p className="text-xs text-gray-500 mt-1">
              Advisory analysis built only from your own recorded tasks, reports, and targets
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <label className="sr-only" htmlFor="ai-insights-month">Month</label>
          <select
            id="ai-insights-month"
            value={month}
            onChange={(e) => setMonth(Number(e.target.value))}
            className="text-xs font-semibold bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-700 focus:ring-2 focus:ring-indigo-500"
          >
            {MONTHS.map((label, index) => (
              <option key={label} value={index + 1}>{label}</option>
            ))}
          </select>

          <label className="sr-only" htmlFor="ai-insights-year">Year</label>
          <select
            id="ai-insights-year"
            value={year}
            onChange={(e) => setYear(Number(e.target.value))}
            className="text-xs font-semibold bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-gray-700 focus:ring-2 focus:ring-indigo-500"
          >
            {years.map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </select>

          <button
            onClick={() => void load()}
            className="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-colors"
          >
            Refresh
          </button>
        </div>
      </div>

      {loading ? (
        <div className="p-16 text-center text-xs text-gray-500 flex flex-col items-center gap-3">
          <span className="text-3xl animate-spin" aria-hidden="true">⚙️</span>
          <span>Analysing your recorded activity...</span>
        </div>
      ) : error || !data ? (
        <div role="alert" className="p-6 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl text-xs font-medium">
          {error ?? 'AI insights are unavailable right now.'}
        </div>
      ) : (
        <>
          {!data.available && (
            <div
              role="status"
              className="p-6 bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl text-xs font-medium"
            >
              <p className="font-bold">Not enough data yet</p>
              <p className="mt-1">{data.no_data_reason}</p>
            </div>
          )}

          {data.summary && (
            <section aria-label="Summary" className="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
              <h2 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Summary</h2>
              <p className="mt-2 text-sm text-gray-700 leading-relaxed">{data.summary}</p>
            </section>
          )}

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-1">
              <AiScoreCard score={data.score} periodLabel={data.period.label} />
            </div>
            <div className="lg:col-span-2">
              <AiMetricGrid metrics={data.metrics} />
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2">
              <PerformanceTrendChart
                history={data.performance_trend.map((point) => ({
                  period_label: point.period_label,
                  year: point.year,
                  month: point.month,
                  score: point.score,
                  rating: point.rating,
                }))}
              />
            </div>
            <div className="lg:col-span-1">
              <AiForecastCard forecast={data.forecast} />
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <AiAlertList alerts={data.alerts} />
            <AiRecommendationList recommendations={data.recommendations} />
          </div>

          <AiTransparencyPanel transparency={data.score_transparency} />

          <p className="text-[11px] text-gray-500 text-center pb-2">{data.disclaimer}</p>
        </>
      )}
    </div>
  )
}
