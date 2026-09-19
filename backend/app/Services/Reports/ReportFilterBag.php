<?php

namespace App\Services\Reports;

use Illuminate\Support\Arr;

/**
 * Normalized reports query bag.
 *
 * Validation happens in the FormRequest layer (HTTP 422); this value object
 * converts the raw query array into the typed values the report services
 * consume, and echoes back the sanitized filter set for the API `meta`.
 */
final class ReportFilterBag
{
    /** Sort whitelist honoured by every table report. `due_date` resolves to
     * the nearest upcoming due assignment per employee/department.
     */
    public const SORTABLE = [
        'employee_name',
        'department_name',
        'completion_rate',
        'on_time_rate',
        'completed_tasks',
        'overdue_tasks',
        'due_date',
    ];

    public const STATUSES = ['not_started', 'draft', 'assigned', 'accepted', 'in_progress', 'submitted', 'under_review', 'completed', 'rejected', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const DIRECTIONS = ['asc', 'desc'];

    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public const SCOPES = ['daily', 'weekly', 'monthly'];

    public ?int $departmentId;

    public ?int $employeeId;

    public ?int $academicYearId;

    public ?string $fromDate;

    public ?string $toDate;

    public ?int $month;

    public ?int $year;

    public ?string $status;

    public ?string $priority;

    public ?string $search;

    public string $sort;

    public string $direction;

    public int $page;

    public int $perPage;

    public string $format;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(array $filters, ?string $reportType = null)
    {
        $this->departmentId = $this->nullableInt($filters['department_id'] ?? null);
        $this->employeeId = $this->nullableInt($filters['employee_id'] ?? null);
        $this->academicYearId = $this->nullableInt($filters['academic_year_id'] ?? null);
        $this->fromDate = $this->nullableString($filters['from_date'] ?? null);
        $this->toDate = $this->nullableString($filters['to_date'] ?? null);
        $this->month = $this->nullableInt($filters['month'] ?? null);
        $this->year = $this->nullableInt($filters['year'] ?? null);
        $this->status = in_array($filters['status'] ?? null, self::STATUSES, true) ? (string) $filters['status'] : null;
        $this->priority = in_array($filters['priority'] ?? null, self::PRIORITIES, true) ? (string) $filters['priority'] : null;
        $this->search = $this->nullableString($filters['search'] ?? null);

        $sort = (string) ($filters['sort'] ?? 'employee_name');
        $this->sort = in_array($sort, self::SORTABLE, true) ? $sort : 'employee_name';
        $direction = (string) ($filters['direction'] ?? 'asc');
        $this->direction = in_array($direction, self::DIRECTIONS, true) ? $direction : 'asc';

        $this->page = max((int) ($filters['page'] ?? 1), 1);
        $this->perPage = min(max((int) ($filters['per_page'] ?? config('reports.default_per_page', 20)), 1), config('reports.max_per_page', 100));

        $format = strtolower((string) ($filters['format'] ?? 'csv'));
        $this->format = in_array($format, self::FORMATS, true) ? $format : 'csv';

        $scope = strtolower((string) ($filters['scope'] ?? 'monthly'));
        $this->scope = in_array($scope, self::SCOPES, true) ? $scope : 'monthly';

        $this->limit = $this->nullableInt($filters['limit'] ?? null);

        // Keep the serialized filter signature stable for per-slug aggregates.
        $this->reportKey = $reportType ?? 'report';
    }

    public string $reportKey;

    /**
     * Echoable filter set included in the report `meta.filters` envelope.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'department_id' => $this->departmentId,
            'employee_id' => $this->employeeId,
            'academic_year_id' => $this->academicYearId,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
            'month' => $this->month,
            'year' => $this->year,
            'status' => $this->status,
            'priority' => $this->priority,
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'limit' => $this->limit,
            'scope' => $this->scope,
        ];
    }

    public ?int $limit = null;

    public ?string $scope = null;

    /**
     * Cacheable signature for safe-aggregate rollups.
     */
    public function signature(): string
    {
        return hash('sha256', $this->reportKey.'|'.json_encode($this->meta()), false);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Apply common date constraints to a query that has `completed_at`.
     *
     * When neither month/year nor date range are present the query is
     * unconstrained — the report simply summarises the whole scope.
     */
    public function constrainCompletedAt(\Illuminate\Database\Eloquent\Builder $query, string $column = 'completed_at'): \Illuminate\Database\Eloquent\Builder
    {
        if ($this->month !== null && $this->year !== null) {
            return $query
                ->whereYear($column, $this->year)
                ->whereMonth($column, $this->month);
        }

        if ($this->fromDate !== null) {
            $query->whereDate($column, '>=', $this->fromDate);
        }

        if ($this->toDate !== null) {
            $query->whereDate($column, '<=', $this->toDate);
        }

        return $query;
    }
}