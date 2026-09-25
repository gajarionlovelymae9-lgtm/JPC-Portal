import { useCallback, useEffect, useState } from 'react'
import { ReportingIntro } from '../../components/Reporting/ReportingIntro'
import { ReportSection } from '../../components/reports/ReportSection'
import { useReportingResource } from '../../hooks/useReporting'
import {
  downloadReportExportFile,
  listReportExports,
  queueReportExport,
} from '../../services/reportingService'
import type { ReportExportTypeValue } from '../../types/reporting'
import { getApiErrorMessage } from '../../utils/apiError'

const REPORT_TYPES: Array<{ value: ReportExportTypeValue; label: string }> = [
  { value: 'employee_progress', label: 'Employee progress' },
  { value: 'department_performance', label: 'Department performance' },
  { value: 'institution_performance', label: 'Institution performance' },
  { value: 'monthly_performance', label: 'Monthly performance' },
  { value: 'achievement_summary', label: 'Achievement summary' },
  { value: 'late_reports', label: 'Late submissions' },
  { value: 'pending_reports', label: 'Pending reports' },
  { value: 'targets_summary', label: 'Target achievement' },
  { value: 'data_quality', label: 'Data quality' },
]

const FORMATS = ['csv', 'xlsx', 'pdf'] as const

function formatBytes(bytes: number | null): string {
  if (bytes === null) {
    return '—'
  }
  if (bytes < 1024) {
    return `${bytes} B`
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/** Phase 9 — Report export queue (spec items 32-33, 53-55). */
export function ReportExportsPage() {
  const [reportType, setReportType] = useState<ReportExportTypeValue>('monthly_performance')
  const [format, setFormat] = useState<(typeof FORMATS)[number]>('xlsx')
  const [queueing, setQueueing] = useState(false)
  const [downloadError, setDownloadError] = useState<string | null>(null)
  const [downloadId, setDownloadId] = useState<number | null>(null)

  const exports = useReportingResource(
    useCallback(() => listReportExports(), []),
    'report-exports',
    'Unable to load your exports.',
  )

  useEffect(() => {
    const hasPending = (exports.data ?? []).some((item) =>
      ['queued', 'processing'].includes(item.status),
    )
    if (!hasPending) {
      return undefined
    }
    const timer = window.setInterval(() => {
      exports.refresh()
    }, 4000)
    return () => window.clearInterval(timer)
  }, [exports])

  async function handleQueue() {
    setQueueing(true)
    setDownloadError(null)
    try {
      await queueReportExport({ report_type: reportType, format, filters: {} })
      exports.refresh()
    } catch (cause: unknown) {
      setDownloadError(getApiErrorMessage(cause, 'The export request failed.'))
    } finally {
      setQueueing(false)
    }
  }

  async function handleDownload(id: number) {
    setDownloadError(null)
    setDownloadId(id)
    try {
      await downloadReportExportFile(id)
    } catch (cause: unknown) {
      setDownloadError(getApiErrorMessage(cause, 'The download failed.'))
      exports.refresh()
    } finally {
      setDownloadId(null)
    }
  }

  const rowCount = (exports.data ?? []).length

  return (
    <div className="page">
      <ReportingIntro title="Report exports">
        Queue nine Phase 9 report types as CSV, XLSX or PDF, then download the completed files.
      </ReportingIntro>

      <ReportSection title="Request a new export">
        <div className="task-filters">
          <label className="task-filters__field">
            <span className="task-filters__label">Report type</span>
            <select
              aria-label="Report type"
              value={reportType}
              onChange={(event) => setReportType(event.target.value as ReportExportTypeValue)}
            >
              {REPORT_TYPES.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>
          </label>
          <label className="task-filters__field">
            <span className="task-filters__label">Format</span>
            <select aria-label="Format" value={format} onChange={(event) => setFormat(event.target.value as (typeof FORMATS)[number])}>
              {FORMATS.map((value) => (
                <option key={value} value={value}>{value.toUpperCase()}</option>
              ))}
            </select>
          </label>
          <div className="task-actions">
            <button
              type="button"
              className="button button--secondary"
              disabled={queueing}
              onClick={() => void handleQueue()}
            >
              {queueing ? 'Queueing…' : 'Queue export'}
            </button>
          </div>
        </div>
        {downloadError ? (
          <div className="page-state page-state--denied" role="alert">
            <p>{downloadError}</p>
          </div>
        ) : null}
      </ReportSection>

      <ReportSection title="Past exports" hint={`${rowCount} on record`}>
        {exports.loading && !exports.data ? (
          <p className="page-state">Loading exports…</p>
        ) : rowCount === 0 ? (
          <p className="page-state">No exports requested yet.</p>
        ) : (
          <div className="task-table-wrap">
            <table className="task-table">
              <thead>
                <tr>
                  <th scope="col">Report type</th>
                  <th scope="col">Format</th>
                  <th scope="col">Status</th>
                  <th scope="col">Rows</th>
                  <th scope="col">Size</th>
                  <th scope="col">Requested</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                {(exports.data ?? []).map((item) => (
                  <tr key={String(item.id)}>
                    <td data-label="Report type">{item.report_type.replaceAll('_', ' ')}</td>
                    <td data-label="Format">{item.format.toUpperCase()}</td>
                    <td data-label="Status">{item.status_label}</td>
                    <td data-label="Rows">{item.row_count ?? '—'}</td>
                    <td data-label="Size">{formatBytes(item.file_size)}</td>
                    <td data-label="Requested">{item.requested_at ?? '—'}</td>
                    <td data-label="Action">
                      {item.status === 'completed' ? (
                        <button
                          type="button"
                          className="button button--small"
                          disabled={downloadId === item.id}
                          onClick={() => void handleDownload(item.id)}
                        >
                          {downloadId === item.id ? 'Downloading…' : 'Download'}
                        </button>
                      ) : (
                        <span className="task-badge">{item.status.replaceAll('_', ' ')}</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </ReportSection>
    </div>
  )
}