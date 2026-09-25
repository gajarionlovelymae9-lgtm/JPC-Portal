<?php

namespace App\Jobs\Reporting;

use App\Enums\ReportExportStatus;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\Reporting\Export\ReportRowBuilder;
use App\Services\Reporting\ReportingService;
use App\Services\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GenerateReportExportJob — Phase 9 asynchronous export pipeline (spec items
 * 32-33, 53-55).
 *
 * Re-generates the requested report through ReportingService (role scoped),
 * flattens it with ReportRowBuilder and persists it via the shared
 * ReportExportService writers. The single-flight guarantee (ShouldBeUnique)
 * prevents duplicate file generation for the same export row.
 */
class GenerateReportExportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly ReportExport $reportExport) {}

    public function uniqueId(): string
    {
        return 'report-export:'.$this->reportExport->id;
    }

    public function handle(
        ReportingService $reportingService,
        ReportRowBuilder $rowBuilder,
        ReportExportService $exportService,
    ): void {
        $export = $this->reportExport;

        if ($export->isTerminal()) {
            return;
        }

        $export->forceFill([
            'status' => ReportExportStatus::Processing->value,
            'error_message' => null,
        ])->save();

        try {
            $user = User::find($export->user_id);
            if ($user === null) {
                throw new \RuntimeException('Export owner no longer exists.');
            }

            $filters = is_array($export->filters) ? $export->filters : [];
            $report = $this->generate($reportingService, $export->report_type, $filters, $user);

            [$columns, $rows] = array_values($rowBuilder->build($export->report_type, $report));

            if (count($rows) > (int) config('reports.export_rows_max', 5000)) {
                throw new \RuntimeException(
                    'Export exceeds the '.config('reports.export_rows_max', 5000).' row limit; narrow the filter period.',
                );
            }

            $fileName = $this->fileName($export->report_type, $export->format);
            $path = 'exports/'.date('Y/m').'/'.Str::random(40).'.'.$this->extension($export->format);

            Storage::disk(ReportExport::DISK)->makeDirectory(dirname($path));

            $fullPath = Storage::disk(ReportExport::DISK)->path($path);
            $exportService->writeToFile($fullPath, $columns, $rows, $this->writerFormat($export->format));

            $export->forceFill([
                'status' => ReportExportStatus::Completed->value,
                'file_path' => $path,
                'file_name' => $fileName,
                'file_size' => (int) Storage::disk(ReportExport::DISK)->size($path),
                'row_count' => count($rows),
                'error_line' => null,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('reports.export_file_ttl_hours', 24)),
            ])->save();

            Log::info('report_export.completed', [
                'report_export_id' => $export->id,
                'user_id' => $export->user_id,
                'report_type' => $export->report_type,
                'rows' => count($rows),
            ]);
        } catch (\Throwable $e) {
            $export->forceFill([
                'status' => ReportExportStatus::Failed->value,
                'error_line' => $e->getLine(),
                'error_message' => $e->getMessage(),
            ])->save();

            Log::error('report_export.failed', [
                'report_export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generate(ReportingService $service, string $reportType, array $filters, User $user): array
    {
        return match ($reportType) {
            'employee_progress' => $service->generateEmployeeReport(
                (int) ($filters['employee_id'] ?? $user->employee?->id ?? 0),
                $filters,
                $user,
            ),
            'department_performance' => $service->generateDepartmentReport(
                (int) ($filters['department_id'] ?? 0),
                $filters,
                $user,
            ),
            'institution_performance' => $service->generateInstitutionReport($filters, $user),
            'monthly_performance' => $service->generateMonthlyReport(
                (int) ($filters['year'] ?? now()->year),
                (int) ($filters['month'] ?? now()->month),
                $user,
            ),
            'achievement_summary' => $service->generateAchievementReport($filters, $user),
            'late_reports' => $service->generateLateReport($filters, $user),
            'pending_reports' => $service->generatePendingReport($filters, $user),
            'targets_summary' => $service->generateTargetReport($filters, $user),
            'data_quality' => $service->generateDataQualityReport($filters, $user),
            default => throw new \InvalidArgumentException("Unsupported report type [{$reportType}]"),
        };
    }

    private function fileName(string $reportType, string $format): string
    {
        return sprintf(
            'jpc_%s_%s.%s',
            $reportType,
            now()->format('Ymd_His'),
            $this->extension($format),
        );
    }

    private function extension(string $format): string
    {
        return match (strtolower($format)) {
            'excel', 'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            default => 'csv',
        };
    }

    private function writerFormat(string $format): string
    {
        return strtolower($format) === 'excel' ? 'xlsx' : strtolower($format);
    }
}