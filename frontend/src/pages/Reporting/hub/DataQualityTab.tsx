import { useCallback } from 'react'
import {
  Badge,
  EmptyState,
  ErrorPanel,
  LoadingPanel,
  Panel,
  Table,
  Td,
  Th,
  dateTime,
  humanise,
} from '../../../components/admin/AdminUi'
import { useReportingResource } from '../../../hooks/useReporting'
import { getDataQualityReport } from '../../../services/reportingService'
import type { DataQualityReport, ReportingFilters } from '../../../types/reporting'
import { MiniStat, numOrNa, pctOrNa, toCategoryRows } from './hubShared'

interface DataQualityTabProps {
  filters: ReportingFilters
}

/**
 * TAB 7 — Data quality. Every figure comes from completed scan runs and the
 * issue ledger; the score is never calculated when no scan has evaluated the
 * institution.
 */
export function DataQualityTab({ filters }: DataQualityTabProps) {
  const dataQuality = useReportingResource<DataQualityReport>(
    useCallback(() => getDataQualityReport(filters), [filters]),
    `hub-data-quality-${JSON.stringify(filters)}`,
    'Unable to load the data quality summary.',
  )

  const data = dataQuality.data

  if (dataQuality.loading && !data) {
    return <LoadingPanel label="Loading data quality" rows={3} />
  }

  if (dataQuality.error) {
    return <ErrorPanel message={dataQuality.error} onRetry={dataQuality.refresh} />
  }

  if (!data) {
    return <EmptyState title="No data quality records" message="No scan results are available." />
  }

  return (
    <div className="space-y-4">
      <Panel
        title="Data quality score"
        description="Coverage comes from completed scans; the score is derived from open issue severity."
        actions={
          <Badge tone={data.summary.evaluated ? 'ok' : 'neutral'}>
            {data.summary.evaluated ? 'Evaluated' : 'Not evaluated'}
          </Badge>
        }
      >
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <MiniStat
            label="Health score"
            value={data.summary.evaluated ? pctOrNa(data.summary.data_quality_score) : 'N/A'}
            hint={
              data.summary.evaluated
                ? 'Derived from open data quality issues'
                : 'No completed scan has evaluated the institution yet'
            }
          />
          <MiniStat
            label="Records checked"
            value={numOrNa(data.summary.records_checked)}
            hint={
              data.summary.latest_scan_at
                ? `Latest scan ${dateTime(data.summary.latest_scan_at)}`
                : 'No scans recorded'
            }
          />
          <MiniStat
            label="Issues found"
            value={numOrNa(data.summary.issues_found)}
            hint={`${numOrNa(data.summary.open_issues)} still open · ${numOrNa(data.summary.critical)} critical`}
          />
          <MiniStat
            label="Resolved"
            value={numOrNa(data.summary.resolved)}
            hint={`${numOrNa(data.summary.dismissed)} dismissed as false positives`}
          />
        </div>
        <p className="mt-3 text-[11px] text-slate-500">
          Score, records checked and issues found come from real scan and issue records; the score is never invented
          when no scan has run.
        </p>
      </Panel>

      <Panel title="Issue severity" description="Open issue ledger broken down by severity.">
        <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
          <MiniStat label="Critical" value={numOrNa(data.summary.critical)} />
          <MiniStat label="High" value={numOrNa(data.summary.high)} />
          <MiniStat label="Medium" value={numOrNa(data.summary.medium)} />
          <MiniStat label="Low" value={numOrNa(data.summary.low)} />
          <MiniStat
            label="Total issues"
            value={numOrNa(data.summary.total_issues)}
            hint={`${numOrNa(data.summary.open_issues)} open`}
          />
        </div>
      </Panel>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Panel title="Issues by category" description="Validation categories raised by the latest scans.">
        {(() => {
          // Normalised defensively: the payload may be a list, a keyed map or
          // invalid; the tab must survive all three without crashing.
          const categoryRows = toCategoryRows(data.by_category)
          return categoryRows.length === 0 ? (
            <EmptyState title="No categorised issues" message="No data quality issues have been recorded." />
          ) : (
            <Table>
              <thead>
                <tr>
                  <Th>Category</Th>
                  <Th className="text-right">Issues</Th>
                </tr>
              </thead>
              <tbody>
                {categoryRows.map((row) => (
                  <tr key={row.label} className="border-b border-slate-100 last:border-0">
                    <Td className="text-slate-700">{humanise(row.label)}</Td>
                    <Td className="text-right tabular-nums">{numOrNa(row.count)}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          )
        })()}
        </Panel>

        <Panel title="Issues by department" description="Where the validation issues were found.">
          {(() => {
            const rows = toCategoryRows(data.by_department)
            return rows.length === 0 ? (
              <EmptyState title="No department breakdown" message="No department-linked issues have been recorded." />
            ) : (
              <Table>
                <thead>
                  <tr>
                    <Th>Department</Th>
                    <Th className="text-right">Issues</Th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.label} className="border-b border-slate-100 last:border-0">
                      <Td className="text-slate-700">{row.label}</Td>
                      <Td className="text-right tabular-nums">{numOrNa(row.count)}</Td>
                    </tr>
                  ))}
                </tbody>
              </Table>
            )
          })()}
        </Panel>
      </div>

      <Panel
        title="Scan history"
        description="Completed and failed data quality runs."
        actions={<Badge tone="neutral">{`${data.recent_scans.length} scans`}</Badge>}
      >
        {data.recent_scans.length === 0 ? (
          <EmptyState title="No scan history" message="No data quality scan has run yet." />
        ) : (
          <Table>
            <thead>
              <tr>
                <Th>Scan</Th>
                <Th>Status</Th>
                <Th>Run at</Th>
                <Th className="text-right">Records checked</Th>
                <Th className="text-right">Issues found</Th>
              </tr>
            </thead>
            <tbody>
              {data.recent_scans.map((scan) => (
                <tr key={scan.id} className="border-b border-slate-100 last:border-0">
                  <Td className="font-semibold text-slate-800">{`#${scan.id}`}</Td>
                  <Td>
                    <Badge tone={scan.status === 'completed' ? 'ok' : scan.status === 'failed' ? 'danger' : 'warn'}>
                      {humanise(scan.status)}
                    </Badge>
                  </Td>
                  <Td className="text-xs text-slate-500">{dateTime(scan.created_at)}</Td>
                  <Td className="text-right tabular-nums">{numOrNa(scan.records_checked)}</Td>
                  <Td className="text-right tabular-nums">{numOrNa(scan.issues_found)}</Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Panel>

      {data.trends.length > 0 ? (
        <Panel title="Score trend" description="Recorded data quality score over recent scans.">
          <div className="flex flex-wrap gap-2">
            {data.trends.map((point) => (
              <Badge key={point.period} tone="neutral">
                {`${point.period}: ${pctOrNa(point.score)}`}
              </Badge>
            ))}
          </div>
        </Panel>
      ) : null}
    </div>
  )
}
