<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAiOcrMonitoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiMonitoringController extends Controller
{
    public function __construct(
        private readonly AdminAiOcrMonitoringService $aiService
    ) {}

    public function status(): JsonResponse
    {
        $data = $this->aiService->getAiStatus();

        return ApiResponse::success('AI pipeline status loaded successfully.', $data);
    }

    public function ocr(): JsonResponse
    {
        $data = $this->aiService->getOcrList();

        return ApiResponse::success('OCR processing documents loaded successfully.', $data);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $res = $this->aiService->retryJob($request->user(), $id);

        return ApiResponse::success('Processing retry initiated.', $res);
    }
}
