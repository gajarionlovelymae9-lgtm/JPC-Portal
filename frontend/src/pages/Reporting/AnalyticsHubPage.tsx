import { useCallback, useMemo, useState } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { PeriodFilter } from '../../components/Reporting/PeriodFilter'
import { ReportSection } from '../../components/reports/ReportSection'
import { BarChart } from '../../components/reports/BarChart'
import { TaskSummaryCards } from '../../components/tasks/TaskSummaryCards'
import { useReportingPeriod, useReportingResource } from '../../hooks/useReporting'
import {
  departmentComparison,
  getCompletionTrend,
  getPerformanceDistribution,
  getPerformanceTrend,
  getRankings,
} from '../../services/reportingService'
import { getReportOptions } from '../../services/reportService'
import { toNumber } from '../../utils/task'
import type { DepartmentComparisonRow } from '../../types/reporting'

const RANKING_METRICS = [
  { value: 'productivity', label: 'Productivity' },
  { value: 'completion_rate', label: 'Completion rate' },
  { value: 'on_time_rate', label: 'On-time rate' },
  { value: 'target_achievement', label: 'Target achievement' },
]

/** Phase 9 — Analytics hub (spec items 14, 16-18, endpoints #16-24). */
export function AnalyticsHubPage() {
  const { year, month, setYear, setMonth } = useReportingPeriod()
  const [metric, setMetric] = useState('productivity')
  const [selectedDepartmentIds, setSelectedDepartmentIds] = useState<number[]>([])

  const performanceTrend = useReportingResource(
    useCallback(() => getPerformanceTrend(6, { year, month }), [year, month]),
    `perf-trend|${year}|${month}`,
    'Unable to load performance trends.',
  )

  const completionTrend = useReportingResource(
    useCallback(() => getCompletionTrend(6, { year, month }), [year, month]),
    `completion-trend|${year}|${month}`,
    'Unable to load completion trends.',
  )

  const distribution = useReportingResource(
    useCallback(() => getPerformanceDistribution({ year, month }), [year, month]),
    `distribution|${year}|${month}`,
    'Unable to load the performance distribution.',
  )

  const rankings = useReportingResource(
    useCallback(() => getRankings({ year, month }, metric), [year, month, metric]),
    `rankings|${year}|${month}|${metric}`,
    'Unable to load rankings.',
  )

  const options = useReportingResource(
    useCallback(() => getReportOptions(), []),
    'analytics-options',
    'Unable to load report options.',
  )

  const comparison = useReportingResource(
    useCallback(() => {
      if (selectedDepartmentIds.length < 2) {
        return Promise.resolve<{ data: DepartmentComparisonRow[] }>({ data: [] })
      }
      return departmentComparison(selectedDepartmentIds)
    }, [selectedDepartmentIds]),
    `dept-comparison|${year}|${month}|${selectedDepartmentIds.slice().sort().join(',')}`,
    'Unable to run the department comparison.',
  )

  const perfChart = useMemo(() => {
    const points = performanceTrend.data ?? []
    const labels = points.map((point) => point.period)
    const scores = points.map((point) => toNumber(point.productivity_score))
    const completion = points.map((point) => toNumber(point.completion_rate))
    const last = scores[scores.length - 1]
    const prev = scores[scores.length - 2]
    const monthOverMonthPp =
      last === undefined || prev === undefined ? 0 : Math.round((last - prev) * 10) / 10
    return { labels, scores, completion, monthOverMonthPp }
  }, [performanceTrend.data])

  const completionChart = useMemo(() => {
    const points = completionTrend.data ?? []
    const labels = points.map((point) => point.period)
    const rates = points.map((point) => toNumber(point.completion_rate))
    const counts = points.map((point) => point.records_count)
    const last = rates[rates.length - 1]
    const prev = rates[rates.length - 2]
    const monthOverMonthPp =
      last === undefined || prev === undefined ? 0 : Math.round((last - prev) * 10) / 10
    return { labels, rates, counts, monthOverMonthPp }
  }, [completionTrend.data])

  function toggleDepartment(id: number) {
    setSelectedDepartmentIds((current) =>
      current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
    )
  }

  return (
    <div className="page">
      <ReportingIntro title="Analytics hub">
        Trend feeds, rankings, performance distribution and cross-department comparison.
      </ReportingIntro>

      <div className="dashboard-filters-wrap">
        <div className="task-filters">
          <PeriodFilter year={year} month={month} onYearChange={setYear} onMonthChange={setMonth} />
        </div>
      </div>

      <ReportSection
        title="Productivity trend"
        hint={`${perfChart.labels.length} periods of institutional productivity`}
      >
        <BarChart
          title="Institutional productivity over time"
          labels={perfChart.labels}
          completed={perfChart.scores}
          pending={perfChart.completion}
          rates={perfChart.scores}
          monthOverMonthPp={perfChart.monthOverMonthPp}
        />
      </ReportSection>

      <ReportSection
        title="Completion trend"
        hint={`${completionChart.labels.length} periods of completion rate`}
      >
        <BarChart
          title="Completion rate over time"
          labels={completionChart.labels}
          completed={completionChart.rates}
          pending={completionChart.counts}
          rates={completionChart.rates}
          monthOverMonthPp={completionChart.monthOverMonthPp}
        />
      </ReportSection>

      <ReportSection title="Performance distribution" hint={distribution.data ? `${distribution.data.total_evaluated} faculty evaluated` : undefined}>
        {distribution.data ? (
          <TaskSummaryCards
            items={Object.entries(distribution.data.distribution).map(([band, count]) => ({
              label: band,
              value: count,
            }))}
          />
        ) : (
          <p className="page-state">Loading distribution…</p>
        )}
      </ReportSection>

      <ReportSection
        title="Rankings"
        hint="Top faculty by selected metric"
        action={
          <div className="task-filters">
            <label className="task-filters__field">
              <span className="task-filters__label">Metric</span>
              <select
                aria-label="Ranking metric"
                value={metric}
                onChange={(event) => setMetric(event.target.value)}
              >
                {RANKING_METRICS.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            </label>
          </div>
        }
      >
        {rankings.data && rankings.data.length === 0 ? (
          <p className="page-state">No ranking data for this period.</p>
        ) : (
          <div className="task-table-wrap">
            <table className="task-table">
              <thead>
                <tr>
                  <th scope="col">Rank</th>
                  <th scope="col">Faculty member</th>
                  <th scope="col">Department</th>
                  <th scope="col">Metric score</th>
                  <th scope="col">Completion</th>
                  <th scope="col">On time</th>
                </tr>
              </thead>
              <tbody>
                {(rankings.data ?? []).map((row) => (
                  <tr key={String(row.employee_id)}>
                    <td data-label="Rank">{row.rank}</td>
                    <td data-label="Faculty">{row.name}</td>
                    <td data-label="Department">{row.department ?? '—'}</td>
                    <td data-label="Score">{toNumber(row.score)}%</td>
                    <td data-label="Completion">{toNumber(row.completion_rate)}%</td>
                    <td data-label="On time">{toNumber(row.on_time_rate)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ReportSection>

      <ReportSection title="Department comparison" hint="Select at least two departments">
        <div className="task-filters">
          {(options.data?.departments ?? []).map((dept) => (
            <label className="task-filters__field" key={dept.id}>
              <input
                type="checkbox"
                checked={selectedDepartmentIds.includes(dept.id)}
                onChange={() => toggleDepartment(dept.id)}
              />
              <span className="task-filters__label">{dept.name}</span>
            </label>
          ))}
        </div>
        {comparison.data && comparison.data.length > 0 ? (
          <div className="task-table-wrap">
            <table className="task-table">
              <thead>
                <tr>
                  <th scope="col">Department</th>
                  <th scope="col">Productivity</th>
                  <th scope="col">Completion</th>
                  <th scope="col">On time</th>
                  <th scope="col">Approval</th>
                  <th scope="col">Target</th>
                </tr>
              </thead>
              <tbody>
                {comparison.data.map((row) => (
                  <tr key={String(row.id)}>
                    <td data-label="Department">{row.name}</td>
                    <td data-label="Productivity">{toNumber(row.productivity)}%</td>
                    <td data-label="Completion">{toNumber(row.completion_rate)}%</td>
                    <td data-label="On time">{toNumber(row.on_time_rate)}%</td>
                    <td data-label="Approval">{toNumber(row.approval_rate)}%</td>
                    <td data-label="Target">{toNumber(row.target_achievement)}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="page-state">
            {selectedDepartmentIds.length < 2
              ? 'Pick two or more departments to compare.'
              : 'Loading comparison…'}
          </p>
        )}
      </ReportSection>
    </div>
  )
}