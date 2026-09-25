<?php

namespace App\Services\Reporting;

use Closure;
use Illuminate\Support\Facades\Cache;

class ReportCacheService
{
    private const DEFAULT_TTL = 300; // 5 minutes

    public function remember(string $key, Closure $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        return Cache::remember("jpc_report_{$key}", $ttl, $callback);
    }

    public function forget(string $key): void
    {
        Cache::forget("jpc_report_{$key}");
    }
}
