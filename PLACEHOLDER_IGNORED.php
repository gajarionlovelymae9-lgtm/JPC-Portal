<?php

namespace App\Jobs\Reports\Ai;

use App\Enums\ReportVersionStatus;
use App\Models\ReportVersion;
use App\Services\AiReports\Ai\Contracts\AiProvider;
use App\Services\AiReports\Json\JsonSchemaValidator;
use App\Services\AiReports\Prompt\IntrusionGuard;
use App\Services\AiReports\ReportAnalysisDossier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Generates the advisory AI report bundle for a single report version.
 *
 * Runs inside phase 3.3-K pipeline. It orchestrates the (optionally remote)
 * AI provider, the injected prompt-guard macros, the strict JSON schema
 * validator and the deterministic anchor analysers, then persists results on
 * the dossier version only. AI output is advisory-by-design: the pipeline
 * never approves or rejects a report — a human reviewer always decides.
 */
abstract class AnalyzeData
{
