import type { ExportFormat, ReportFilters } from '../../types/report'
import type { ReportExportType } from '../../services/reportService'

interface ExportButtonsProps {
  report: ReportExportType
  filters: ReportFilters
  onExport: (report: ReportExportType, format: ExportFormat, filters: ReportFilters) => void
  disabled?: boolean
}

const FORMATS: Array<{ format: ExportFormat; label: string }> = [
  { format: 'csv', label: 'CSV' },
  { format: 'xlsx', label: 'XLSX' },
  { format: 'pdf', label: 'PDF' },
]

/**
 * CSV / XLSX / PDF export buttons for a report. The page owns the download call
 * so it can catch ApiError (e.g. 429 from the export rate-limiter) and surface
 * the message.
 */
export function ExportButtons({ report, filters, onExport, disabled = false }: ExportButtonsProps) {
  return (
    <span className="report-export">
      <span className="report-export__label">Export</span>
      {FORMATS.map(({ format, label }) => (
        <button
          key={format}
          type="button"
          className="button button--small button--secondary"
          disabled={disabled}
          onClick={() => onExport(report, format, filters)}
        >
          {label}
        </button>
      ))}
    </span>
  )
}