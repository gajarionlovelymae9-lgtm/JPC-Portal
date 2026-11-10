<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiLanguagePreferenceController extends Controller
{
    private const ALLOWED = ['auto', 'english', 'filipino', 'tagalog', 'taglish'];

    public function show(Request $request): JsonResponse
    {
        $value = $this->normalize($request->user()?->ai_language_preference);

        return ApiResponse::success('AI language preference retrieved successfully', [
            'ai_language_preference' => $value,
            'options' => [
                ['value' => 'auto', 'label' => 'Auto Detect'],
                ['value' => 'english', 'label' => 'English'],
                ['value' => 'filipino', 'label' => 'Filipino / Tagalog'],
                ['value' => 'taglish', 'label' => 'Taglish'],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_language_preference' => ['required', 'string', 'in:auto,english,filipino,tagalog,taglish'],
        ]);

        $value = $this->normalize($validated['ai_language_preference']);
        $user = $request->user();
        $user->forceFill(['ai_language_preference' => $value])->save();

        return ApiResponse::success('AI language preference updated successfully', [
            'ai_language_preference' => $value,
        ]);
    }

    private function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === 'tagalog') {
            $value = 'filipino';
        }

        return in_array($value, self::ALLOWED, true) ? ($value === 'tagalog' ? 'filipino' : $value) : 'auto';
    }
}
