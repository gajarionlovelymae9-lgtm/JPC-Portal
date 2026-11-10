<?php

namespace App\Services\Admin;

use App\Enums\AiInsightType;
use App\Models\AiInsight;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin AI Insights data source.
 *
 * HONESTY CONTRACT (deliberate, not accidental):
 *  - This service never invents an insight and never invents a score.
 *  - `ai_insights` is the only table that holds generated AI insight content.
 *    If it is empty the payload says so explicitly and the UI is expected to
 *    render "No AI insight available" instead of a placeholder card.
 *  - Development seeder output (`jpc.sample` / `development-sample`, or any
 *    `fake`/`stub` provider) is never counted as live AI output. Those rows are
 *    reported separately as sample data and excluded from live KPI totals.
 *  - Provider status uses the same `ai_reports.ai.*` configuration that the
 *    real generation service uses. "Connected" is only true after a real
 *    provider request has actually been recorded, never merely because a key
 *    is present in .env.
 */
class AdminInsightService
{
    public const MESSAGE_NONE = 'No AI insight available';

    /**
     * Full payload for the admin "AI Insights" section.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters = []): array
    {
        $insights = $this->insights($filters);
        $validation = $this->validationOverview($filters);
        $recent = $this->recentAnalysisRuns($filters);
        $sampleRuns = $this->sampleAnalysisRuns();
        $sampleValidation = $validation['sample'];
        $samplePresent = $this->sampleDataPresent();

        return [
            'insights' => $insights['items'],
            'available' => $insights['available'],
            'message' => $insights['available'] ? null : self::MESSAGE_NONE,
            'generated' => $insights['available'],
            'counts' => $insights['counts'],
            'reports_analyzed' => $this->reportsAnalyzed($filters),
            'validation' => $validation,
            'recent_runs' => $recent,
            'sample_runs' => $sampleRuns,
            'sample_data' => [
                'present' => $samplePresent,
                'validation' => $sampleValidation,
                'analysis_runs' => $sampleRuns,
            ],
            'provider_status' => $this->providerStatus(),
            'filters_applied' => array_filter([
                'type' => $filters['type'] ?? null,
                'department_id' => $filters['department_id'] ?? null,
                'employee_id' => $filters['employee_id'] ?? null,
                'scope' => $filters['scope'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ]),
        ];
    }

    /**
     * Real generated insights grouped by AiInsightType. Sample/unknown
     * provenance rows are filtered out so they are never presented as live.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function insights(array $filters = []): array
    {
        $validTypes = array_map(fn (AiInsightType $case) => $case->value, AiInsightType::cases());

        $query = AiInsight::query()
            ->with(['employee:id,first_name,last_name,department_id', 'department:id,name'])
            ->when(! empty($filters['type']) && in_array($filters['type'], $validTypes, true), fn (Builder $q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('department_id', (int) $filters['department_id']))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['from']), fn (Builder $q) => $q->where('generated_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(! empty($filters['to']), fn (Builder $q) => $q->where('generated_at', '<=', Carbon::parse($filters['to'])->endOfDay()));

        $scope = (string) ($filters['scope'] ?? '');
        if ($scope === 'employee') {
            $query->whereNotNull('employee_id');
        } elseif ($scope === 'department') {
            $query->whereNull('employee_id')->whereNotNull('department_id');
        } elseif ($scope === 'institution') {
            $query->whereNull('employee_id')->whereNull('department_id');
        }

        $rows = $query
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get()
            ->reject(fn (AiInsight $insight) => $this->isSampleInsight($insight))
            ->values();

        $items = $rows->take(200)->map(fn (AiInsight $insight) => [
            'id' => $insight->id,
            // AiInsight has no title/severity/confidence columns, so the UI
            // must render `type` + `content` and must not display a confidence
            // value that was never produced.
            'type' => $insight->type?->value,
            'type_label' => $insight->type?->name,
            'content' => $insight->content,
            'metadata' => $insight->metadata,
            'employee_id' => $insight->employee_id,
            'employee' => $insight->employee?->full_name,
            'department_id' => $insight->department_id,
            'department' => $insight->department?->name,
            'generated_at' => $insight->generated_at?->toIso8601String(),
            'scope' => match (true) {
                $insight->employee_id !== null => 'employee',
                $insight->department_id !== null => 'department',
                default => 'institution',
            },
            'is_sample_data' => false,
            'provenance' => data_get($insight->metadata, 'provenance', data_get($insight->metadata, 'source')),
        ])->all();

        $counts = $rows->groupBy(fn (AiInsight $insight) => $insight->type?->value ?? '')
            ->map(fn (Collection $group) => $group->count())
            ->all();

        $byType = [];
        foreach ($validTypes as $type) {
            $byType[] = [
                'type' => $type,
                'label' => AiInsightType::tryFrom($type)?->name ?? $type,
                'total' => (int) ($counts[$type] ?? 0),
            ];
        }

        return [
            'available' => $rows->isNotEmpty(),
            'items' => $items,
            'counts' => [
                'total' => $rows->count(),
                'by_type' => $byType,
            ],
        ];
    }

    /**
     * Aggregate live validation scores. Only rows whose analysis provider is a
     * real, verified engine count toward live KPIs. Sample/unknown rows are
     * returned separately so the UI can label them honestly.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function validationOverview(array $filters = []): array
    {
        $query = DB::table('ai_validation_results as v')
            ->join('reports as r', 'r.id', '=', 'v.report_id')
            ->leftJoin('ai_analyses as a', 'a.id', '=', 'v.ai_analysis_id')
            ->whereNull('r.deleted_at')
            ->select([
                'v.overall_score',
                'v.confidence_score',
                'v.result',
                'v.created_at',
                'a.provider',
                'a.model',
            ]);

        if (! empty($filters['department_id'])) {
            $query->join('employees as e', 'e.id', '=', 'r.employee_id')
                ->where('e.department_id', (int) $filters['department_id'])
                ->addSelect('e.department_id');
        }
        if (! empty($filters['employee_id'])) {
            $query->where('r.employee_id', (int) $filters['employee_id']);
        }
        if (! empty($filters['from'])) {
            $query->where('v.created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('v.created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $rows = $query->get();
        $live = $rows->filter(fn ($row) => $this->isLiveRun($row->provider, $row->model));
        $sample = $rows->reject(fn ($row) => $this->isLiveRun($row->provider, $row->model));

        $liveAgg = $this->aggregateValidation($live);
        $sampleAgg = $this->aggregateValidation($sample);
        $samplePresent = $sample->isNotEmpty() || $this->configuredProviderIsSample();

        return [
            'has_live_data' => $liveAgg['total'] > 0,
            'total' => $liveAgg['total'],
            'avg_score' => $liveAgg['avg_score'],
            'avg_confidence' => $liveAgg['avg_confidence'],
            'passed' => $liveAgg['passed'],
            'warning' => $liveAgg['warning'],
            'failed' => $liveAgg['failed'],
            // 0..1 fractions are stored; the UI must not assume a percentage.
            'score_scale' => '0..1 fraction, exposed as 0..100 here',
            'is_sample_data' => $samplePresent,
            'provenance_note' => $samplePresent
                ? 'Development/unverified validation rows (for example provider "jpc.sample") are stored but excluded from the live KPIs above.'
                : null,
            'sample' => $sampleAgg + ['present' => $sample->isNotEmpty()],
        ];
    }

    /**
     * Recent live AI analysis executions, straight from ai_analyses.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function recentAnalysisRuns(array $filters = [], int $limit = 10): array
    {
        $rows = $this->analysisRuns($filters);

        return $rows
            ->filter(fn ($row) => $this->isLiveRun($row['provider'] ?? null, $row['model'] ?? null))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Development/sample analysis executions, kept visibly separate.
     *
     * @return list<array<string, mixed>>
     */
    public function sampleAnalysisRuns(int $limit = 10): array
    {
        return $this->analysisRuns([])
            ->reject(fn ($row) => $this->isLiveRun($row['provider'] ?? null, $row['model'] ?? null))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Count distinct reports that have at least one completed live analysis.
     *
     * @param  array<string, mixed>  $filters
     */
    public function reportsAnalyzed(array $filters = []): int
    {
        $analysisRows = DB::table('ai_analyses as a')
            ->join('reports as r', 'r.id', '=', 'a.report_id')
            ->whereNull('r.deleted_at')
            ->when(! empty($filters['department_id']), fn ($q) => $q->join('employees as e', 'e.id', '=', 'r.employee_id')
                ->where('e.department_id', (int) $filters['department_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('r.employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('a.completed_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(! empty($filters['to']), fn ($q) => $q->where('a.completed_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->select(['a.report_id', 'a.provider', 'a.model'])
            ->get();

        $analysisReportIds = $analysisRows
            ->filter(fn ($row) => $this->isLiveRun($row->provider, $row->model))
            ->pluck('report_id')
            ->filter();

        $insightReportIds = AiInsight::query()
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('department_id', (int) $filters['department_id']))
            ->when(! empty($filters['employee_id']), fn (Builder $q) => $q->where('employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['from']), fn (Builder $q) => $q->where('generated_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(! empty($filters['to']), fn (Builder $q) => $q->where('generated_at', '<=', Carbon::parse($filters['to'])->endOfDay()))
            ->get()
            ->reject(fn (AiInsight $insight) => $this->isSampleInsight($insight))
            ->map(fn (AiInsight $insight) => data_get($insight->metadata, 'provenance.report_id') ?? data_get($insight->metadata, 'source.report_id'))
            ->filter();

        return $analysisReportIds->merge($insightReportIds)->unique()->count();
    }

    /**
     * Which AI provider the application is actually configured to use, plus
     * the last successful real request recorded in the database.
     *
     * @return array<string, mixed>
     */
    public function providerStatus(): array
    {
        $provider = (string) config('ai_reports.ai.provider', 'fake');
        $model = (string) config('ai_reports.ai.model', '');
        $endpoint = (string) config('ai_reports.ai.http.endpoint', '');
        $apiKey = (string) config('ai_reports.ai.http.api_key', '');

        $configured = ! in_array(strtolower(trim($provider)), ['', 'fake'], true)
            && $endpoint !== ''
            && ($apiKey !== '' || ! str_contains(strtolower($endpoint), 'openai'));

        $lastInsight = AiInsight::query()
            ->orderByDesc('generated_at')
            ->limit(50)
            ->get()
            ->first(fn (AiInsight $insight) => ! $this->isSampleInsight($insight));

        $lastAnalysis = DB::table('ai_analyses as a')
            ->whereNotNull('a.completed_at')
            ->where('a.status', 'completed')
            ->orderByDesc('a.completed_at')
            ->orderByDesc('a.id')
            ->limit(50)
            ->get(['a.provider', 'a.model', 'a.completed_at'])
            ->first(fn ($row) => $this->isLiveRun($row->provider, $row->model));

        $lastInsightAt = $lastInsight?->generated_at;
        $lastAnalysisAt = $lastAnalysis?->completed_at ? Carbon::parse($lastAnalysis->completed_at) : null;
        $lastSuccess = collect([$lastInsightAt, $lastAnalysisAt])->filter()->sortDesc()->first();
        $connected = $configured && $lastSuccess !== null;

        $liveInsights = AiInsight::query()->orderByDesc('generated_at')->limit(200)->get()
            ->reject(fn (AiInsight $insight) => $this->isSampleInsight($insight))
            ->count();
        $liveAnalyses = DB::table('ai_analyses')->get(['provider', 'model'])
            ->filter(fn ($row) => $this->isLiveRun($row->provider, $row->model))
            ->count();

        $hasLiveData = $liveInsights > 0 || $liveAnalyses > 0;
        $samplePresent = $this->sampleDataPresent();

        $note = null;
        if (! $configured) {
            $note = 'The configured AI provider is not a live engine. Set AI_PROVIDER, AI_MODEL, AI_ENDPOINT, and AI_API_KEY in backend/.env, then run php artisan config:clear.';
        } elseif (! $connected) {
            $note = 'The AI provider is configured, but no successful real AI request has been recorded yet.';
        }
        // Sample-data messaging is exposed through `sample_data_present` and the
        // validation/sample blocks. It is deliberately not mixed into the
        // provider note, so the UI can show one dedicated sample-data notice
        // instead of repeating the same warning twice.

        return [
            'configured_provider' => $provider,
            'model' => $model !== '' ? $model : null,
            'endpoint_configured' => $endpoint !== '',
            'api_key_configured' => $apiKey !== '',
            'configured' => $configured,
            'connected' => $connected,
            'live' => $connected,
            'data_source' => $hasLiveData ? 'live_database' : 'no_live_data',
            'data_source_label' => $hasLiveData ? 'Live Database' : 'No Data',
            'last_successful_ai_request' => $lastSuccess?->toIso8601String(),
            'last_successful_analysis' => ($lastAnalysisAt ?? $lastInsightAt)?->toIso8601String(),
            'is_sample_data' => $samplePresent,
            'sample_data_present' => $samplePresent,
            'note' => $note,
        ];
    }

    /**
     * Toolbar options — types come from the enum, never from a hardcoded list.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'types' => array_map(fn (AiInsightType $case) => [
                'value' => $case->value,
                'label' => ucwords(strtolower(str_replace('_', ' ', $case->name))),
            ], AiInsightType::cases()),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'employees' => Employee::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(500)
                ->get(['id', 'first_name', 'last_name', 'employee_number', 'department_id'])
                ->map(fn (Employee $e) => [
                    'value' => $e->id,
                    'label' => trim($e->first_name.' '.$e->last_name).' ('.$e->employee_number.')',
                ])->all(),
            'reports' => Report::query()
                ->orderByDesc('id')
                ->limit(500)
                ->get(['id', 'title'])
                ->map(fn ($r) => ['value' => $r->id, 'label' => '#'.$r->id.' · '.$r->title])->all(),
        ];
    }

    /**
     * True when a provider/model pair is a real engine rather than seeded or
     * unknown output. Unknown/missing provenance never counts as live.
     */
    private function isLiveRun(?string $provider, ?string $model): bool
    {
        $provider = strtolower(trim((string) $provider));
        $model = strtolower(trim((string) $model));

        if ($provider === '' || $provider === 'null') {
            return false;
        }

        return ! $this->isSampleRun($provider, $model);
    }

    private function isSampleRun(?string $provider, ?string $model): bool
    {
        $provider = strtolower((string) $provider);
        $model = strtolower((string) $model);

        return str_contains($provider, 'sample')
            || str_contains($provider, 'fake')
            || str_contains($provider, 'stub')
            || str_contains($model, 'sample')
            || str_contains($model, 'development');
    }

    private function isSampleInsight(AiInsight $insight): bool
    {
        $provenance = (array) (data_get($insight->metadata, 'provenance') ?? data_get($insight->metadata, 'source') ?? []);
        $provider = $provenance['provider'] ?? null;
        $model = $provenance['model'] ?? null;

        // Insights with no provenance at all cannot be verified as live output.
        if ($provider === null && $model === null) {
            return true;
        }

        return ! $this->isLiveRun(is_string($provider) ? $provider : null, is_string($model) ? $model : null);
    }

    /** @return array{total:int,avg_score:?float,avg_confidence:?float,passed:int,warning:int,failed:int} */
    private function aggregateValidation(Collection $rows): array
    {
        $total = $rows->count();
        $avgOverall = $total > 0 ? $rows->avg(fn ($row) => $row->overall_score !== null ? (float) $row->overall_score : null) : null;
        $avgConfidence = $total > 0 ? $rows->avg(fn ($row) => $row->confidence_score !== null ? (float) $row->confidence_score : null) : null;

        return [
            'total' => $total,
            'avg_score' => $avgOverall === null ? null : round((float) $avgOverall * 100, 2),
            'avg_confidence' => $avgConfidence === null ? null : round((float) $avgConfidence * 100, 2),
            'passed' => $rows->where('result', 'pass')->count(),
            'warning' => $rows->where('result', 'warning')->count(),
            'failed' => $rows->where('result', 'fail')->count(),
        ];
    }

    private function configuredProviderIsSample(): bool
    {
        $provider = strtolower(trim((string) config('ai_reports.ai.provider', 'fake')));

        return in_array($provider, ['', 'fake', 'null', 'stub'], true);
    }

    /**
     * True when any stored AI output came from the development seeder or an
     * unverified/non-live provider.
     */
    private function sampleDataPresent(): bool
    {
        if ($this->configuredProviderIsSample()) {
            return true;
        }

        $providers = DB::table('ai_analyses')->distinct()->pluck('provider')
            ->filter()
            ->all();

        foreach ($providers as $provider) {
            if ($this->isSampleRun($provider, null)) {
                return true;
            }
        }

        $insights = AiInsight::query()->orderByDesc('id')->limit(200)->get();
        if ($insights->contains(fn (AiInsight $insight) => $this->isSampleInsight($insight))) {
            return true;
        }

        return false;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function analysisRuns(array $filters): Collection
    {
        return DB::table('ai_analyses as a')
            ->leftJoin('reports as r', 'r.id', '=', 'a.report_id')
            ->when(! empty($filters['department_id']), fn ($q) => $q->join('employees as e', 'e.id', '=', 'r.employee_id')
                ->where('e.department_id', (int) $filters['department_id']))
            ->when(! empty($filters['employee_id']), fn ($q) => $q->where('r.employee_id', (int) $filters['employee_id']))
            ->when(! empty($filters['from']), fn ($q) => $q->where(function ($inner) use ($filters) {
                $inner->where('a.completed_at', '>=', Carbon::parse($filters['from'])->startOfDay())
                    ->orWhere('a.started_at', '>=', Carbon::parse($filters['from'])->startOfDay());
            }))
            ->when(! empty($filters['to']), fn ($q) => $q->where(function ($inner) use ($filters) {
                $inner->where('a.completed_at', '<=', Carbon::parse($filters['to'])->endOfDay())
                    ->orWhere('a.started_at', '<=', Carbon::parse($filters['to'])->endOfDay());
            }))
            ->orderByDesc('a.id')
            ->limit(100)
            ->select([
                'a.id',
                'a.report_id',
                'a.analysis_type',
                'a.provider',
                'a.model',
                'a.status',
                'a.error_message',
                'a.started_at',
                'a.completed_at',
                'r.title',
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'report_id' => $row->report_id,
                'report' => $row->title,
                'analysis_type' => $row->analysis_type,
                'provider' => $row->provider,
                'model' => $row->model,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
                'is_sample_data' => ! $this->isLiveRun($row->provider, $row->model),
            ]);
    }
}
