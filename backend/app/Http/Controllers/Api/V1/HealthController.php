<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health
     *
     * Reports application, API and database status without exposing any
     * credentials, keys or filesystem paths.
     */
    public function __invoke(): JsonResponse
    {
        $databaseStatus = 'ok';
        $message = 'JPC Portal API is running';

        try {
            DB::select('SELECT 1');
        } catch (Throwable $e) {
            report($e);
            $databaseStatus = 'error';
            $message = 'JPC Portal API is running, but the database is unreachable';
        }

        return ApiResponse::success($message, [
            'application' => 'ok',
            'database' => $databaseStatus,
            'api' => 'ok',
            'environment' => app()->environment(),
            'api_version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
