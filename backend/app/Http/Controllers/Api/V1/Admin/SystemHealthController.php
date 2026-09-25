<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use App\Services\System\SystemMonitoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Admin-only System Health dashboard API (Phase 13).
 *
 * GET /admin/system-health               full dashboard payload
 * GET /admin/system-health/database      database component only
 * GET /admin/system-health/queue         queue component only
 * GET /admin/system-health/storage       storage component only
 * GET /admin/system-health/ai            AI provider component only
 * GET /admin/system-health/ocr           OCR provider component only
 * GET /admin/system-health/history       persisted health-check runs
 *
 * All responses are aggregate status/metrics — no credentials, keys, prompts
 * or report contents are ever returned.
 */
class SystemHealthController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $health,
        private readonly SystemMonitoringService $monitoring,
    ) {}

    public function index(): JsonResponse
    {
        // Record this run so the history endpoint reflects API-triggered checks.
        $check = $this->monitoring->runAndRecord('api');

        return ApiResponse::success('System health loaded successfully.', $check);
    }

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success('System health dashboard loaded successfully.', $this->monitoring->dashboard());
    }

    public function database(): JsonResponse
    {
        return ApiResponse::success('Database health loaded successfully.', $this->health->check()['components']['database']);
    }

    public function queue(): JsonResponse
    {
        return ApiResponse::success('Queue health loaded successfully.', $this->health->check()['components']['queue']);
    }

    public function storage(): JsonResponse
    {
        return ApiResponse::success('Storage health loaded successfully.', $this->health->check()['components']['storage']);
    }

    public function ai(): JsonResponse
    {
        return ApiResponse::success('AI provider health loaded successfully.', $this->health->check()['components']['ai']);
    }

    public function ocr(): JsonResponse
    {
        return ApiResponse::success('OCR provider health loaded successfully.', $this->health->check()['components']['ocr']);
    }

    public function history(): JsonResponse
    {
        return ApiResponse::success('Health history loaded successfully.', $this->monitoring->history(20));
    }
}