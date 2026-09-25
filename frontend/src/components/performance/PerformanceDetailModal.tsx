import React, { useEffect, useState } from 'react'
import { performanceService } from '../../services/performanceService'
import type { PerformanceInsight, BadgeItem, PerformanceHistoryPoint } from '../../types/performance'
import { ProductivityScoreCard } from './ProductivityScoreCard'
import { ScoreBreakdownView } from './ScoreBreakdownView'
import { KpiGrid } from './KpiGrid'
import { BadgeShowcase } from './BadgeShowcase'
import { PerformanceTrendChart } from './PerformanceTrendChart'

interface PerformanceDetailModalProps {
  employeeId: number | null
  year: number
  month: number
  onClose: () => void
}

export const PerformanceDetailModal: React.FC<PerformanceDetailModalProps> = ({
  employeeId,
  year,
  month,
  onClose,
}) => {
  const [loading, setLoading] = useState(true)
  const [record, setRecord] = useState<any>(null)
  const [insights, setInsights] = useState<PerformanceInsight | null>(null)
  const [history, setHistory] = useState<PerformanceHistoryPoint[]>([])
  const [badges, setBadges] = useState<BadgeItem[]>([])
  const [activeTab, setActiveTab] = useState<'kpi' | 'breakdown' | 'trend' | 'badges' | 'insights'>('kpi')

  useEffect(() => {
    if (employeeId) {
      loadDetails()
    }
  }, [employeeId, year, month])

  const loadDetails = async () => {
    if (!employeeId) return
    try {
      setLoading(true)
      const res = await performanceService.getEmployeePerformance(employeeId, year, month)
      if (res.success && res.data) {
        setRecord(res.data.record)
        setInsights(res.data.insights)
        setHistory(res.data.history)
        setBadges(res.data.badges)
      }
    } catch (err) {
      console.error('Failed to load employee performance details', err)
    } finally {
      setLoading(false)
    }
  }

  if (!employeeId) return null

  const employeeName = record?.employee?.full_name || record?.employee?.first_name || `Employee #${employeeId}`
  const departmentName = record?.employee?.department?.name || 'Department'
  const productivityScore = Number(record?.productivity_score ?? 0)

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-slate-900 text-white">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-lg font-bold">
              👤
            </div>
            <div>
              <h3 className="text-base font-bold leading-tight">{employeeName}</h3>
              <p className="text-xs text-slate-300">
                {departmentName} • Evaluated Period: {month}/{year}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
          >
            ✕
          </button>
        </div>

        {/* Tab Navigation */}
        <div className="flex border-b border-gray-200 bg-gray-50 px-6 text-xs font-semibold">
          <button
            onClick={() => setActiveTab('kpi')}
            className={`py-3 px-4 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'kpi'
                ? 'border-indigo-600 text-indigo-600 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            }`}
          >
            <span>📊</span>
            KPI Summary
          </button>
          <button
            onClick={() => setActiveTab('breakdown')}
            className={`py-3 px-4 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'breakdown'
                ? 'border-indigo-600 text-indigo-600 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            }`}
          >
            <span>⚖️</span>
            Score Breakdown
          </button>
          <button
            onClick={() => setActiveTab('trend')}
            className={`py-3 px-4 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'trend'
                ? 'border-indigo-600 text-indigo-600 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            }`}
          >
            <span>📈</span>
            Multi-Month Trend
          </button>
          <button
            onClick={() => setActiveTab('badges')}
            className={`py-3 px-4 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'badges'
                ? 'border-indigo-600 text-indigo-600 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            }`}
          >
            <span>🎖️</span>
            Badges ({badges.length})
          </button>
          <button
            onClick={() => setActiveTab('insights')}
            className={`py-3 px-4 border-b-2 transition-colors flex items-center gap-1.5 ${
              activeTab === 'insights'
                ? 'border-indigo-600 text-indigo-600 bg-white'
                : 'border-transparent text-gray-500 hover:text-gray-900'
            }`}
          >
            <span>✨</span>
            AI Advisory Insights
          </button>
        </div>

        {/* Content Body */}
        <div className="p-6 overflow-y-auto flex-1 space-y-6">
          {loading ? (
            <div className="p-12 text-center text-xs text-gray-500 flex flex-col items-center gap-2">
              <span className="text-2xl animate-spin">⚙️</span>
              <span>Loading performance profile...</span>
            </div>
          ) : (
            <>
              {activeTab === 'kpi' && record && (
                <div className="space-y-6">
                  <ProductivityScoreCard
                    score={productivityScore}
                    rating={record.rating?.value || record.rating || 'Good'}
                    calculationVersion={record.calculation_version}
                    periodName={`${month}/${year}`}
                  />
                  <KpiGrid
                    kpis={{
                      task_completion_rate: Number(record.completion_rate || 0),
                      report_completion_rate: Number(record.report_completion_rate || 0),
                      on_time_submission_rate: Number(record.on_time_rate || 0),
                      approval_rate: Number(record.approval_rate || 0),
                      target_achievement_rate: Number(record.target_achievement_rate || 0),
                    }}
                    metadata={record.metadata}
                  />
                </div>
              )}

              {activeTab === 'breakdown' && record && (
                <ScoreBreakdownView
                  breakdowns={record.breakdown_data || record.breakdowns || []}
                  finalScore={productivityScore}
                />
              )}

              {activeTab === 'trend' && (
                <PerformanceTrendChart history={history} />
              )}

              {activeTab === 'badges' && (
                <BadgeShowcase badges={badges} />
              )}

              {activeTab === 'insights' && insights && (
                <div className="space-y-4">
                  {/* Advisory Notice */}
                  <div className="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                    <span className="text-base">🛡️</span>
                    <div>
                      <p className="font-bold">Advisory Coaching & Trend Analysis</p>
                      <p className="mt-0.5 text-amber-800">{insights.disclaimer}</p>
                    </div>
                  </div>

                  {/* Summary Narrative */}
                  <div className="bg-white rounded-xl border border-gray-200 p-5 shadow-xs space-y-2">
                    <h4 className="text-xs font-bold text-gray-900 uppercase tracking-wider">
                      Narrative Trajectory Summary
                    </h4>
                    <p className="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl border border-gray-100 leading-relaxed">
                      {insights.summary}
                    </p>
                  </div>

                  {/* Warning Indicators / Anomalies */}
                  {insights.anomalies.length > 0 && (
                    <div className="bg-white rounded-xl border border-rose-200 p-5 shadow-xs space-y-2">
                      <h4 className="text-xs font-bold text-rose-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>⚠️</span>
                        <span>Potential Performance Bottlenecks</span>
                      </h4>
                      <ul className="space-y-1.5 text-xs text-rose-800">
                        {insights.anomalies.map((anom, idx) => (
                          <li key={idx} className="flex items-start gap-2">
                            <span>•</span>
                            <span>{anom}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}

                  {/* Recommendations */}
                  {insights.recommendations.length > 0 && (
                    <div className="bg-white rounded-xl border border-indigo-200 p-5 shadow-xs space-y-2">
                      <h4 className="text-xs font-bold text-indigo-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>💡</span>
                        <span>Development Recommendations</span>
                      </h4>
                      <ul className="space-y-1.5 text-xs text-indigo-800">
                        {insights.recommendations.map((rec, idx) => (
                          <li key={idx} className="flex items-start gap-2">
                            <span>•</span>
                            <span>{rec}</span>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-3 border-t border-gray-100 bg-gray-50 flex items-center justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl text-xs font-bold transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  )
}
